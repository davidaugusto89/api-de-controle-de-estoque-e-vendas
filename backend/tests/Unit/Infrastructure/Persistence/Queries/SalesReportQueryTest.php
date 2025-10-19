<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Queries;

use App\Infrastructure\Persistence\Queries\SalesReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SalesReportQuery::class)]
final class SalesReportQueryTest extends TestCase
{
    public function test_totals_without_sku_returns_zeros_when_no_rows(): void
    {
        /**
         * Cenário
         * Dado: consulta sem linhas retornadas
         * Quando: totals é chamado sem SKU
         * Então: retorna zeros para totais
         */
        $from = CarbonImmutable::parse('2025-01-01');
        $to   = CarbonImmutable::parse('2025-01-31');

        $fake = new class
        {
            public function betweenDays($from, $to)
            {
                return $this;
            }

            public function selectRaw()
            {
                return $this;
            }

            public function first()
            {
                return null;
            }

            public function when()
            {
                return $this;
            }
        };

        $q = new SalesReportQuery;
        $q->setSaleQueryResolver(fn () => $fake);

        $res = $q->totals($from, $to, null);

        $this->assertSame(0, $res['total_sales']);
        $this->assertSame('0.00', $res['total_amount']);
    }

    public function test_by_day_returns_collection_of_days(): void
    {
        /**
         * Cenário
         * Dado: dataset com uma linha por dia
         * Quando: byDay é chamado
         * Então: retorna Collection de dias com totals
         */
        $from = CarbonImmutable::parse('2025-10-01');
        $to   = CarbonImmutable::parse('2025-10-03');

        $row = (object) ['date' => '2025-10-01', 'total_amount' => '100.00', 'total_profit' => '40.00', 'orders' => 2];

        $fake = new class($row)
        {
            private $row;

            public function __construct($row)
            {
                $this->row = $row;
            }

            public function betweenDays($from, $to)
            {
                return $this;
            }

            public function selectRaw()
            {
                return $this;
            }

            public function groupBy()
            {
                return $this;
            }

            public function orderBy()
            {
                return $this;
            }

            public function get()
            {
                return collect([$this->row]);
            }

            public function when()
            {
                return $this;
            }
        };

        $q = new SalesReportQuery;
        $q->setSaleQueryResolver(fn () => $fake);

        $res = $q->byDay($from, $to, null);

        $this->assertInstanceOf(Collection::class, $res);
        $this->assertSame('2025-10-01', $res->first()['date']);
    }
}
