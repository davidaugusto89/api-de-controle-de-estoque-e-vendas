<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class InventoryResource extends JsonResource
{
    /**
     * Resource para serialização consistente de registros de inventário.
     *
     * Campos retornados:
     * - product_id: int
     * - sku: string
     * - name: string
     * - quantity: int
     * - cost_price: float
     * - sale_price: float
     * - stock_cost_value: float
     * - stock_sale_value: float
     * - projected_profit: float
     * - last_updated: string|null (ISO 8601)
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Valores base (preferindo já vir da query)
        $productId = (int) data_get($this->resource, 'product_id');
        $sku = (string) (data_get($this->resource, 'sku') ?? data_get($this->resource, 'product.sku'));
        $name = (string) (data_get($this->resource, 'name') ?? data_get($this->resource, 'product.name'));
        $quantity = (int) data_get($this->resource, 'quantity', 0);

        // Preços unitários (se vieram da query ou via relation)
        $costPrice = (float) (data_get($this->resource, 'cost_price') ?? data_get($this->resource, 'product.cost_price', 0));
        $salePrice = (float) (data_get($this->resource, 'sale_price') ?? data_get($this->resource, 'product.sale_price', 0));

        // Valores calculados por item — preferir os campos já calculados no SQL
        $stockCostValue = (float) (data_get($this->resource, 'stock_cost_value', $quantity * $costPrice));
        $stockSaleValue = (float) (data_get($this->resource, 'stock_sale_value', $quantity * $salePrice));
        $projectedProfit = (float) (data_get($this->resource, 'projected_profit', $stockSaleValue - $stockCostValue));

        // Data ISO 8601 consistente
        $lastUpdatedRaw = (string) (data_get($this->resource, 'last_updated') ?? data_get($this->resource, 'updated_at'));
        $lastUpdated = $lastUpdatedRaw !== ''
            ? Carbon::parse($lastUpdatedRaw)->toISOString()
            : null;

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'name' => $name,
            'quantity' => $quantity,
            'cost_price' => $costPrice,
            'sale_price' => $salePrice,
            'stock_cost_value' => $stockCostValue,
            'stock_sale_value' => $stockSaleValue,
            'projected_profit' => $projectedProfit,
            'last_updated' => $lastUpdated,
        ];
    }
}
