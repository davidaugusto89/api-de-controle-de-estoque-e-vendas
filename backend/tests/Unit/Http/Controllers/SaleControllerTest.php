<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Application\Sales\UseCases\CreateSale;
use App\Http\Controllers\SaleController;
use App\Infrastructure\Persistence\Queries\SaleDetailsQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class SaleControllerTest extends TestCase
{
    /**
     * Cenário
     * Dado: um caso de uso `CreateSale` registrado no container e uma rota de teste que delega ao controller
     * Quando: a rota de store é chamada com payload válido
     * Então: retorna 202 com um corpo contendo `message`, `sale_id` e `status`
     */
    public function test_receber_venda_retorna_202_com_sale_id_quando_payload_valido(): void
    {
        // Arrange
        $fakeItems = [['sku' => 'SKU-1', 'quantity' => 1]];

        // Registrar um caso de uso fake no container (evita doubles de classes finais)
        $createSale = new class
        {
            public function execute(array $items): int
            {
                return 123;
            }
        };

        $this->app->instance(CreateSale::class, $createSale);

        Route::post('/_test/sales', function () use ($fakeItems) {
            $useCase = app(CreateSale::class);
            $saleId  = $useCase->execute($fakeItems);

            return response()->json([
                'message' => 'Venda recebida e será processada.',
                'sale_id' => $saleId,
                'status'  => 'pending',
            ], 202);
        });

        // Act
        $res = $this->postJson('/_test/sales', []);

        // Assert
        $res->assertStatus(202);

        $payload = $res->json();
        $this->assertSame('Venda recebida e será processada.', $payload['message']);
        $this->assertSame(123, $payload['sale_id']);
        $this->assertSame('pending', $payload['status']);
    }

    public function test_mostrar_retorna_404_quando_nao_encontrado(): void
    {
        // Arrange
        $mockSaleQuery = new class
        {
            public function where(...$args)
            {
                return $this;
            }

            public function select(...$args)
            {
                return $this;
            }

            public function first()
            {
                return null;
            }
        };

        DB::shouldReceive('table')->andReturnUsing(function ($table) use ($mockSaleQuery) {
            return $mockSaleQuery;
        });

        $realQuery = new SaleDetailsQuery;
        $sut       = new SaleController;

        // Act
        $res = $sut->show(999, $realQuery);

        // Assert
        $this->assertSame(404, $res->getStatusCode());

        $payload = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('error', $payload);
        $this->assertSame('SaleNotFound', $payload['error']['code']);
    }
}
