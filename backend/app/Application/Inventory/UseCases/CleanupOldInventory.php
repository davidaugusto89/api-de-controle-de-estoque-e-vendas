<?php

declare(strict_types=1);

namespace App\Application\Inventory\UseCases;

use App\Support\Traits\WithCacheInvalidation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Limpa registros órfãos/antigos do inventário e normaliza quantidades negativas,
 * invalidando os caches relacionados.
 */
final class CleanupOldInventory
{
    use WithCacheInvalidation;

    /**
     * Executa a limpeza e normalização em transação.
     *
     * @param  bool  $normalizeNegativeToZero  Ajusta quantidades negativas para zero quando true.
     * @return array{removed_orphans:int, removed_stale:int, normalized:int}
     */
    public function handle(bool $normalizeNegativeToZero = true): array
    {
        $now    = CarbonImmutable::now();
        $cutoff = $now->subDays(90);

        return DB::transaction(function () use ($normalizeNegativeToZero, $now, $cutoff): array {
            $removedOrphans = DB::table('inventory')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('products')
                        ->whereColumn('products.id', 'inventory.product_id');
                })
                ->delete();

            $removedStale = DB::table('inventory')
                ->where('last_updated', '<', $cutoff)
                ->delete();

            $normalized = 0;
            if ($normalizeNegativeToZero) {
                $normalized = DB::table('inventory')
                    ->where('quantity', '<', 0)
                    ->update([
                        'quantity'     => 0,
                        'last_updated' => $now,
                        'updated_at'   => $now,
                    ]);
            }

            $this->bustInventoryCaches();

            return [
                'removed_orphans' => $removedOrphans,
                'removed_stale'   => $removedStale,
                'normalized'      => $normalized,
            ];
        });
    }
}
