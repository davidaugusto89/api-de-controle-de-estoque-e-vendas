<?php

declare(strict_types=1);

namespace App\Application\Sales\UseCases;

use App\Infrastructure\Jobs\FinalizeSaleJob;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Database\Transactions;

/**
 * Caso de uso para criar uma nova venda.
 *
 * Gerencia a criação de uma venda com itens, valida produtos e despacha o job de finalização.
 */
final class CreateSale
{
    /**
     * @param  Transactions  $tx  Manipulador de transações de banco de dados
     * @param  FinalizeSale  $finalizeSale  Caso de uso para finalizar a venda
     */
    public function __construct(
        private readonly Transactions $tx,
        private readonly FinalizeSale $finalizeSale
    ) {}

    /**
     * Executa o processo de criação da venda.
     *
     * @param  array<int, array{product_id: int, quantity: int, unit_price?: ?float}>  $items  Dados dos itens da venda
     * @return int O ID da venda criada
     */
    public function execute(array $items): int
    {
        // valida existência básica de produtos e normaliza preços
        $productMap = Product::query()
            ->whereIn('id', array_column($items, 'product_id'))
            ->get(['id', 'sale_price', 'cost_price'])
            ->keyBy('id');

        return $this->tx->run(function () use ($items, $productMap) {
            /** @var Sale $sale */
            $sale = new Sale;
            $sale->status = Sale::STATUS_QUEUED;
            $sale->total_amount = 0;
            $sale->total_cost = 0;
            $sale->total_profit = 0;
            $sale->save();

            $rows = [];
            foreach ($items as $it) {
                $p = $productMap[$it['product_id']] ?? null;
                // preço unitário: payload > produto
                $unitPrice = isset($it['unit_price']) ? (float) $it['unit_price'] : (float) ($p?->sale_price ?? 0);
                $rows[] = [
                    'sale_id' => $sale->id,
                    'product_id' => (int) $it['product_id'],
                    'quantity' => (int) $it['quantity'],
                    'unit_price' => $unitPrice,
                    'unit_cost' => (float) ($p?->cost_price ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows) {
                SaleItem::query()->insert($rows);
            }

            // Despacha a FINALIZAÇÃO para a fila "sales"
            dispatch(new FinalizeSaleJob($sale->id))->onQueue('sales');

            return $sale->id;
        });
    }
}
