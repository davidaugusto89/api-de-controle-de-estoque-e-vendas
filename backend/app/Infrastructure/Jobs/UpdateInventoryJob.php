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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Atualiza o estoque a partir de uma venda, garantindo consistência transacional
 * e exclusão mútua por produto via lock distribuído.
 */
final class UpdateInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Número máximo de tentativas antes de falhar. */
    public int $tries = 3;

    /** @var array<int,int> Backoff em segundos entre tentativas. */
    public array $backoff = [5, 15, 60];

    /**
     * @param  int  $saleId  ID da venda.
     * @param array<int, array{
     *   product_id:int,
     *   quantity:int,
     *   unit_price?:float,
     *   unit_cost?:float
     * }> $items Itens da venda.
     */
    public function __construct(
        public readonly int $saleId,
        public readonly array $items
    ) {}

    /**
     * Executa a atualização de estoque com transação e lock por produto.
     *
     * @throws Throwable
     */
    public function handle(
        Transactions $tx,
        InventoryLockService $locks,
        StockPolicy $policy,
        ?LoggerInterface $logger = null
    ): void {
        $logger ??= new NullLogger();

        $tx->run(function () use ($locks, $policy): void {
            foreach ($this->items as $it) {
                $productId = (int) $it['product_id'];
                $quantity  = (int) $it['quantity'];

                $locks->lock(
                    $productId,
                    function () use ($policy, $productId, $quantity): void {
                        $policy->decrease($productId, $quantity);
                    },
                    10,
                    5
                );
            }
        });

        $logger->info('Inventory updated from sale', [
            'sale_id' => $this->saleId,
            'items'   => array_map(
                static fn (array $i): array => [
                    'p' => (int) $i['product_id'],
                    'q' => (int) $i['quantity'],
                ],
                $this->items
            ),
        ]);
    }

    /**
     * Callback após esgotar tentativas.
     */
    public function failed(Throwable $e, ?LoggerInterface $logger = null): void
    {
        $logger ??= new NullLogger();

        $logger->error('UpdateInventoryJob failed', [
            'sale_id' => $this->saleId,
            'error'   => $e->getMessage(),
        ]);
    }
}
