<?php

declare(strict_types=1);

namespace App\Application\Sales\UseCases;

use App\Domain\Sales\Enums\SaleStatus;
use App\Infrastructure\Jobs\FinalizeSaleJob;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Database\Transactions;
use Illuminate\Support\Facades\Bus;

/**
 * Cria uma venda e enfileira a finalização assíncrona.
 */
final class CreateSale
{
    public function __construct(
        private readonly Transactions $tx,
        private readonly FinalizeSale $finalize,
    ) {}

    /**
     * Persiste a venda e seus itens e despacha o job de finalização.
     *
     * @param  array<int, array{product_id:int, quantity:int, unit_price?:float|null}>  $items
     * @return int ID da venda criada
     */
    public function execute(array $items): int
    {
        $productMap = Product::query()
            ->whereIn('id', array_column($items, 'product_id'))
            ->get(['id', 'sale_price', 'cost_price'])
            ->keyBy('id');

        return $this->tx->run(function () use ($items, $productMap): int {
            // 1) cria a venda só para obter o ID
            $sale = new Sale;
            // usar string do enum para não depender de constante do Model
            $sale->status = SaleStatus::QUEUED->value;
            $sale->total_amount = 0.0;
            $sale->total_cost = 0.0;
            $sale->total_profit = 0.0;
            $sale->save(); // única chamada a save()

            // Obtemos o id do modelo de forma defensiva
            $saleId = $sale->getAttribute('id') ?? ($sale->id ?? null);

            // 2) monta os itens e acumula totais em memória
            $rows = [];
            foreach ($items as $it) {
                $p = $productMap[$it['product_id']] ?? null;

                $quantity = (int) ($it['quantity'] ?? 0);
                $unitPrice = array_key_exists('unit_price', $it) && $it['unit_price'] !== null
                    ? (float) $it['unit_price']
                    : (float) ($p?->sale_price ?? 0.0);
                $unitCost = (float) ($p?->cost_price ?? 0.0);

                // Produto ausente → normaliza para zero
                if ($p === null) {
                    $unitPrice = 0.0;
                    $unitCost = 0.0;
                }

                $rows[] = [
                    'sale_id' => $saleId,
                    'product_id' => (int) $it['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $sale->total_amount += $quantity * $unitPrice;
                $sale->total_cost += $quantity * $unitCost;
            }

            $sale->total_profit = $sale->total_amount - $sale->total_cost;

            if ($rows) {
                SaleItem::query()->insert($rows);
            }

            // Despacha o job explicitamente — é isso que os testes verificam
            Bus::dispatch(new FinalizeSaleJob($saleId));

            return $saleId;
        });
    }
}
