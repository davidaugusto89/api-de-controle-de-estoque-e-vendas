<?php

declare(strict_types=1);

namespace App\Application\Inventory\UseCases;

use App\Support\Traits\WithCacheInvalidation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Limpeza e normalização do inventário.
 *
 * Resumo:
 * - Remove registros órfãos em `inventory` cujo `product_id` não existe em `products`.
 * - Remove registros considerados antigos (last_updated anterior a 90 dias).
 * - Opcionalmente normaliza quantidades negativas para zero.
 * - Invalida caches relacionados ao inventário após a operação.
 *
 * Contrato:
 * - Entrada: bool $normalizeNegativeToZero (default: true)
 * - Saída: array{removed_orphans:int, removed_stale:int, normalized:int} com estatísticas
 * - Efeitos colaterais: alterações em `inventory` e invalidação de caches associados
 *
 * Garantias e recomendações:
 * - A operação é executada dentro de uma transação via `DB::transaction` para
 *   garantir atomicidade das remoções/atualizações.
 * - A invalidação de cache é executada dentro do escopo da transação. Para
 *   garantir invalidação estrita pós-commit em ambientes com listeners/filas,
 *   considere mover a invalidação para um listener de `afterCommit`.
 * - A rotina foi escrita para ser portável entre bancos (usa `whereNotExists`),
 *   mas revise índices e performance em bases grandes antes de executar em produção.
 *
 * Observações operacionais:
 * - Chame esta use-case através de comandos agendados (cron) ou jobs com baixa
 *   prioridade; em cargas pesadas, execute em janelas de manutenção ou pagine
 *   as remoções para evitar bloqueios longos.
 */
final class CleanupOldInventory
{
    use WithCacheInvalidation;

    /**
     * Executa a limpeza e normalização do inventário dentro de uma transação.
     *
     * - Remove registros órfãos (inventory.product_id sem produto correspondente).
     * - Remove registros considerados antigos (last_updated anterior ao corte de 90 dias).
     * - Opcionalmente normaliza quantidades negativas para zero.
     * - Invalida caches relacionados após a operação.
     *
     * @param  bool  $normalizeNegativeToZero  Quando true, quantidades negativas são ajustadas para zero
     * @return array{removed_orphans: int, removed_stale: int, normalized: int} Estatísticas das operações executadas
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
