<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\ReportResource;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ReportResource::class)]
final class ReportResourceTest extends TestCase
{
    public function test_to_array_returns_expected_structure_and_values(): void
    {
        /**
         * Cenário
         * Dado: payload de relatório com period, totals, series e top_products
         * Quando: ReportResource é serializado via toArray
         * Então: mantém estrutura e valores esperados
         */
        $payload = [
            'period' => ['from' => '2025-01-01', 'to' => '2025-01-31'],
            'totals' => [
                'total_sales'  => 2,
                'total_amount' => 100.0,
                'total_cost'   => 60.0,
                'total_profit' => 40.0,
                'avg_ticket'   => 50.0,
            ],
            'series' => [
                ['date' => '2025-01-01', 'total_amount' => 50.0, 'total_profit' => 20.0, 'orders' => 1],
            ],
            'top_products' => [
                ['product_id' => 1, 'sku' => 'SKU-1', 'name' => 'P1', 'quantity' => 2, 'amount' => 100.0, 'profit' => 40.0],
            ],
        ];

        $resource = new ReportResource($payload);

        $request = Request::create('/', 'GET');

        $res = $resource->toArray($request);

        $this->assertArrayHasKey('period', $res);
        $this->assertArrayHasKey('totals', $res);
        $this->assertArrayHasKey('series', $res);
        $this->assertArrayHasKey('top_products', $res);

        $this->assertSame($payload['period'], $res['period']);
        $this->assertSame($payload['totals'], $res['totals']);
        $this->assertSame($payload['series'], $res['series']);
        $this->assertSame($payload['top_products'], $res['top_products']);
    }
}
