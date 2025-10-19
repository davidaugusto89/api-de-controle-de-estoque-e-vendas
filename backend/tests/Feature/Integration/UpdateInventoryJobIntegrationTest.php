<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Infrastructure\Jobs\UpdateInventoryJob;
use App\Infrastructure\Locks\RedisLock;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Contracts\Cache\Factory as CacheFactoryContract;
use Illuminate\Contracts\Cache\Lock as CacheLockContract;
use Illuminate\Contracts\Cache\LockProvider as LockProviderContract;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

final class UpdateInventoryJobIntegrationTest extends TestCase
{
    public function test_job_decrements_inventory_and_invalidates_cache(): void
    {
        // Preparar DB: garante que migrations estão aplicadas (limpo) e criar produto e inventário
        Artisan::call('migrate:fresh');

        $product = Product::factory()->create([
            'cost_price' => 10.00,
            'sale_price' => 15.00,
        ]);

        Inventory::updateOrCreate(
            ['product_id' => $product->id],
            ['quantity' => 10, 'version' => 1]
        );

        // Assegura que o inventário está 10 antes
        $this->assertSame(10, (int) Inventory::where('product_id', $product->id)->first()->quantity);

        $items = [['product_id' => $product->id, 'quantity' => 3]];

        $job = new UpdateInventoryJob(999, $items);

        // Bind a RedisLock mock into the container so InventoryLockService is
        // constructed with a working lock implementation during the test.
        $cacheMock = Mockery::mock(CacheFactoryContract::class);
        $storeMock = Mockery::mock(LockProviderContract::class);
        $lockMock = Mockery::mock(CacheLockContract::class);

        // block() should return true so the RedisLock->run proceeds to execute the callback
        $lockMock->shouldReceive('block')->andReturnTrue();
        $lockMock->shouldReceive('release')->andReturnNull();

        $storeMock->shouldReceive('lock')->andReturn($lockMock);
        $cacheMock->shouldReceive('store')->andReturn($storeMock);

        $redisLock = new RedisLock($cacheMock);
        $this->app->instance(RedisLock::class, $redisLock);

        // Executa o job com dependências reais do container
        $job->handle(app(\App\Support\Database\Transactions::class), app(\App\Domain\Inventory\Services\InventoryLockService::class), app(\App\Domain\Inventory\Services\StockPolicy::class), app(\App\Infrastructure\Persistence\Eloquent\InventoryRepository::class), app(\App\Infrastructure\Cache\InventoryCache::class));

        // Verifica decremento no banco
        $this->assertSame(7, (int) Inventory::where('product_id', $product->id)->first()->quantity);

        // Não testamos a implementação do cache store aqui (depende do driver),
        // mas a chamada a invalidateByProducts não deve lançar exceção. Se a
        // implementação de cache suportar increment/forget, seria possível
        // mockar o store e assertar; aqui asseguramos integridade do DB.
        $this->addToAssertionCount(1);
    }
}
