<?php

declare(strict_types=1);

namespace App\Application\Reports\UseCases;

use App\Infrastructure\Persistence\Queries\SalesReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Gera relatório de vendas (agregado, séries diárias e ranking por produto) com cache.
 */
final class GenerateSalesReport
{
    public function __construct(
        private readonly SalesReportQuery $query
    ) {}

    /**
     * Normaliza parâmetros, aplica cache e retorna o payload do relatório.
     *
     * @param array{
     *   from?: string|null,
     *   to?: string|null,
     *   start_date?: string|null,
     *   end_date?: string|null,
     *   product_sku?: string|null,
     *   top?: int|null,
     *   order_by?: 'amount'|'quantity'|'profit'|'date'|'sku'|null,
     *   cache_ttl?: int|null
     * } $params
     * @return array{
     *   period: array{from: string, to: string},
     *   totals: array<string, mixed>,
     *   series: array<int, array<string, mixed>>,
     *   top_products: array<int, array<string, mixed>>
     * }
     */
    public function handle(array $params): array
    {
        $now = CarbonImmutable::now();

        $fromInput = Arr::get($params, 'from', Arr::get($params, 'start_date'));
        $toInput   = Arr::get($params, 'to', Arr::get($params, 'end_date'));

        $from = $this->parseDate($fromInput) ?? $now->subDays(30)->startOfDay();
        $to   = $this->parseDate($toInput)   ?? $now->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $periodStart = $from->startOfDay();
        $periodEnd   = $to->endOfDay();

        $sku = trim((string) Arr::get($params, 'product_sku', '')) ?: null;
        $top = max(1, min(1000, (int) (Arr::get($params, 'top', 10))));
        $ttl = max(0, (int) (Arr::get($params, 'cache_ttl', 300)));

        $allowedOrder = ['amount', 'quantity', 'profit', 'date', 'sku'];
        $orderParam   = Arr::get($params, 'order_by');
        $orderBy      = in_array($orderParam, $allowedOrder, true) ? $orderParam : 'amount';

        $cacheKey = sprintf(
            'sales_report:%s:%s:%s:%d:%s',
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            $sku ?? '-',
            $top,
            $orderBy
        );

        return Cache::tags(['sales', 'reports'])->remember(
            $cacheKey,
            $ttl,
            function () use ($periodStart, $periodEnd, $sku, $top, $orderBy): array {
                $totals      = $this->query->totals($periodStart, $periodEnd, $sku);
                $byDay       = $this->query->byDay($periodStart, $periodEnd, $sku)->all();
                $topProducts = $this->query
                    ->topProducts($periodStart, $periodEnd, $top, $orderBy, $sku)
                    ->all();

                return [
                    'period' => [
                        'from' => $periodStart->toDateString(),
                        'to'   => $periodEnd->toDateString(),
                    ],
                    'totals'       => $totals,
                    'series'       => $byDay,
                    'top_products' => $topProducts,
                ];
            }
        );
    }

    /**
     * Converte string ISO em CarbonImmutable, retornando null se inválida.
     */
    private function parseDate(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
