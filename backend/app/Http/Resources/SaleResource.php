<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Representation of a Sale for API responses.
 *
 * Campos retornados:
 * - id: int
 * - status: string
 * - total_amount: float
 * - total_cost: float
 * - total_profit: float
 * - created_at: string (ISO 8601)
 * - updated_at: string (ISO 8601)
 * - items: array of SaleItemResource
 */
final class SaleResource extends JsonResource
{
    /**
     * @param  mixed  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $items = (array) ($this->resource['items'] ?? []);

        return [
            'id'           => (int) $this->resource['id'],
            'status'       => (string) $this->resource['status'],
            'total_amount' => (float) $this->resource['total_amount'],
            'total_cost'   => (float) $this->resource['total_cost'],
            'total_profit' => (float) $this->resource['total_profit'],
            'created_at'   => Carbon::parse($this->resource['created_at'])->toISOString(),
            'updated_at'   => Carbon::parse($this->resource['updated_at'])->toISOString(),
            'items'        => array_map(fn ($it) => (new SaleItemResource($it))->resolve(), $items),
        ];
    }
}
