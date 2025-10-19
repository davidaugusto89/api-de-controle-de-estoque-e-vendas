<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Queries;

use App\Models\Product;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query para relatórios de vendas.
 */
final class SalesReportQuery
{
    /** @var (callable():mixed)|null */
    private $saleQueryResolver;

    /** @var (callable():mixed)|null */
    private $dbResolver;

    /** @var (callable():mixed)|null */
    private $productQueryResolver;

    /**
     * Define um resolver para consultas de vendas (facilita testes).
     *
     * @param  callable():mixed|null  $resolver
     */
    public function setSaleQueryResolver(?callable $resolver): void
    {
        $this->saleQueryResolver = $resolver;
    }

    /**
     * Injeta um resolver opcional para DB::table(...) (facilita testes).
     *
     * @param  callable():mixed|null  $resolver
     */
    public function setDbResolver(?callable $resolver): void
    {
        $this->dbResolver = $resolver;
    }

    /**
     * Define um resolver para consultas de produtos (facilita testes).
     *
     * @param  callable():mixed|null  $resolver
     */
    public function setProductQueryResolver(?callable $resolver): void
    {
        $this->productQueryResolver = $resolver;
    }

    /**
     * Totais do período, com filtro opcional de SKU.
     *
     * @return array{
     *   total_sales:int,
     *   total_amount:string,
     *   total_cost:string,
     *   total_profit:string,
     *   avg_ticket:string
     * }
     */
    public function totals(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $productSku = null
    ): array {
        // Normalizamos início/fim e usamos a coluna indexada "sale_date"
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $base = ($this->saleQueryResolver ? ($this->saleQueryResolver)() : Sale::query())
            ->betweenDays($from, $to) // usa sale_date (DATE) -> índice
            ->when($productSku, function (EloquentBuilder $q) use ($productSku, $from, $to) {
                // EXISTS com pushdown de SKU + período por sale_date (já indexado)
                $q->whereExists(function ($e) use ($productSku, $from, $to) {
                    $e->from('sale_items as si')
                        ->join('products as p', 'p.id', '=', 'si.product_id')
                        ->whereColumn('si.sale_id', 'sales.id')
                        ->where('p.sku', $productSku)
                        ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()]);
                });
            });

        $row = $base
            ->selectRaw('COUNT(*) as total_sales')
            ->selectRaw('COALESCE(SUM(total_amount),0) as total_amount')
            ->selectRaw('COALESCE(SUM(total_cost),0)   as total_cost')
            ->selectRaw('COALESCE(SUM(total_profit),0) as total_profit')
            ->first();

        $totalSales = (int) ($row?->total_sales ?? 0);
        $totalAmount = (string) ($row?->total_amount ?? '0.00');
        $totalCost = (string) ($row?->total_cost ?? '0.00');
        $totalProfit = (string) ($row?->total_profit ?? '0.00');

        $avgTicket = $totalSales > 0
            ? (string) bcdiv($totalAmount, (string) $totalSales, 2)
            : '0.00';

        return [
            'total_sales' => $totalSales,
            'total_amount' => $totalAmount,
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
            'avg_ticket' => $avgTicket,
        ];
    }

    /**
     * Série por dia (orders, amount, profit) com filtro opcional por SKU.
     *
     * @return Collection<int, array{date:string,total_amount:string,total_profit:string,orders:int}>
     */
    public function byDay(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $productSku = null
    ): Collection {
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        $base = ($this->saleQueryResolver ? ($this->saleQueryResolver)() : Sale::query())
            ->betweenDays($from, $to) // filtro por sale_date (indexado)
            ->when($productSku, function (EloquentBuilder $q) use ($productSku, $from, $to) {
                $q->whereExists(function ($e) use ($productSku, $from, $to) {
                    $e->from('sale_items as si')
                        ->join('products as p', 'p.id', '=', 'si.product_id')
                        ->whereColumn('si.sale_id', 'sales.id')
                        ->where('p.sku', $productSku)
                        ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()]);
                });
            });

        // Agora sem DATE(), usando diretamente a coluna gerada/indexada
        return $base
            ->selectRaw('sales.sale_date as date')
            ->selectRaw('COALESCE(SUM(total_amount),0) as total_amount')
            ->selectRaw('COALESCE(SUM(total_profit),0) as total_profit')
            ->selectRaw('COUNT(*) as orders')
            ->groupBy('sales.sale_date')
            ->orderBy('sales.sale_date', 'asc')
            ->get()
            ->map(static fn ($r) => [
                'date' => (string) $r->date,
                'total_amount' => (string) $r->total_amount,
                'total_profit' => (string) $r->total_profit,
                'orders' => (int) $r->orders,
            ]);
    }

    /**
     * Top N produtos por faturamento ou quantidade no período.
     * Uma única agregação + leftJoinSub (evita N+1).
     *
     * @return Collection<int, array{product_id:int,sku:?string,name:?string,quantity:int,amount:string,profit:string}>
     */
    public function topProducts(
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $limit = 10,
        string $orderBy = 'amount',
        ?string $productSku = null
    ): Collection {
        $limit = max(1, min(1000, $limit));
        $orderBy = $orderBy === 'quantity' ? 'quantity' : 'amount';

        $from = $from->startOfDay();
        $to = $to->endOfDay();

        // Agregação uma vez só (filtra por período na coluna indexada sale_date)
        $agg = ($this->dbResolver ? ($this->dbResolver)() : DB::table('sale_items as si'))
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->when($productSku, function ($q) use ($productSku) {
                $q->join('products as psku', 'psku.id', '=', 'si.product_id')
                    ->where('psku.sku', $productSku);
            })
            ->whereBetween('s.sale_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('si.product_id')
            ->selectRaw('si.product_id')
            ->selectRaw('SUM(si.quantity) as quantity')
            ->selectRaw('SUM(si.quantity * si.unit_price) as amount')
            ->selectRaw('SUM(si.quantity * (CAST(si.unit_price AS DECIMAL(20,2)) - CAST(si.unit_cost AS DECIMAL(20,2)))) as profit');

        $productQuery = $this->productQueryResolver ? ($this->productQueryResolver)() : Product::query();

        return $productQuery
            ->when($productSku, fn (EloquentBuilder $q) => $q->where('sku', $productSku))
            ->leftJoinSub($agg, 'agg', 'agg.product_id', '=', 'products.id')
            ->select('products.id', 'products.sku', 'products.name')
            ->selectRaw('COALESCE(agg.quantity,0) as quantity')
            ->selectRaw('COALESCE(agg.amount,0)   as amount')
            ->selectRaw('COALESCE(agg.profit,0)   as profit')
            ->orderBy($orderBy, 'desc')
            ->limit($limit)
            ->get()
            ->map(static fn ($p) => [
                'product_id' => (int) $p->id,
                'sku' => $p->sku ?: null,
                'name' => $p->name ?: null,
                'quantity' => (int) $p->quantity,
                'amount' => (string) $p->amount,
                'profit' => (string) $p->profit,
            ]);
    }
}
