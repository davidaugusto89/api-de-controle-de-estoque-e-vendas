<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Sales\UseCases\CreateSale;
use App\Application\Sales\UseCases\FinalizeSale;
use App\Infrastructure\Jobs\FinalizeSaleJob;
use App\Support\Database\Transactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

/**
 * Cenário
 * Dado: um payload de itens e dependências externas (Transactions, FinalizeSale, modelos Eloquent)
 * Quando: o caso de uso `CreateSale::execute($items)` é executado
 * Então: deve criar a venda, inserir os itens (quando houver), retornar o id da venda e enfileirar `FinalizeSaleJob`
 * Regras de Negócio Relevantes:
 *  - Se o produto não for encontrado, unit_price e unit_cost devem ser normalizados para 0.
 *  - Se o payload informar `unit_price`, este prevalece sobre o preço do produto.
 *  - Se a lista de items for vazia, não deve inserir rows em `sale_items`, mas ainda deve enfileirar o job.
 * Observações:
 *  - Os testes utilizam mocks de nível de modelo (alias / overload) via Mockery; migrar isso exigirá adaptar factories/repositories (@todo: substituir mocks estáticos por injeção de dependência).
 *
 * @covers \App\Application\Sales\UseCases\CreateSale
 */
final class CreateSaleTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Testa o fluxo principal: produtos existem, itens inseridos e job enfileirado.
     */
    public function test_criar_venda_e_despachar_job_quando_sucesso(): void
    {
        // Arrange
        Bus::fake();

        $items = [
            ['product_id' => 1, 'quantity' => 2],
        ];

        // Mock Product::query()->whereIn(...)->get(...)
        $productQuery = Mockery::mock();
        $productQuery->shouldReceive('whereIn')->once()
            ->with('id', [1])->andReturnSelf();
        $productQuery->shouldReceive('get')->once()
            ->with(['id', 'sale_price', 'cost_price'])
            ->andReturn(new Collection([(object) ['id' => 1, 'sale_price' => 10.0, 'cost_price' => 5.0]]));

        // Alias mock para Product estático
        Mockery::mock('alias:App\Models\Product')->shouldReceive('query')
            ->once()->andReturn($productQuery);

        // Mock Sale (overload para interceptar new Sale)
        $saleMock = Mockery::mock('overload:App\Models\Sale');
        $saleMock->shouldReceive('save')->once()->andReturnUsing(function () use ($saleMock) {
            $saleMock->id = 1001;

            return true;
        });

        // Intercepta SaleItem::query()->insert($rows)
        $saleItemQuery = Mockery::mock();
        $saleItemQuery->shouldReceive('insert')->once()->with(Mockery::on(function ($rows) {
            // Verifica o formato básico da linha inserida
            if (! is_array($rows) || count($rows) !== 1) {
                return false;
            }
            $row = $rows[0];

            return isset($row['sale_id'], $row['product_id'], $row['quantity'], $row['unit_price'], $row['unit_cost']);
        }))->andReturnTrue();

        Mockery::mock('alias:App\Models\SaleItem')->shouldReceive('query')
            ->once()->andReturn($saleItemQuery);

        // Transactions que executa o callback imediatamente
        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        $finalize = Mockery::mock(FinalizeSale::class);

        $useCase = new CreateSale($tx, $finalize);

        // Act
        $sut = $useCase;
        $resultId = $sut->execute($items);

        // Assert
        $this->assertSame(1001, $resultId, 'Deve retornar o id setado pelo save do modelo Sale');

        // Assegura que o job de finalização foi enfileirado com o saleId correto
        Bus::assertDispatched(FinalizeSaleJob::class, function ($job) use ($resultId) {
            return $job->saleId === $resultId;
        });
    }

    /**
     * Quando o payload contém unit_price, este deve prevalecer sobre o preço do produto.
     */
    public function test_usar_unit_price_do_payload_quando_presente(): void
    {
        // Arrange
        Bus::fake();

        $items = [
            ['product_id' => 2, 'quantity' => 1, 'unit_price' => 20.5],
        ];

        $productQuery = Mockery::mock();
        $productQuery->shouldReceive('whereIn')->once()->with('id', [2])->andReturnSelf();
        $productQuery->shouldReceive('get')->once()
            ->with(['id', 'sale_price', 'cost_price'])
            ->andReturn(new Collection([(object) ['id' => 2, 'sale_price' => 15.0, 'cost_price' => 7.5]]));

        Mockery::mock('alias:App\Models\Product')->shouldReceive('query')
            ->once()->andReturn($productQuery);

        $saleMock = Mockery::mock('overload:App\Models\Sale');
        $saleMock->shouldReceive('save')->once()->andReturnUsing(function () use ($saleMock) {
            $saleMock->id = 2002;

            return true;
        });

        $saleItemQuery = Mockery::mock();
        $saleItemQuery->shouldReceive('insert')->once()->with(Mockery::on(function ($rows) {
            $row = $rows[0];

            // unit_price enviado no payload deve prevalecer
            return (float) $row['unit_price'] === 20.5;
        }))->andReturnTrue();

        Mockery::mock('alias:App\Models\SaleItem')->shouldReceive('query')
            ->once()->andReturn($saleItemQuery);

        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        $finalize = Mockery::mock(FinalizeSale::class);

        $useCase = new CreateSale($tx, $finalize);

        // Act
        $sut = $useCase;
        $resultId = $sut->execute($items);

        // Assert
        $this->assertSame(2002, $resultId);
        Bus::assertDispatched(FinalizeSaleJob::class);
    }

    /**
     * Quando o produto não é encontrado na base, os preços devem ser normalizados para 0.
     */
    public function test_normalizar_precos_para_zero_quando_produto_ausente(): void
    {
        // Arrange
        Bus::fake();

        $items = [
            ['product_id' => 9999, 'quantity' => 3],
        ];

        $productQuery = Mockery::mock();
        $productQuery->shouldReceive('whereIn')->once()->with('id', [9999])->andReturnSelf();
        $productQuery->shouldReceive('get')->once()
            ->with(['id', 'sale_price', 'cost_price'])
            ->andReturn(new Collection([]));

        Mockery::mock('alias:App\Models\Product')->shouldReceive('query')
            ->once()->andReturn($productQuery);

        $saleMock = Mockery::mock('overload:App\Models\Sale');
        $saleMock->shouldReceive('save')->once()->andReturnUsing(function () use ($saleMock) {
            $saleMock->id = 3003;

            return true;
        });

        $saleItemQuery = Mockery::mock();
        $saleItemQuery->shouldReceive('insert')->once()->with(Mockery::on(function ($rows) {
            $row = $rows[0];

            // produto ausente => unit_price e unit_cost = 0
            return (float) $row['unit_price'] === 0.0 && (float) $row['unit_cost'] === 0.0;
        }))->andReturnTrue();

        Mockery::mock('alias:App\Models\SaleItem')->shouldReceive('query')
            ->once()->andReturn($saleItemQuery);

        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        $finalize = Mockery::mock(FinalizeSale::class);
        $useCase = new CreateSale($tx, $finalize);

        // Act
        $sut = $useCase;
        $resultId = $sut->execute($items);

        // Assert
        $this->assertSame(3003, $resultId);
        Bus::assertDispatched(FinalizeSaleJob::class);
    }

    /**
     * Se a lista de items for vazia, não deve tentar inserir sale_items, mas ainda assim enfileirar o job.
     */
    public function test_nao_inserir_items_quando_lista_vazia_mas_despachar_job(): void
    {
        // Arrange
        Bus::fake();

        $items = [];

        // Produto não é consultado pois array_column retorna [] -> whereIn([], ...)
        $productQuery = Mockery::mock();
        $productQuery->shouldReceive('whereIn')->once()->with('id', [])->andReturnSelf();
        $productQuery->shouldReceive('get')->once()
            ->with(['id', 'sale_price', 'cost_price'])
            ->andReturn(new Collection([]));

        Mockery::mock('alias:App\Models\Product')->shouldReceive('query')
            ->once()->andReturn($productQuery);

        $saleMock = Mockery::mock('overload:App\Models\Sale');
        $saleMock->shouldReceive('save')->once()->andReturnUsing(function () use ($saleMock) {
            $saleMock->id = 4004;

            return true;
        });

        // Quando não há rows, SaleItem::query()->insert não deve ser chamado
        Mockery::mock('alias:App\Models\SaleItem')->shouldReceive('query')->never();

        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        $finalize = Mockery::mock(FinalizeSale::class);

        $useCase = new CreateSale($tx, $finalize);

        // Act
        $sut = $useCase;
        $resultId = $sut->execute($items);

        // Assert
        $this->assertSame(4004, $resultId);
        Bus::assertDispatched(FinalizeSaleJob::class);
    }

    /**
     * Se a transação falhar, a exceção deve ser propagada e o job não deve ser enfileirado.
     */
    public function test_propagar_excecao_quando_transacao_falhar(): void
    {
        // Arrange
        Bus::fake();

        $items = [
            ['product_id' => 1, 'quantity' => 1],
        ];

        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andThrow(new \RuntimeException('DB transaction failed'));

        $finalize = Mockery::mock(FinalizeSale::class);
        $useCase = new CreateSale($tx, $finalize);

        // Assert exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB transaction failed');

        // Act
        try {
            $useCase->execute($items);
        } finally {
            // Job não deve ter sido enfileirado
            Bus::assertNotDispatched(FinalizeSaleJob::class);
        }
    }

    /**
     * Helpers privados para reduzir repetição nos testes.
     */
    private function makeSaleOverloadMock(int $id): Mockery\MockInterface
    {
        $saleMock = Mockery::mock('overload:App\\Models\\Sale');
        $saleMock->shouldReceive('save')->once()->andReturnUsing(function () use ($saleMock, $id) {
            $saleMock->id = $id;

            return true;
        });

        return $saleMock;
    }

    private function makeTransactionsMock()
    {
        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        return $tx;
    }

    /**
     * @todo(Consolidar mocks estáticos de Eloquent: migrar alias/overload para repositório injetável para usar createMock)
     */
}
