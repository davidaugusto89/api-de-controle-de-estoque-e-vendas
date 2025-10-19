<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\InventoryResource;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InventoryResource::class)]
final class InventoryResourceTest extends TestCase
{
    public function test_serializacao_do_inventario_retorna_campos_esperados_em_portugues(): void
    {
        /**
         * Cenário
         * Dado: registro bruto de inventário
         * Quando: InventoryResource é serializado
         * Então: saída contém campos esperados (em português) e last_updated como ISO string
         */
        // Arrange: montar um recurso com dados vindos da query
        $raw = [
            'product_id' => 123,
            'sku' => 'ABC-123',
            'name' => 'Produto de Teste',
            'quantity' => 5,
            'cost_price' => 10.5,
            'sale_price' => 15.0,
            // valores calculados podem vir prontos
            'stock_cost_value' => 52.5,
            'stock_sale_value' => 75.0,
            'projected_profit' => 22.5,
            'last_updated' => Carbon::now()->toIsoString(),
        ];

        $resource = new InventoryResource($raw);

        // Act: serializar
        $out = $resource->toArray(request());

        // Assert: checar campos principais
        $this->assertSame(123, $out['product_id']);
        $this->assertSame('ABC-123', $out['sku']);
        $this->assertSame('Produto de Teste', $out['name']);
        $this->assertSame(5, $out['quantity']);
        $this->assertSame(10.5, $out['cost_price']);
        $this->assertSame(15.0, $out['sale_price']);
        $this->assertSame(52.5, $out['stock_cost_value']);
        $this->assertSame(75.0, $out['stock_sale_value']);
        $this->assertSame(22.5, $out['projected_profit']);

        // last_updated deve ser uma string ISO 8601 ou null
        $this->assertIsString($out['last_updated']);
    }

    public function test_calculos_sao_gerados_quando_campos_nao_vierem_da_query(): void
    {
        /**
         * Cenário
         * Dado: recurso sem valores calculados explicitamente
         * Quando: InventoryResource serializa
         * Então: calcula stock_cost_value, stock_sale_value e projected_profit corretamente
         */
        // Arrange: recurso sem valores calculados explicitamente
        $raw = [
            'product_id' => 5,
            'product' => [
                'sku' => 'XYZ-999',
                'name' => 'Outro Produto',
                'cost_price' => 2.0,
                'sale_price' => 3.5,
            ],
            'quantity' => 10,
            'last_updated' => Carbon::create(2020, 1, 1, 12, 0, 0)->toIsoString(),
        ];

        $resource = new InventoryResource($raw);

        // Act
        $out = $resource->toArray(request());

        // Assert: valores calculados corretos
        $this->assertSame(20.0, $out['stock_cost_value']);
        $this->assertSame(35.0, $out['stock_sale_value']);
        $this->assertSame(15.0, $out['projected_profit']);
        $this->assertSame('2020-01-01T12:00:00.000000Z', $out['last_updated']);
    }
}
