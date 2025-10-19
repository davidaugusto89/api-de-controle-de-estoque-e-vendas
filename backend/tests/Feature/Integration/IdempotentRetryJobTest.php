<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Domain\Inventory\Exceptions\InventoryInsufficientException;
use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Jobs\UpdateInventoryJob;
use App\Infrastructure\Persistence\Eloquent\InventoryRepository;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Log\NullLogger;
use Tests\TestCase;

final class IdempotentRetryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_partial_failure_rolls_back_and_retry_applies_once(): void
    {
        // Arrange: dois produtos com estoque suficiente
        $p1 = Product::factory()->create();
        $p2 = Product::factory()->create();

        $invRepo = $this->app->make(InventoryRepository::class);
        $invRepo->upsertByProductId((int) $p1->id, 5);
        $invRepo->upsertByProductId((int) $p2->id, 5);

        // Criar venda com dois itens
        $payload = ['items' => [
            ['product_id' => $p1->id, 'quantity' => 2, 'unit_price' => $p1->sale_price],
            ['product_id' => $p2->id, 'quantity' => 2, 'unit_price' => $p2->sale_price],
        ]];

        $res = $this->postJson('/api/v1/sales', $payload);
        $res->assertStatus(202);

        $saleId = $res->json('sale_id');
        $this->assertIsInt($saleId);

        $items = Sale::findOrFail($saleId)->items->map(fn ($it) => ['product_id' => $it->product_id, 'quantity' => $it->quantity, 'unit_price' => $it->unit_price, 'unit_cost' => $it->unit_cost])->toArray();

        // Criar um repositório decorador que lança ao processar o segundo produto
        $decorator = new class($this->app->make(InventoryRepository::class), $p2->id) extends InventoryRepository
        {
            public function __construct(private InventoryRepository $inner, private int $failOnProductId) {}

            public function decrementOrFail(int $productId, int $quantity): void
            {
                if ($productId === $this->failOnProductId) {
                    throw InventoryInsufficientException::forProduct($productId);
                }

                $this->inner->decrementOrFail($productId, $quantity);
            }
        };

        $tx             = $this->app->make(\App\Support\Database\Transactions::class);
        $locks          = $this->app->make(\App\Domain\Inventory\Services\InventoryLockService::class);
        $policy         = $this->app->make(\App\Domain\Inventory\Services\StockPolicy::class);
        $cacheStore     = $this->app->make('cache.store');
        $inventoryCache = new InventoryCache($cacheStore);
        // Reset metrics
        foreach (['inventory.job.start', 'inventory.job.completed', 'inventory.item.decrement', 'inventory.item.failure', 'inventory.cache.invalidated'] as $m) {
            $cacheStore->put('metrics:'.$m, 0);
        }

        // Act 1: executar job com decorador que falha no segundo item -> deve lançar
        $this->expectException(InventoryInsufficientException::class);

        $job = new UpdateInventoryJob($saleId, $items);
        $job->handle($tx, $locks, $policy, $decorator, $inventoryCache, new NullLogger);

        // After failed job, metrics should reflect a start and an item failure
        $this->assertSame(1, (int) $cacheStore->get('metrics:inventory.job.start', 0));
        $this->assertSame(1, (int) $cacheStore->get('metrics:inventory.item.failure', 0));

        // Se chegou aqui sem exception, falhou o assert. Mas após exception, confirmar rollback:
        $inv1 = Inventory::where('product_id', $p1->id)->firstOrFail();
        $inv2 = Inventory::where('product_id', $p2->id)->firstOrFail();

        $this->assertSame(5, (int) $inv1->quantity, 'Inventory should be unchanged after failed job (transaction rollback)');
        $this->assertSame(5, (int) $inv2->quantity, 'Inventory should be unchanged after failed job (transaction rollback)');

        // Act 2: executar job com repositório real -> deve processar com sucesso
        $job->handle($tx, $locks, $policy, $invRepo, $inventoryCache, new NullLogger);

        // After successful retry, verify metrics were incremented accordingly
        $this->assertSame(2, (int) $cacheStore->get('metrics:inventory.job.start', 0));
        $this->assertSame(1, (int) $cacheStore->get('metrics:inventory.job.completed', 0));
        $this->assertSame(2, (int) $cacheStore->get('metrics:inventory.item.decrement', 0));
        $this->assertSame(1, (int) $cacheStore->get('metrics:inventory.cache.invalidated', 0));

        $inv1After = Inventory::where('product_id', $p1->id)->firstOrFail();
        $inv2After = Inventory::where('product_id', $p2->id)->firstOrFail();

        $this->assertSame(3, (int) $inv1After->quantity);
        $this->assertSame(3, (int) $inv2After->quantity);
    }
}
