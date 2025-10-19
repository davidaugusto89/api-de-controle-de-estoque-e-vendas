<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Inventory\Services\InventoryLockService;
use App\Domain\Inventory\Services\StockPolicy;
use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Metrics\MetricsCollector;
use App\Infrastructure\Persistence\Eloquent\InventoryRepository;
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
     *
     *
     * @throws Throwable
     */
    public function handle(
        Transactions $tx,
        InventoryLockService $locks,
        StockPolicy $policy,
        InventoryRepository $inventoryRepo,
        InventoryCache $cache,
        MetricsCollector|LoggerInterface|null $metricsOrLogger = null,
        ?LoggerInterface $logger = null
    ): void {
        // Compatibilidade retroativa: chamadores anteriormente passavam o Logger
        // como 6º argumento. Aceitamos tanto MetricsCollector quanto
        // LoggerInterface aqui e normalizamos as variáveis.

        // Normaliza logger: se metricsOrLogger for um Logger, trate-o como logger.
        if ($metricsOrLogger instanceof LoggerInterface) {
            $logger = $metricsOrLogger;
            // não forçar a resolução de MetricsCollector em testes unitários sem um binding de cache
            $metrics = $this->resolveMetricsSafely();
        } else {
            // metricsOrLogger is either MetricsCollector or null
            $metrics = $metricsOrLogger ?? $this->resolveMetricsSafely();
        }

        $logger ??= new NullLogger;

        // metrics pode ser nulo em testes unitários; proteger as chamadas
        $metrics?->increment('inventory.job.start');

        $processed = [];

        $tx->run(function () use ($locks, $inventoryRepo, &$processed, $logger, $metrics): void {
            foreach ($this->items as $it) {
                $productId = (int) $it['product_id'];
                $quantity = (int) $it['quantity'];

                $logger->info('Processing inventory item', ['sale_id' => $this->saleId, 'product_id' => $productId, 'quantity' => $quantity]);

                try {
                    $locks->lock(
                        $productId,
                        function () use ($inventoryRepo, $productId, $quantity, $logger): void {
                            // Decremento atômico no DB encapsulado no repositório
                            $inventoryRepo->decrementOrFail($productId, $quantity);

                            $logger->info('Decrement applied', ['product_id' => $productId, 'quantity' => $quantity]);
                        },
                        10,
                        5
                    );

                    $metrics?->increment('inventory.item.decrement');
                } catch (\Throwable $e) {
                    $metrics?->increment('inventory.item.failure');
                    $logger->warning('Failed to process inventory item', ['product_id' => $productId, 'error' => $e->getMessage()]);
                    throw $e;
                }

                $processed[] = $productId;
            }
        });

        // Invalida caches relacionados (bump de versão)
        if ($processed !== []) {
            $cache->invalidateByProducts(array_values(array_unique($processed)));

            $logger->info('Cache invalidated for products', ['products' => array_values(array_unique($processed))]);
            $metrics?->increment('inventory.cache.invalidated');
        }

        $metrics?->increment('inventory.job.completed');

        $logger->info('Inventory update completed from sale', [
            'sale_id' => $this->saleId,
            'items' => array_map(
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
     *
     *
     *
     * @throws Throwable
     */
    public function failed(Throwable $e, ?LoggerInterface $logger = null): void
    {
        $logger ??= new NullLogger;

        $meta = [
            'sale_id' => $this->saleId,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
        ];

        // Tratamento específico para exceção de estoque insuficiente
        $metrics = $this->resolveMetricsSafely();

        if ($e instanceof \App\Domain\Inventory\Exceptions\InventoryInsufficientException) {
            $logger->warning('UpdateInventoryJob failed due to insufficient inventory', $meta);
            try {
                $metrics?->increment('inventory.job.failed');
            } catch (\Throwable) {
                // silencioso
            }
        } else {
            $logger->error('UpdateInventoryJob failed', $meta);
            try {
                $metrics?->increment('inventory.job.failed');
            } catch (\Throwable) {
                // silencioso
            }
        }
    }

    /**
     * Tenta resolver MetricsCollector apenas quando o container forneceu um
     * CacheRepository (evita BindingResolutionException em testes unitários).
     */
    private function resolveMetricsSafely(): ?MetricsCollector
    {
        try {
            // Se o container não tem um CacheRepository, não tentamos resolver
            if (! app()->bound(\Illuminate\Contracts\Cache\Repository::class)) {
                return null;
            }

            return app()->make(MetricsCollector::class);
        } catch (\Throwable) {
            return null;
        }
    }
}
