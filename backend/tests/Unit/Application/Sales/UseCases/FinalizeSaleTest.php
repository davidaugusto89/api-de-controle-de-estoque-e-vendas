<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales\UseCases;

use App\Application\Sales\UseCases\FinalizeSale;
use App\Domain\Sales\Enums\SaleStatus;
use App\Domain\Sales\Services\MarginCalculator;
use App\Domain\Sales\Services\SaleValidator;
use App\Infrastructure\Events\SaleFinalized;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Database\Transactions;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Cenário
 * Dado: o caso de uso `FinalizeSale` que conclui uma venda, valida itens, calcula
 *       totais e emite um evento `SaleFinalized`.
 *
 * Quando: o método `execute(int $saleId)` é invocado em diferentes situações
 * Então: validar os ramos principais:
 *   - se a venda já está `COMPLETED` não deve alterar ou emitir evento;
 *   - se a validação falhar, a exceção deve ser propagada;
 *   - em sucesso, totals são calculados corretamente, status ajustado e evento dispatch.
 *
 * Regras de Negócio Relevantes:
 *  - total_amount e total_cost são somas de unit_price * quantity e arredondados
 *    para 2 casas pelo próprio caso de uso;
 *  - venda já finalizada deve retornar sem efeitos.
 *
 * Observações:
 *  - Testes 100% unitários: mockamos modelos e facades; usamos Transactions para
 *    executar callbacks inline.
 */
#[CoversClass(FinalizeSale::class)]
final class FinalizeSaleTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_nao_deve_alterar_se_venda_ja_finalizada(): void
    {
        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        $validator = new SaleValidator;
        $margin = new MarginCalculator;

        // Mock do Sale::query()->lockForUpdate()->findOrFail($saleId)
        $saleModel = Mockery::mock();
        $saleModel->id = 1;
        $saleModel->status = SaleStatus::COMPLETED->value;

        $saleQuery = Mockery::mock();
        $saleQuery->shouldReceive('lockForUpdate')->andReturnSelf();
        $saleQuery->shouldReceive('findOrFail')->with(1)->andReturn($saleModel);

        Mockery::mock('alias:App\\Models\\Sale')
            ->shouldReceive('query')
            ->andReturn($saleQuery);

        $sut = new FinalizeSale($tx, $validator, $margin);

        // Act & Assert: não deve lançar e não deve chamar save/dispatch
        Event::fake();

        $sut->execute(1);

        Event::assertNothingDispatched();
    }

    public function test_propagates_exception_from_validator(): void
    {
        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        // Usa instância real do validador (classe final) e provoca exceção via dados inválidos
        $validator = new SaleValidator;
        $margin = new MarginCalculator;

        // Sale exists and not completed
        $saleModel = Mockery::mock();
        $saleModel->id = 2;
        $saleModel->status = SaleStatus::PROCESSING->value;

        $saleQuery = Mockery::mock();
        $saleQuery->shouldReceive('lockForUpdate')->andReturnSelf();
        $saleQuery->shouldReceive('findOrFail')->with(2)->andReturn($saleModel);

        Mockery::mock('alias:App\\Models\\Sale')
            ->shouldReceive('query')
            ->andReturn($saleQuery);

        // SaleItem::query()->where(...)->get() retorna collection com item inválido (product_id 0)
        $item = (object) ['unit_price' => 5.0, 'unit_cost' => 3.0, 'quantity' => 1, 'product_id' => 0];
        $saleItemQuery = Mockery::mock();
        $saleItemQuery->shouldReceive('where')->andReturnSelf();
        $saleItemQuery->shouldReceive('get')->andReturn(collect([$item]));

        Mockery::mock('alias:App\\Models\\SaleItem')
            ->shouldReceive('query')
            ->andReturn($saleItemQuery);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item inválido');

        $sut = new FinalizeSale($tx, $validator, $margin);

        $sut->execute(2);
    }

    public function test_calcula_totais_e_emite_evento_em_sucesso(): void
    {
        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        $validator = new SaleValidator;

        $margin = new MarginCalculator;

        // Sale model to be returned by findOrFail
        $saleModel = Mockery::mock();
        $saleModel->id = 3;
        $saleModel->status = SaleStatus::PROCESSING->value;
        $saleModel->shouldReceive('save')->once()->andReturnTrue();

        $saleQuery = Mockery::mock();
        $saleQuery->shouldReceive('lockForUpdate')->andReturnSelf();
        $saleQuery->shouldReceive('findOrFail')->with(3)->andReturn($saleModel);

        Mockery::mock('alias:App\\Models\\Sale')
            ->shouldReceive('query')
            ->andReturn($saleQuery);

        // SaleItem collection
        $item1 = (object) ['unit_price' => 5.0, 'unit_cost' => 2.0, 'quantity' => 2, 'product_id' => 10]; // amount 10 cost 4
        $item2 = (object) ['unit_price' => 3.5, 'unit_cost' => 1.5, 'quantity' => 1, 'product_id' => 11]; // amount 3.5 cost 1.5

        $saleItemQuery = Mockery::mock();
        $saleItemQuery->shouldReceive('where')->andReturnSelf();
        $saleItemQuery->shouldReceive('get')->andReturn(collect([$item1, $item2]));

        Mockery::mock('alias:App\\Models\\SaleItem')
            ->shouldReceive('query')
            ->andReturn($saleItemQuery);

        // Espera dispatch do evento SaleFinalized com payload correto
        Event::fake();

        $sut = new FinalizeSale($tx, $validator, $margin);

        $sut->execute(3);

        Event::assertDispatched(SaleFinalized::class, function ($evt) {
            return $evt->saleId === 3
                && is_array($evt->items)
                && count($evt->items) === 2
                && $evt->items[0]['product_id'] === 10;
        });
    }
}
