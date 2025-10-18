<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Models\Inventory;
use Illuminate\Support\Carbon;

/**
 * Repositório Eloquent para Inventory.
 */
class InventoryRepository
{
    /**
     * Recupera o registro de inventário por product_id.
     */
    public function findByProductId(int $productId): ?Inventory
    {
        /** @var Inventory|null $inv */
        $inv = Inventory::query()->where('product_id', $productId)->first();

        return $inv;
    }

    /**
     * Cria ou atualiza (UPSERT) o inventário para um product_id, ajustando version e last_updated.
     */
    public function upsertByProductId(int $productId, int $quantity, ?Carbon $lastUpdated = null): Inventory
    {
        $lastUpdated = $lastUpdated ?? Carbon::now();

        $inv = $this->findByProductId($productId);

        if (! $inv) {
            $inv = new Inventory([
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
            $inv->version = 0;
        } else {
            $inv->quantity = $quantity;
        }

        $inv->last_updated = $lastUpdated;
        $inv->version = (int) $inv->version + 1;
        $inv->save();

        return $inv;
    }

    public function decrementIfEnough(int $productId, int $quantity): bool
    {
        // UPDATE inventory SET quantity = quantity - :q, version = version+1
        // WHERE product_id = :id AND quantity >= :q
        return (bool) \DB::table('inventory')
            ->where('product_id', $productId)
            ->where('quantity', '>=', $quantity)
            ->update([
                'quantity' => \DB::raw("quantity - {$quantity}"),
                'version' => \DB::raw('version + 1'),
                'last_updated' => now(),
                'updated_at' => now(),
            ]);
    }
}
