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

final class ConcurrentSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_sales_for_same_sku_do_not_double_decrement(): void
    {
        // Arrange: produto com quantidade 5
        $product = Product::factory()->create();
        $initial = 5;

        $invRepo = $this->app->make(InventoryRepository::class);
        $invRepo->upsertByProductId((int) $product->id, $initial);

        // Criar duas vendas (serão aceitas para processamento assíncrono)
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => $product->sale_price]],
        ];

        $r1 = $this->postJson('/api/v1/sales', $payload);
        $r2 = $this->postJson('/api/v1/sales', $payload);

        $r1->assertStatus(202);
        $r2->assertStatus(202);

        $saleId1 = $r1->json('sale_id');
        $saleId2 = $r2->json('sale_id');

        $this->assertIsInt($saleId1);
        $this->assertIsInt($saleId2);

        // Preparar dependências
        $tx             = $this->app->make(\App\Support\Database\Transactions::class);
        $locks          = $this->app->make(\App\Domain\Inventory\Services\InventoryLockService::class);
        $policy         = $this->app->make(\App\Domain\Inventory\Services\StockPolicy::class);
        $inventoryRepo  = $this->app->make(InventoryRepository::class);
        $cache          = $this->app->make('cache.store');
        $inventoryCache = new InventoryCache($cache);
        // Reset metrics
        foreach (['inventory.job.start', 'inventory.job.completed', 'inventory.item.decrement', 'inventory.item.failure', 'inventory.cache.invalidated'] as $m) {
            $cache->put('metrics:'.$m, 0);
        }

        // Recupera items de cada venda
        $items1 = Sale::findOrFail($saleId1)->items->map(fn ($it) => ['product_id' => $it->product_id, 'quantity' => $it->quantity, 'unit_price' => $it->unit_price, 'unit_cost' => $it->unit_cost])->toArray();
        $items2 = Sale::findOrFail($saleId2)->items->map(fn ($it) => ['product_id' => $it->product_id, 'quantity' => $it->quantity, 'unit_price' => $it->unit_price, 'unit_cost' => $it->unit_cost])->toArray();

        // Act: processar a primeira venda (deve passar)
        $job1 = new UpdateInventoryJob($saleId1, $items1);
        $job1->handle($tx, $locks, $policy, $inventoryRepo, $inventoryCache, new NullLogger);

        // Assert intercalar: inventário foi decrementado para 2
        $invAfter1 = Inventory::where('product_id', $product->id)->firstOrFail();
        $this->assertSame($initial - 3, (int) $invAfter1->quantity);

        // Metrics after first job
        $this->assertSame(1, (int) $cache->get('metrics:inventory.job.start', 0));
        $this->assertSame(1, (int) $cache->get('metrics:inventory.job.completed', 0));
        $this->assertSame(1, (int) $cache->get('metrics:inventory.item.decrement', 0));
        $this->assertSame(1, (int) $cache->get('metrics:inventory.cache.invalidated', 0));

        // Act: processar a segunda venda — deve lançar InventoryInsufficientException
        $job2 = new UpdateInventoryJob($saleId2, $items2);

        $this->expectException(InventoryInsufficientException::class);

        $job2->handle($tx, $locks, $policy, $inventoryRepo, $inventoryCache, new NullLogger);
    }
}
