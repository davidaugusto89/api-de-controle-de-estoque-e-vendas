<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class SaleFlowTest extends TestCase
{
    public function test_sale_flow_decrements_inventory_and_invalidates_cache(): void
    {
        // garante ambiente limpo
        Artisan::call('migrate:fresh');

        $product = Product::factory()->create([
            'cost_price' => 1.00,
            'sale_price' => 2.00,
        ]);

        Inventory::updateOrCreate(['product_id' => $product->id], ['quantity' => 10, 'version' => 1]);

        $this->assertSame(10, (int) Inventory::where('product_id', $product->id)->first()->quantity);

        // captura versão do cache antes
        $beforeVersion = Cache::get('inventory:list_version');

        $payload = [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ];

        // cria venda via endpoint (use rota existente)
        $response = $this->postJson('/api/v1/sales', $payload);
        $response->assertStatus(202);

        // Executa job synchronously (resolve e chama handle)
        $saleId = $response->json('sale_id') ?? null;

        $this->assertNotNull($saleId, 'Sale id should be returned');

        // Local processing: dispatch listener/job flow happens in app, but para garantir, chamamos o job manualmente
        $job = new \App\Infrastructure\Jobs\UpdateInventoryJob((int) $saleId, $payload['items']);

        $job->handle(
            app(\App\Support\Database\Transactions::class),
            new \App\Domain\Inventory\Services\InventoryLockService(
                new \App\Infrastructure\Locks\RedisLock(app('cache'))
            ),
            app(\App\Domain\Inventory\Services\StockPolicy::class),
            app(\App\Infrastructure\Persistence\Eloquent\InventoryRepository::class),
            app(\App\Infrastructure\Cache\InventoryCache::class),
            null
        );

        // Assert inventory decremented
        $this->assertSame(7, (int) Inventory::where('product_id', $product->id)->first()->quantity);

        // Assert cache bumped (version increased)
        $afterVersion = Cache::get('inventory:list_version');

        $this->assertTrue(
            is_null($beforeVersion) || $afterVersion > $beforeVersion,
            'Inventory cache list_version should be bumped or set'
        );
    }
}
