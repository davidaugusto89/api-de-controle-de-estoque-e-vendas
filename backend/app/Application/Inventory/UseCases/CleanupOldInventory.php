<?php

declare(strict_types=1);

namespace App\Application\Inventory\UseCases;

use App\Support\Traits\WithCacheInvalidation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Limpa e normaliza o inventário.
 *
 * - Remove órfãos em `inventory` cujo `product_id` não existe em `products`
 * - Remove registros "antigos" (last_updated < agora - 90 dias)
 * - Normaliza quantidades negativas para zero (opcional)
 * - Invalida caches relacionados ao inventário
 */
final class CleanupOldInventory
{
    use WithCacheInvalidation;

    /**
     * @return array{
     *   removed_orphans:int,
     *   removed_stale:int,
     *   normalized:int
     * }
     */
    public function handle(bool $normalizeNegativeToZero = true): array
    {
        $now = CarbonImmutable::now();
        $cutoff = $now->subDays(90);

        return DB::transaction(function () use ($normalizeNegativeToZero, $now, $cutoff) {
            // 1) Remover órfãos (portável entre bancos)
            $removedOrphans = DB::table('inventory')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('products')
                        ->whereColumn('products.id', 'inventory.product_id');
                })
                ->delete();

            // 2) Remover registros antigos (90 dias sem atualização)
            $removedStale = DB::table('inventory')
                ->where('last_updated', '<', $cutoff)
                ->delete();

            // 3) Normalizar quantidades negativas para zero (opcional)
            $normalized = 0;
            if ($normalizeNegativeToZero) {
                $normalized = DB::table('inventory')
                    ->where('quantity', '<', 0)
                    ->update([
                        'quantity' => 0,
                        'last_updated' => $now,
                        'updated_at' => $now,
                    ]);
            }

            // 4) Invalidar caches (depois que a transação confirma)
            //    Se preferir garantir pós-commit estrito, mova para um listener de "afterCommit".
            $this->bustInventoryCaches();

            return [
                'removed_orphans' => $removedOrphans,
                'removed_stale' => $removedStale,
                'normalized' => $normalized,
            ];
        });
    }
}
