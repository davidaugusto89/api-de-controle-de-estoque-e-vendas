<?php

declare(strict_types=1);

namespace App\Application\Reports\UseCases;

use App\Infrastructure\Persistence\Queries\SalesReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Gera relatório de vendas agregadas e por produto com suporte a cache.
 *
 * Contrato de entrada (array $params):
 * - from|start_date?: string|null  Data inicial (ISO) do período
 * - to|end_date?: string|null      Data final (ISO) do período
 * - product_sku?: string|null      Filtra por SKU de produto
 * - top?: int|null                 Quantidade máxima de produtos no ranking (1-1000)
 * - order_by?: 'amount'|'quantity'|'profit'|'date'|'sku'|null  Ordenação dos top products
 * - cache_ttl?: int|null           TTL do cache em segundos (0 = sem cache)
 *
 * Saída:
 * - array contendo chaves: 'period' (from/to), 'totals' (totais agregados),
 *   'series' (séries diárias) e 'top_products' (lista dos principais produtos).
 *
 * Comportamento e garantias:
 * - Valida e normaliza datas; padrão é últimos 30 dias quando não informado.
 * - Limita `top` entre 1 e 1000 e normaliza `order_by` para valores permitidos.
 * - Usa cache taggeada (`sales`, `reports`) via facade `Cache::tags`. Se `cache_ttl` for 0,
 *   a consulta ainda passa pelo mecanismo de cache, mas o TTL será 0 (dependendo do driver,
 *   pode comportar-se como sem cache).
 * - Toda a lógica de agregação é delegada a {@see App\Infrastructure\Persistence\Queries\SalesReportQuery}.
 *
 * Observações:
 * - Esta classe produz um payload pensado para consumo por APIs ou exportadores;
 *   formatação adicional (monetária, localidade) deve ser feita pelo layer de apresentação.
 */
final class GenerateSalesReport
{
    public function __construct(
        private readonly SalesReportQuery $query
    ) {}

    /**
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
     */
    public function handle(array $params): array
    {
        $now = CarbonImmutable::now();

        $fromInput = Arr::get($params, 'from', Arr::get($params, 'start_date'));
        $toInput = Arr::get($params, 'to', Arr::get($params, 'end_date'));

        $from = $this->parseDate($fromInput) ?? $now->subDays(30)->startOfDay();
        $to = $this->parseDate($toInput) ?? $now->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $periodStart = $from->startOfDay();
        $periodEnd = $to->endOfDay();

        $sku = trim((string) Arr::get($params, 'product_sku', '')) ?: null;
        $top = max(1, min(1000, (int) (Arr::get($params, 'top', 10))));
        $ttl = max(0, (int) (Arr::get($params, 'cache_ttl', 300)));

        $allowedOrder = ['amount', 'quantity', 'profit', 'date', 'sku'];
        $orderBy = in_array(Arr::get($params, 'order_by'), $allowedOrder, true)
            ? Arr::get($params, 'order_by')
            : 'amount';

        $cacheKey = sprintf(
            'sales_report:%s:%s:%s:%d:%s',
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            $sku ?? '-',
            $top,
            $orderBy
        );

        return Cache::tags(['sales', 'reports'])->remember($cacheKey, $ttl, function () use ($periodStart, $periodEnd, $sku, $top, $orderBy) {
            $totals = $this->query->totals($periodStart, $periodEnd, $sku);
            $byDay = $this->query->byDay($periodStart, $periodEnd, $sku)->all();
            $topProducts = $this->query->topProducts($periodStart, $periodEnd, $top, $orderBy, $sku)->all();

            return [
                'period' => [
                    'from' => $periodStart->toDateString(),
                    'to' => $periodEnd->toDateString(),
                ],
                'totals' => $totals,
                'series' => $byDay,
                'top_products' => $topProducts,
            ];
        });
    }

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
