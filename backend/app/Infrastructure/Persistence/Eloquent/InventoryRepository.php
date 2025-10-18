<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Models\Inventory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Repositório Eloquent para o modelo Inventory.
 *
 * Fornece operações de busca/upsert e decremento atômico usadas pelos serviços de domínio.
 */
class InventoryRepository
{
    /**
     * Recupera registro de inventário pelo id do produto.
     */
    public function findByProductId(int $productId): ?Inventory
    {
        /** @var Inventory|null $inv */
        $inv = Inventory::query()->where('product_id', $productId)->first();

        return $inv;
    }

    /**
     * Insere ou atualiza o inventário de um produto e atualiza version/last_updated.
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

    /**
     * Decrementa atomica mente a quantidade se houver estoque suficiente.
     * Retorna true quando a atualização afetou uma linha.
     */
    public function decrementIfEnough(int $productId, int $quantity): bool
    {
        return (bool) DB::table('inventory')
            ->where('product_id', $productId)
            ->where('quantity', '>=', $quantity)
            ->update([
                'quantity' => DB::raw("quantity - {$quantity}"),
                'version' => DB::raw('version + 1'),
                'last_updated' => now(),
                'updated_at' => now(),
            ]);
    }
}
