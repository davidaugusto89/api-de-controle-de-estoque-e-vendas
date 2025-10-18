<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners;

use App\Infrastructure\Events\SaleFinalized;
use App\Infrastructure\Jobs\UpdateInventoryJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class UpdateInventoryListener implements ShouldQueue
{
    use InteractsWithQueue;

    /** Garante que o listener só é enfileirado após o COMMIT da transação */
    public bool $afterCommit = true;

    /** Fila preferida deste listener */
    public string $queue = 'inventory';

    public function handle(SaleFinalized $event): void
    {
        try {
            // Use o helper/dispatch estático do Job (retorna PendingDispatch → permite onQueue)
            UpdateInventoryJob::dispatch($event->saleId, $event->items)
                ->onQueue($this->queue);
        } catch (\Throwable $e) {
            // Log útil para diagnosticar rapidamente
            Log::error('Falha no UpdateInventoryListener', [
                'sale_id' => $event->saleId,
                'items'   => $event->items,
                'error'   => $e->getMessage(),
            ]);

            // Repassa a exceção para o worker marcar como failed (e aparecer no Horizon/queue:failed)
            throw $e;
        }
    }
}
