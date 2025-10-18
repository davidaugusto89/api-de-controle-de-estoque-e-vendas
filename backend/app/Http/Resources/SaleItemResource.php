<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa um item de venda para respostas da API.
 *
 * Campos retornados:
 * - product_id: int
 * - sku: string
 * - name: string
 * - quantity: int
 * - unit_price: float
 * - unit_cost: float
 * - line_total: float
 * - line_cost: float
 * - line_profit: float
 */
final class SaleItemResource extends JsonResource
{
    /**
     * @param  mixed  $request
     * @return array<string, int|float|string>
     */
    public function toArray($request): array
    {
        return [
            'product_id'  => (int) $this->resource['product_id'],
            'sku'         => (string) $this->resource['sku'],
            'name'        => (string) $this->resource['name'],
            'quantity'    => (int) $this->resource['quantity'],
            'unit_price'  => (float) $this->resource['unit_price'],
            'unit_cost'   => (float) $this->resource['unit_cost'],
            'line_total'  => (float) $this->resource['unit_price']                                          * (int) $this->resource['quantity'],
            'line_cost'   => (float) $this->resource['unit_cost']                                           * (int) $this->resource['quantity'],
            'line_profit' => ((float) $this->resource['unit_price'] - (float) $this->resource['unit_cost']) * (int) $this->resource['quantity'],
        ];
    }
}
