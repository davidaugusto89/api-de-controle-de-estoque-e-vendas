<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\SaleItemResource;
use PHPUnit\Framework\TestCase;

final class SaleItemResourceTest extends TestCase
{
    public function test_serializa_campos_e_calcula_totais(): void
    {
        $data = [
            'product_id' => 11,
            'sku' => 'SKU-11',
            'name' => 'Produto 11',
            'quantity' => 3,
            'unit_price' => 10.00,
            'unit_cost' => 6.00,
        ];

        $res = new SaleItemResource($data);
        $arr = $res->toArray(null);

        // Keys
        $this->assertArrayHasKey('product_id', $arr);
        $this->assertArrayHasKey('sku', $arr);
        $this->assertArrayHasKey('name', $arr);
        $this->assertArrayHasKey('quantity', $arr);
        $this->assertArrayHasKey('unit_price', $arr);
        $this->assertArrayHasKey('unit_cost', $arr);
        $this->assertArrayHasKey('line_total', $arr);
        $this->assertArrayHasKey('line_cost', $arr);
        $this->assertArrayHasKey('line_profit', $arr);

        // Types and values
        $this->assertSame(11, $arr['product_id']);
        $this->assertSame('SKU-11', $arr['sku']);
        $this->assertSame('Produto 11', $arr['name']);
        $this->assertSame(3, $arr['quantity']);
        $this->assertIsFloat($arr['unit_price']);
        $this->assertIsFloat($arr['unit_cost']);

        $this->assertEqualsWithDelta(30.0, $arr['line_total'], 0.00001);
        $this->assertEqualsWithDelta(18.0, $arr['line_cost'], 0.00001);
        $this->assertEqualsWithDelta(12.0, $arr['line_profit'], 0.00001);
    }

    public function test_zero_quantity_retorna_totais_zerados(): void
    {
        $data = [
            'product_id' => 1,
            'sku' => 'SKU-0',
            'name' => 'Produto zero',
            'quantity' => 0,
            'unit_price' => 100.00,
            'unit_cost' => 90.00,
        ];

        $arr = (new SaleItemResource($data))->toArray(null);

        $this->assertSame(0, $arr['quantity']);
        $this->assertEqualsWithDelta(0.0, $arr['line_total'], 0.00001);
        $this->assertEqualsWithDelta(0.0, $arr['line_cost'], 0.00001);
        $this->assertEqualsWithDelta(0.0, $arr['line_profit'], 0.00001);
    }

    public function test_profit_negativo_quando_custo_maior(): void
    {
        $data = [
            'product_id' => 2,
            'sku' => 'SKU-NP',
            'name' => 'Produto NP',
            'quantity' => 2,
            'unit_price' => 5.00,
            'unit_cost' => 6.00,
        ];

        $arr = (new SaleItemResource($data))->toArray(null);

        $this->assertEqualsWithDelta(10.0, $arr['line_total'], 0.00001);
        $this->assertEqualsWithDelta(12.0, $arr['line_cost'], 0.00001);
        $this->assertEqualsWithDelta(-2.0, $arr['line_profit'], 0.00001);
    }

    public function test_trata_strings_numericas_e_virgula(): void
    {
        $data = [
            'product_id' => '3',
            'sku' => 'SKU-STR',
            'name' => 'Produto STR',
            'quantity' => '1',
            'unit_price' => '12.34',
            // note: string with comma will be cast by (float) to 2.0 in PHP
            'unit_cost' => '2,50',
        ];

        $arr = (new SaleItemResource($data))->toArray(null);

        $this->assertSame(3, $arr['product_id']);
        $this->assertSame('SKU-STR', $arr['sku']);
        $this->assertSame('Produto STR', $arr['name']);
        $this->assertSame(1, $arr['quantity']);

        $this->assertIsFloat($arr['unit_price']);
        $this->assertIsFloat($arr['unit_cost']);

        $this->assertEqualsWithDelta(12.34, $arr['line_total'], 0.0001);
        // '2,50' cast to float becomes 2.0 in many locales; check line_cost accordingly
        $this->assertEqualsWithDelta(2.0, $arr['line_cost'], 0.0001);
    }

    public function test_valores_nao_numericos_sao_tratados_como_zero(): void
    {
        $data = [
            'product_id' => 4,
            'sku' => 'SKU-NA',
            'name' => 'Produto NA',
            'quantity' => '2',
            'unit_price' => 'abc',
            'unit_cost' => 'def',
        ];

        $arr = (new SaleItemResource($data))->toArray(null);

        $this->assertEqualsWithDelta(0.0, $arr['unit_price'], 0.00001);
        $this->assertEqualsWithDelta(0.0, $arr['unit_cost'], 0.00001);
        $this->assertEqualsWithDelta(0.0, $arr['line_total'], 0.00001);
        $this->assertEqualsWithDelta(0.0, $arr['line_profit'], 0.00001);
    }
}
