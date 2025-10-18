<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class InventoryQuery
{
    /**
     * Lista (não paginada) de itens de estoque.
     *
     * @param  string|null  $search  filtro por sku|name (like)
     * @param  int|null  $limit  CAP p/ evitar payloads gigantes (null = sem limite)
     * @return Collection<int, array<string,mixed>>
     */
    public function list(?string $search = null, ?int $limit = null): Collection
    {
        $qb = $this->baseQuery($search);
        if ($limit !== null) {
            $qb->limit($limit);
        }

        return $qb->get()->map(fn ($row) => (array) $row);
    }

    /**
     * Lista paginada de itens de estoque (página controlada explicitamente).
     */
    public function paginate(int $perPage = 15, ?string $search = null, int $page = 1): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->baseQuery($search)
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(fn ($row) => (array) $row);

        return $paginator;
    }

    /**
     * Obtém um item de inventário por product_id.
     *
     * @return array<string,mixed>|null
     */
    public function byProductId(int $productId): ?array
    {
        $row = $this->baseQuery()
            ->where('p.id', $productId)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Totais agregados do estoque (filtrados por $search).
     * Retorna: total_cost, total_sale, projected_profit.
     *
     * @return array{total_cost: float, total_sale: float, projected_profit: float}
     */
    public function totals(?string $search = null): array
    {
        $qb = DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id');

        if ($search !== null && $search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $qb->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(p.sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(p.name) LIKE ?', [$like]);
            });
        }

        $totals = $qb->selectRaw('
                COALESCE(SUM(i.quantity * p.cost_price), 0)  as total_cost,
                COALESCE(SUM(i.quantity * p.sale_price), 0)  as total_sale
            ')
            ->first();

        $totalCost = (float) ($totals->total_cost ?? 0);
        $totalSale = (float) ($totals->total_sale ?? 0);

        return [
            'total_cost'       => $totalCost,
            'total_sale'       => $totalSale,
            'projected_profit' => $totalSale - $totalCost,
        ];
    }

    /**
     * Query base (JOIN products p, inventory i), com colunas projetadas e filtros.
     */
    private function baseQuery(?string $search = null): Builder
    {
        $qb = DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->select([
                'p.id as product_id',
                'p.sku',
                'p.name',
                'i.quantity',
                'i.last_updated',
                // Valores por item (úteis no Resource e no front)
                DB::raw('(i.quantity * p.cost_price)  as stock_cost_value'),
                DB::raw('(i.quantity * p.sale_price)  as stock_sale_value'),
                DB::raw('(i.quantity * p.sale_price) - (i.quantity * p.cost_price) as projected_profit'),
            ])
            ->orderBy('p.sku');

        if ($search !== null && $search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $qb->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(p.sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(p.name) LIKE ?', [$like]);
            });
        }

        return $qb;
    }
}
