<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Domain\Inventory\Services\InventoryLockService;
use App\Domain\Inventory\Services\StockPolicy;
use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Jobs\UpdateInventoryJob;
use App\Infrastructure\Persistence\Eloquent\InventoryRepository;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Support\Database\Transactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Psr\Log\NullLogger;
use Tests\TestCase;

final class SaleFlowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_flow_decrements_inventory_and_invalidates_cache(): void
    {
        // Arrange: criar produto e inventário
        $product = Product::factory()->create();

        $initialQty = 10;

        /** @var InventoryRepository $invRepo */
        $invRepo = $this->app->make(InventoryRepository::class);
        $invRepo->upsertByProductId((int) $product->id, $initialQty);

        // Captura a versão atual do cache das listas
        $cache          = $this->app->make('cache.store');
        $inventoryCache = new InventoryCache($cache);
        $beforeVersion  = (int) $cache->get('inventory:list_version', 1);
        // Reset metric counters to avoid test cross-contamination
        foreach (['inventory.job.start', 'inventory.job.completed', 'inventory.item.decrement', 'inventory.item.failure', 'inventory.cache.invalidated'] as $m) {
            $cache->put('metrics:'.$m, 0);
        }

        // Act: criar uma venda via endpoint
        $payload = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 3,
                    'unit_price' => $product->sale_price,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sales', $payload);

        // Endpoint enfileira processamento assíncrono; espera 202 Accepted
        // Endpoint retorna 202 (aceito para processamento assíncrono) e fornece sale_id
        $response->assertStatus(202);

        $saleId = $response->json('sale_id') ?? null;
        $this->assertIsInt($saleId, 'sale_id should be present in response');

        // Recupera os items da venda a partir do DB
        $sale  = Sale::findOrFail($saleId);
        $items = $sale->items->map(function ($it) {
            return [
                'product_id' => (int) $it->product_id,
                'quantity'   => (int) $it->quantity,
                'unit_price' => (float) $it->unit_price,
                'unit_cost'  => (float) $it->unit_cost,
            ];
        })->toArray();

        // Execução do job manualmente (sincronamente) com dependências reais
        $job = new UpdateInventoryJob($saleId, $items);

        $tx            = $this->app->make(Transactions::class);
        $locks         = $this->app->make(InventoryLockService::class);
        $policy        = $this->app->make(StockPolicy::class);
        $inventoryRepo = $this->app->make(InventoryRepository::class);

        $job->handle($tx, $locks, $policy, $inventoryRepo, $inventoryCache, new NullLogger);

        // Metrics assertions
        $this->assertSame(1, (int) $cache->get('metrics:inventory.job.start', 0));
        $this->assertSame(1, (int) $cache->get('metrics:inventory.job.completed', 0));
        $this->assertSame(1, (int) $cache->get('metrics:inventory.item.decrement', 0));
        $this->assertSame(1, (int) $cache->get('metrics:inventory.cache.invalidated', 0));

        // Assert: inventário decrementado
        $invAfter = Inventory::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame($initialQty - 3, (int) $invAfter->quantity);

        // Assert: cache version incrementada
        $afterVersion = (int) $cache->get('inventory:list_version', 1);
        $this->assertGreaterThan($beforeVersion, $afterVersion);
    }
}
