<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners;

use App\Infrastructure\Events\SaleFinalized;
use App\Infrastructure\Jobs\UpdateInventoryJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener que reage ao evento de venda finalizada para atualizar o estoque.
 */
final class UpdateInventoryListener implements ShouldQueue
{
    use InteractsWithQueue;

    /** Garante que o listener só é enfileirado após o COMMIT da transação */
    public bool $afterCommit = true;

    /** Fila preferida deste listener */
    public string $queue = 'inventory';

    /**
     * Manipula o evento disparado quando uma venda é finalizada.
     */
    public function handle(SaleFinalized $event): void
    {
        try {
            // Log antes do dispatch para ter contexto no worker
            Log::info('Dispatching UpdateInventoryJob from listener', ['sale_id' => $event->saleId, 'items_count' => count($event->items)]);

            // Se a conexão da fila for 'sync' (testes), despacha sincronicamente
            // no processo atual para que os bindings do container de teste
            // (mocks) sejam visíveis ao handler do job. Caso contrário, despachar
            // para a fila como antes.
            $default = config('queue.default');
            if ($default === 'sync') {
                $job = new \App\Infrastructure\Jobs\UpdateInventoryJob($event->saleId, $event->items);
                app(\Illuminate\Bus\Dispatcher::class)->dispatchSync($job);
            } else {
                // Use o helper/dispatch estático do Job (retorna PendingDispatch → permite onQueue)
                UpdateInventoryJob::dispatch($event->saleId, $event->items)
                    ->onQueue($this->queue);
            }
        } catch (\Throwable $e) {
            // Log útil para diagnosticar rapidamente
            Log::error('Falha no UpdateInventoryListener', [
                'sale_id' => $event->saleId,
                'items' => $event->items,
                'error' => $e->getMessage(),
            ]);

            // Repassa a exceção para o worker marcar como failed (e aparecer no Horizon/queue:failed)
            throw $e;
        }
    }
}
