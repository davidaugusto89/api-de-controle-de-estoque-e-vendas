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

final class RegisterStockEntry
{
    public function __construct(
        private readonly Transactions $tx,
        private readonly InventoryLockService $lockService,
        private readonly StockPolicy $stockPolicy,
    ) {}

    /**
     * Registra uma entrada de estoque para um produto aplicando custo médio móvel.
     *
     * Fluxo e garantias:
     * - Executa dentro de uma transação usando {@see Transactions}.
     * - Usa {@see InventoryLockService} para lock por `product_id` e evita concorrência
     *   entre atualizações concorrentes de inventário.
     * - Aplica regras da {@see StockPolicy} (por exemplo, caps e não permitir valores proibidos).
     * - Se `unitCost` for fornecido, recalcula o `cost_price` do produto via média ponderada
     *   (custo médio móvel) e salva o produto.
     *
     * @param  int  $productId  ID do produto
     * @param  int  $quantity  Quantidade a adicionar (deve ser positiva)
     * @param  float|null  $unitCost  Custo por unidade da entrada; quando null, não altera o custo do produto
     * @return array<string, mixed> Estrutura consumível pelo `InventoryResource` contendo
     *                              dados do produto e valores agregados (quantidade, valores de estoque, lucro projetado, etc.)
     *
     * @throws \InvalidArgumentException Quando `quantity` for menor ou igual a zero
     * @throws \Illuminate\Database\ModelNotFoundException Quando o produto não existir
     * @throws \Throwable Em caso de falha na transação ou nas operações de banco
     */
    public function execute(int $productId, int $quantity, ?float $unitCost): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantidade deve ser positiva.');
        }

        return $this->tx->run(function () use ($productId, $quantity, $unitCost) {
            return $this->lockService->lock($productId, function () use ($productId, $quantity, $unitCost) {
                /** @var Product $product */
                $product = Product::query()->lockForUpdate()->findOrFail($productId);

                // busca/abre registro de inventário do produto
                /** @var Inventory $inv */
                $inv = Inventory::query()
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if (! $inv) {
                    $inv = new Inventory;
                    $inv->product_id = $product->id;
                    $inv->quantity = 0;
                }

                // aplica política de estoque (ex: não permitir negativo, caps, etc.)
                $newQty = $this->stockPolicy->increase((int) $inv->quantity, $quantity);
                $inv->quantity = $newQty;
                $inv->last_updated = Carbon::now();
                $inv->save();

                // custo médio móvel (se informado)
                if ($unitCost !== null) {
                    $currentQty = (int) $newQty;
                    $prevQty = max(0, $currentQty - $quantity);
                    $prevCost = (float) $product->cost_price;

                    // média ponderada: (q_prev*cost_prev + q_new*unit_cost) / (q_prev+q_new)
                    $den = $prevQty + $quantity;
                    $newCost = $den > 0
                        ? (($prevQty * $prevCost) + ($quantity * (float) $unitCost)) / $den
                        : (float) $unitCost;

                    // arredonda como monetário com 2 casas
                    $product->cost_price = round($newCost, 2);
                    $product->save();
                }

                // retorna shape consumível pelo Resource
                return [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'quantity' => (int) $inv->quantity,
                    'cost_price' => (float) $product->cost_price,
                    'sale_price' => (float) $product->sale_price,
                    'last_updated' => $inv->last_updated?->toISOString(),
                    // valores calculados por item (o Resource também sabe lidar)
                    'stock_cost_value' => (int) $inv->quantity * (float) $product->cost_price,
                    'stock_sale_value' => (int) $inv->quantity * (float) $product->sale_price,
                    'projected_profit' => ((int) $inv->quantity * (float) $product->sale_price)
                                         - ((int) $inv->quantity * (float) $product->cost_price),
                ];
            });
        });
    }
}
