<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Queries;

use Illuminate\Support\Facades\DB;

final class SaleDetailsQuery
{
    /**
     * @return array<string,mixed>|null
     */
    public function byId(int $saleId): ?array
    {
        $sale = DB::table('sales as s')
            ->where('s.id', $saleId)
            ->select([
                's.id',
                's.status',
                's.total_amount',
                's.total_cost',
                's.total_profit',
                's.created_at',
                's.updated_at',
            ])
            ->first();

        if (! $sale) {
            return null;
        }

        $items = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.sale_id', $saleId)
            ->select([
                'si.product_id',
                'p.sku',
                'p.name',
                'si.quantity',
                'si.unit_price',
                'si.unit_cost',
            ])
            ->orderBy('p.sku')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return [
            'id'           => $sale->id,
            'status'       => $sale->status,
            'total_amount' => (float) $sale->total_amount,
            'total_cost'   => (float) $sale->total_cost,
            'total_profit' => (float) $sale->total_profit,
            'created_at'   => (string) $sale->created_at,
            'updated_at'   => (string) $sale->updated_at,
            'items'        => $items,
        ];
    }
}
