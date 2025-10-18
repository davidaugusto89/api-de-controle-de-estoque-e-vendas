<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Inventory\Services\InventoryLockService;
use App\Domain\Inventory\Services\StockPolicy;
use App\Support\Database\Transactions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class UpdateInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 15, 60];

    /**
     * @param  array<int, array{product_id:int, quantity:int, unit_price?:float, unit_cost?:float}>  $items
     */
    public function __construct(
        public readonly int $saleId,
        public readonly array $items
    ) {
        // Defina fila/afterCommit no dispatch:
        // UpdateInventoryJob::dispatch(...)->onQueue('inventory')->afterCommit();
    }

    public function handle(
        Transactions $tx,
        InventoryLockService $locks,
        StockPolicy $policy
    ): void {
        // $this->timeout = 90; // opcional

        $tx->run(function () use ($locks, $policy) {
            foreach ($this->items as $it) {
                $pid = (int) $it['product_id'];
                $qty = (int) $it['quantity'];

                // Usa o método disponível no serviço:
                $locks->lock($pid, function () use ($policy, $pid, $qty) {
                    $policy->decrease($pid, $qty);
                }, 10, 5);
            }
        });

        Log::info('Inventory updated from sale', [
            'sale_id' => $this->saleId,
            'items' => array_map(
                fn ($i) => ['p' => (int) $i['product_id'], 'q' => (int) $i['quantity']],
                $this->items
            ),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('UpdateInventoryJob failed', [
            'sale_id' => $this->saleId,
            'error' => $e->getMessage(),
        ]);
    }
}
