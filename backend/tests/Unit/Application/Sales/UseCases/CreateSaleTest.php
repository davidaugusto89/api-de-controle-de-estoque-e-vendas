<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales\UseCases;

use App\Application\Sales\UseCases\CreateSale;
use App\Application\Sales\UseCases\FinalizeSale as FinalizeSaleUseCase;
use App\Infrastructure\Jobs\FinalizeSaleJob;
use App\Support\Database\Transactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * Cenário
 * Dado: o caso de uso de aplicação `CreateSale` que persiste uma venda, seus itens
 *       e despacha um job de finalização.
 *
 * Quando: o método `execute(array $items)` é chamado com diferentes conjuntos de itens
 * Então: espera-se que
 *   - o registro da venda seja criado (save chamado uma vez);
 *   - os itens sejam inseridos com valores normalizados (produto ausente -> zeros);
 *   - o job `FinalizeSaleJob` seja despachado com o saleId retornado;
 *   - o método retorne o ID da venda obtido via getAttribute('id') ou propriedade.
 *
 * Regras de Negócio Relevantes:
 *  - Não deve haver chamadas reais ao banco ou filas (tests 100% unitários).
 *  - Produtos ausentes devem ser tratados como preço/custo 0.0.
 *  - O caso de uso usa Transactions::run para isolar transação; nos testes o callback
 *    deve ser executado inline.
 *
 * Observações:
 *  - Usamos Mockery para sobrecarregar modelos/facades e Collection para simular resultados.
 */
#[CoversClass(CreateSale::class)]
final class CreateSaleTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[DataProvider('providerForSuccessfulCreates')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_cria_venda_e_despacha_job_com_itens_validos(
        array $items,
        array $productRows,
        int $expectedSaleId,
        int $expectedInsertedCount,
        float $expectedTotalAmount,
        float $expectedTotalCost
    ): void {
        /**
         * Cenário
         * Dado: o use case `CreateSale` que cria venda e insere itens
         * Quando: `execute(items)` é chamado com diferentes conjuntos de itens
         * Então: a venda é salva, itens inseridos conforme normalização e job `FinalizeSaleJob` é despachado
         */
        // Mocka Transactions para executar o callback inline
        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        // FinalizeSale não é usado diretamente aqui, mas é exigido pelo construtor
        $finalize = Mockery::mock(FinalizeSaleUseCase::class);

        // Mock do Product::query()->whereIn(...)->get([...]) retornando collection keyBy('id')
        $productQuery = Mockery::mock();
        $productQuery->shouldReceive('whereIn')->andReturnSelf();
        $productQuery->shouldReceive('get')->andReturn(collect($productRows));

        Mockery::mock('alias:App\\Models\\Product')
            ->shouldReceive('query')
            ->andReturn($productQuery);

        // Mock do Sale - sobrecarrega instância criada por `new Sale`
        $saleId   = $expectedSaleId;
        $saleMock = Mockery::mock('overload:App\\Models\\Sale');
        $saleMock->shouldReceive('save')->once()->andReturnTrue();
        $saleMock->shouldReceive('getAttribute')->with('id')->andReturn($saleId);

        // Mock do SaleItem::query()->insert($rows)
        $saleItemQuery     = Mockery::mock();
        $insertExpectation = $saleItemQuery->shouldReceive('insert')->once()->withArgs(function (array $rows) use ($expectedInsertedCount, $saleId, $expectedTotalAmount, $expectedTotalCost): bool {
            // Checa contagem de linhas e sale_id e tipos numéricos
            if (count($rows) !== $expectedInsertedCount) {
                return false;
            }

            foreach ($rows as $row) {
                if (! isset($row['sale_id']) || $row['sale_id'] !== $saleId) {
                    return false;
                }

                if (! is_int($row['product_id']) || ! is_int($row['quantity'])) {
                    return false;
                }

                if (! is_float($row['unit_price'] + 0.0) || ! is_float($row['unit_cost'] + 0.0)) {
                    return false;
                }
            }

            // Validação adicional simples: soma dos unit_price * qty aproxima total_amount
            $sumAmount = 0.0;
            $sumCost   = 0.0;
            foreach ($rows as $r) {
                $sumAmount += $r['unit_price'] * $r['quantity'];
                $sumCost   += $r['unit_cost']  * $r['quantity'];
            }

            // Permitir pequena diferença numérica
            return abs($sumAmount - $expectedTotalAmount) < 0.0001
                && abs($sumCost - $expectedTotalCost)     < 0.0001;
        })->andReturnTrue();

        Mockery::mock('alias:App\\Models\\SaleItem')
            ->shouldReceive('query')
            ->andReturn($saleItemQuery);

        // Intercepta o dispatch do job via facade Bus
        Bus::shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($job) use ($saleId): bool {
                return $job instanceof FinalizeSaleJob && $job->saleId === $saleId;
            }))
            ->andReturnNull();

        // Cria SUT
        $sut = new CreateSale($tx, $finalize);

        // Act
        $resultId = $sut->execute($items);

        // Assert
        $this->assertSame($expectedSaleId, $resultId, 'O método deve retornar o ID da venda criado.');
    }

    public static function providerForSuccessfulCreates(): array
    {
        return [
            'item único padrão' => [
                // items
                [['product_id' => 1, 'quantity' => 2]],
                // productRows (objects simulating models retornados pelo query->get())
                [(object) ['id' => 1, 'sale_price' => 10.0, 'cost_price' => 6.0]],
                // expectedSaleId
                555,
                // expectedInsertedCount
                1,
                // expectedTotalAmount
                20.0,
                // expectedTotalCost
                12.0,
            ],

            'override unit_price' => [
                [['product_id' => 2, 'quantity' => 3, 'unit_price' => 12.5]],
                [(object) ['id' => 2, 'sale_price' => 9.99, 'cost_price' => 5.0]],
                777,
                1,
                37.5, // 12.5 * 3
                15.0, // cost_price * 3 (uses product cost)
            ],

            'produto ausente normaliza para zero' => [
                [['product_id' => 999, 'quantity' => 5]],
                // productRows vazio -> produto ausente
                [],
                1010,
                1,
                0.0,
                0.0,
            ],
        ];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_nao_deve_inserir_linhas_quando_items_vazio_mas_despacha_job_com_id(): void
    {
        /**
         * Cenário
         * Dado: chamada com items vazio
         * Quando: `execute([])` for chamado
         * Então: não insere linhas na tabela sale_items, mas despacha job com id retornado
         */
        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        $finalize = Mockery::mock(FinalizeSaleUseCase::class);

        // Product query não deve ser chamado, mas definimos um fallback
        $productQuery = Mockery::mock();
        $productQuery->shouldReceive('whereIn')->andReturnSelf();
        $productQuery->shouldReceive('get')->andReturn(collect([]));

        Mockery::mock('alias:App\\Models\\Product')
            ->shouldReceive('query')
            ->andReturn($productQuery);

        $saleId   = 4242;
        $saleMock = Mockery::mock('overload:App\\Models\\Sale');
        $saleMock->shouldReceive('save')->once()->andReturnTrue();
        $saleMock->shouldReceive('getAttribute')->with('id')->andReturn($saleId);

        // Expectação: insert NÃO chamado
        $saleItemQuery = Mockery::mock();
        $saleItemQuery->shouldReceive('insert')->never();

        Mockery::mock('alias:App\\Models\\SaleItem')
            ->shouldReceive('query')
            ->andReturn($saleItemQuery);

        Bus::shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($job) use ($saleId): bool {
                return $job instanceof FinalizeSaleJob && $job->saleId === $saleId;
            }))
            ->andReturnNull();

        $sut = new CreateSale($tx, $finalize);

        // Act
        $res = $sut->execute([]);

        // Assert
        $this->assertSame($saleId, $res);
    }
}

// Como rodar (Docker / Sail):
// docker compose exec php vendor/bin/phpunit --testsuite Unit --filter CreateSaleTest --coverage-text
// ./vendor/bin/sail test --testsuite=Unit --filter=CreateSaleTest --coverage-text
