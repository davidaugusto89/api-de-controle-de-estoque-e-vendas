<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\SaleResource;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SaleResource::class)]
final class SaleResourceTest extends TestCase
{
    /**
     * Cenário
     * Dado: um array bruto representando uma venda com items
     * Quando: o resource `SaleResource` é serializado via `toArray(request())`
     * Então: o array resultante inclui campos principais, datas em ISO 8601 e items serializados corretamente
     */
    public function test_serializar_venda_retorna_campos_e_items_em_portugues(): void
    {
        /**
         * Cenário
         * Dado: dados brutos de venda com um item
         * Quando: SaleResource é serializado
         * Então: retorna campos principais, datas em ISO 8601 e items serializados corretamente
         */
        // Arrange: montar dados de venda com um item simples
        $raw = [
            'id'           => 77,
            'status'       => 'completed',
            'total_amount' => 100.5,
            'total_cost'   => 60.0,
            'total_profit' => 40.5,
            'created_at'   => Carbon::create(2021, 5, 4, 10, 30, 0)->toIsoString(),
            'updated_at'   => Carbon::create(2021, 5, 5, 11, 0, 0)->toIsoString(),
            'items'        => [
                [
                    'id'         => 1,
                    'product_id' => 10,
                    'sku'        => 'SKU-10',
                    'name'       => 'Produto X',
                    'quantity'   => 2,
                    'unit_price' => 50.25,
                    'unit_cost'  => 30.0,
                ],
            ],
        ];
        $sut = new SaleResource($raw);

        // Act
        $out = $sut->toArray(request());

        // Assert: campos principais
        $this->assertSame(77, $out['id']);
        $this->assertSame('completed', $out['status']);
        $this->assertSame(100.5, $out['total_amount']);
        $this->assertSame(60.0, $out['total_cost']);
        $this->assertSame(40.5, $out['total_profit']);

        // Datas devem estar em ISO 8601
        $this->assertSame('2021-05-04T10:30:00.000000Z', $out['created_at']);
        $this->assertSame('2021-05-05T11:00:00.000000Z', $out['updated_at']);

        // Items: deve serializar usando SaleItemResource (array de arrays)
        $this->assertIsArray($out['items']);
        $this->assertCount(1, $out['items']);
        $this->assertSame(10, $out['items'][0]['product_id']);
        $this->assertSame(2, $out['items'][0]['quantity']);
        $this->assertSame(50.25, $out['items'][0]['unit_price']);
    }
}
