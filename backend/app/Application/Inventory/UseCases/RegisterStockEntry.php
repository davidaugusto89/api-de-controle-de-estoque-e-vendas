<?php

declare(strict_types=1);

namespace App\Application\Inventory\UseCases;

use App\Domain\Inventory\Services\InventoryLockService;
use App\Domain\Inventory\Services\StockPolicy;
use App\Models\Inventory;
use App\Models\Product;
use App\Support\Database\Transactions;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Registra entrada de estoque e atualiza o custo médio móvel quando aplicável.
 */
final class RegisterStockEntry
{
    public function __construct(
        private readonly Transactions $tx,
        private readonly InventoryLockService $lockService,
        private readonly StockPolicy $stockPolicy,
    ) {}

    /**
     * Registra uma entrada de estoque para um produto, aplicando custo médio móvel.
     *
     * @param  int  $productId  ID do produto
     * @param  int  $quantity  Quantidade a adicionar (deve ser positiva)
     * @param  float|null  $unitCost  Custo unitário da entrada; quando null, não altera o custo do produto
     * @return array{
     *   product_id:int,
     *   sku:string,
     *   name:string,
     *   quantity:int,
     *   cost_price:float,
     *   sale_price:float,
     *   last_updated:?string,
     *   stock_cost_value:float,
     *   stock_sale_value:float,
     *   projected_profit:float
     * }
     *
     * @throws InvalidArgumentException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Throwable
     */
    public function execute(int $productId, int $quantity, ?float $unitCost): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantidade deve ser positiva.');
        }

        return $this->tx->run(function () use ($productId, $quantity, $unitCost): array {
            return $this->lockService->lock($productId, function () use ($productId, $quantity, $unitCost): array {
                /** @var Product $product */
                $product = Product::query()->lockForUpdate()->findOrFail($productId);

                /** @var Inventory|null $inv */
                $inv = Inventory::query()
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if (! $inv) {
                    $inv = new Inventory;
                    $inv->product_id = $product->id;
                    $inv->quantity = 0;
                }

                $newQty = $this->stockPolicy->increase((int) $inv->quantity, $quantity);
                $inv->quantity = $newQty;
                $inv->last_updated = Carbon::now();
                $inv->save();

                if ($unitCost !== null) {
                    $currentQty = (int) $newQty;
                    $prevQty = max(0, $currentQty - $quantity);
                    $prevCost = (float) $product->cost_price;

                    $den = $prevQty + $quantity;
                    $newCost = $den > 0
                        ? (($prevQty * $prevCost) + ($quantity * (float) $unitCost)) / $den
                        : (float) $unitCost;

                    $product->cost_price = round($newCost, 2);
                    $product->save();
                }

                return [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'quantity' => (int) $inv->quantity,
                    'cost_price' => (float) $product->cost_price,
                    'sale_price' => (float) $product->sale_price,
                    'last_updated' => $inv->last_updated?->toISOString(),
                    'stock_cost_value' => (int) $inv->quantity * (float) $product->cost_price,
                    'stock_sale_value' => (int) $inv->quantity * (float) $product->sale_price,
                    'projected_profit' => ((int) $inv->quantity * (float) $product->sale_price)
                        - ((int) $inv->quantity * (float) $product->cost_price),
                ];
            });
        });
    }
}
