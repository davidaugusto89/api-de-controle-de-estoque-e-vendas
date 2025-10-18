<?php

declare(strict_types=1);

namespace App\Application\Sales\UseCases;

use App\Infrastructure\Jobs\FinalizeSaleJob;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Database\Transactions;

/**
 * Cria uma venda e enfileira a finalização assíncrona.
 */
final class CreateSale
{
    public function __construct(
        private readonly Transactions $tx
    ) {}

    /**
     * Persiste a venda e seus itens e despacha o job de finalização.
     *
     * @param  array<int, array{product_id:int, quantity:int, unit_price?:float|null}>  $items
     * @return int ID da venda criada
     *
     * @throws \Throwable
     */
    public function execute(array $items): int
    {
        $productMap = Product::query()
            ->whereIn('id', array_column($items, 'product_id'))
            ->get(['id', 'sale_price', 'cost_price'])
            ->keyBy('id');

        return $this->tx->run(function () use ($items, $productMap): int {
            $sale               = new Sale;
            $sale->status       = Sale::STATUS_QUEUED;
            $sale->total_amount = 0;
            $sale->total_cost   = 0;
            $sale->total_profit = 0;
            $sale->save();

            $rows = [];
            foreach ($items as $it) {
                $p         = $productMap[$it['product_id']] ?? null;
                $unitPrice = isset($it['unit_price'])
                    ? (float) $it['unit_price']
                    : (float) ($p?->sale_price ?? 0);

                $rows[] = [
                    'sale_id'    => $sale->id,
                    'product_id' => (int) $it['product_id'],
                    'quantity'   => (int) $it['quantity'],
                    'unit_price' => $unitPrice,
                    'unit_cost'  => (float) ($p?->cost_price ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows) {
                SaleItem::query()->insert($rows);
            }

            dispatch(new FinalizeSaleJob($sale->id))->onQueue('sales');

            return $sale->id;
        });
    }
}
