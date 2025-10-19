<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Reports\UseCases;

use App\Application\Reports\UseCases\GenerateSalesReport;
use App\Infrastructure\Persistence\Queries\SalesReportQuery;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(GenerateSalesReport::class)]
final class GenerateSalesReportTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_cache_miss_calls_query_and_returns_payload(): void
    {
        /**
         * Cenário
         * Dado: o caso de uso `GenerateSalesReport` que gera relatório de vendas
         * Quando: cache miss ocorre
         * Então: consulta SalesReportQuery e retorna payload montado (period, totals, series, top_products)
         */
        // Create a partial mock from a real instance, but do not pass it to the constructor
        $realQuery = new SalesReportQuery;
        /** @var \Mockery\MockInterface $query */
        $query = Mockery::mock($realQuery)->makePartial();

        $periodTotals = ['amount' => 100.0, 'quantity' => 5, 'profit' => 50.0];
        $byDay = [['date' => '2025-01-01', 'amount' => 50.0], ['date' => '2025-01-02', 'amount' => 50.0]];
        $topProducts = [['sku' => 'SKU-1', 'amount' => 60.0], ['sku' => 'SKU-2', 'amount' => 40.0]];

        $query->shouldReceive('totals')->once()->andReturn($periodTotals);
        $query->shouldReceive('byDay')->once()->andReturn(new Collection($byDay));
        $query->shouldReceive('topProducts')->once()->andReturn(new Collection($topProducts));

        // Provide a cache resolver that simulates a real cache tags object
        $cacheMock = Mockery::mock();
        $cacheMock->shouldReceive('remember')->once()->andReturnUsing(function ($key, $ttl, $cb) {
            return $cb();
        });

        $sut = new GenerateSalesReport($realQuery);
        $sut->setQueryResolver(fn () => $query);
        $sut->setCacheResolver(fn () => $cacheMock);

        $res = $sut->handle([]);

        $this->assertArrayHasKey('period', $res);
        $this->assertArrayHasKey('totals', $res);
        $this->assertArrayHasKey('series', $res);
        $this->assertArrayHasKey('top_products', $res);
    }

    public function test_cache_hit_returns_cached_value_without_query(): void
    {
        /**
         * Cenário
         * Dado: cache com valor pré-existente
         * Quando: `handle()` é invocado
         * Então: retorna o valor do cache sem chamar métodos do query
         */
        $realQuery2 = new SalesReportQuery;
        /** @var \Mockery\MockInterface $query */
        $query = Mockery::mock($realQuery2)->makePartial();

        $cached = [
            'period' => ['from' => '2025-01-01', 'to' => '2025-01-02'],
            'totals' => ['amount' => 1.0],
            'series' => [],
            'top_products' => [],
        ];

        $cacheMock = Mockery::mock();
        $cacheMock->shouldReceive('remember')->once()->andReturn($cached);

        // Ensure query methods are NOT called
        $query->shouldReceive('totals')->never();
        $query->shouldReceive('byDay')->never();
        $query->shouldReceive('topProducts')->never();

        $sut = new GenerateSalesReport($realQuery2);
        $sut->setQueryResolver(fn () => $query);
        $sut->setCacheResolver(fn () => $cacheMock);

        $res = $sut->handle([]);

        $this->assertSame($cached, $res);
    }
}
