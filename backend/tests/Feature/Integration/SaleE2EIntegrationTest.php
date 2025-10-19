<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Infrastructure\Locks\RedisLock;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Contracts\Cache\Factory as CacheFactoryContract;
use Illuminate\Contracts\Cache\Lock as CacheLockContract;
use Illuminate\Contracts\Cache\LockProvider as LockProviderContract;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

final class SaleE2EIntegrationTest extends TestCase
{
    public function test_post_sale_executes_job_and_updates_inventory_and_invalidates_cache(): void
    {
        // garantir ambiente limpo
        Artisan::call('migrate:fresh');

        $product = Product::factory()->create([
            'cost_price' => 10.00,
            'sale_price' => 15.00,
        ]);

        Inventory::updateOrCreate([
            'product_id' => $product->id,
        ], [
            'quantity' => 5,
            'version' => 1,
        ]);

        $this->assertSame(5, (int) Inventory::where('product_id', $product->id)->first()->quantity);

        // Criar venda via endpoint (E2E)
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 2]]];

        // Preparar mocks para lock e cache e injetar no container ANTES da requisição
        $cacheMock = Mockery::mock(CacheFactoryContract::class);
        $storeMock = Mockery::mock(LockProviderContract::class);
        $lockMock = Mockery::mock(CacheLockContract::class);

        $lockMock->shouldReceive('block')->andReturnTrue();
        $lockMock->shouldReceive('release')->andReturnNull();

        $storeMock->shouldReceive('lock')->andReturn($lockMock);
        $cacheMock->shouldReceive('store')->andReturn($storeMock);

        // Criar um fake cache que implementa os métodos usados por InventoryCache
        $fakeCache = new class
        {
            public array $store = [];

            public array $forgotten = [];

            public function get($k, $d = null)
            {
                return $this->store[$k] ?? $d;
            }

            public function put($k, $v, $ttl = null)
            {
                $this->store[$k] = $v;
            }

            public function increment($k)
            {
                $v = (int) ($this->store[$k] ?? 1);
                $v++;
                $this->store[$k] = $v;

                return $v;
            }

            public function forget($k)
            {
                $this->forgotten[] = $k;
                unset($this->store[$k]);
            }

            public function remember($k, $ttl, $closure)
            {
                if (isset($this->store[$k])) {
                    return $this->store[$k];
                } $v = $closure();
                $this->store[$k] = $v;

                return $v;
            }

            public function lock($k, $ttl = 5)
            {
                return new class
                {
                    public function block($wait, $sleep = null)
                    {
                        return true;
                    }

                    public function release()
                    {
                        return null;
                    }
                };
            }

            public function store()
            {
                return $this;
            }
        };

        $inventoryCacheReal = new \App\Infrastructure\Cache\InventoryCache($fakeCache);

        $redisLock = new RedisLock($cacheMock);
        $this->app->instance(RedisLock::class, $redisLock);
        $this->app->instance(\App\Infrastructure\Cache\InventoryCache::class, $inventoryCacheReal);

        // Fazer requisição ao endpoint de venda
        $resp = $this->postJson('/api/v1/sales', $payload);

        $resp->assertStatus(202);

        // Recupera a venda criada
        $sale = Sale::latest()->first();
        $this->assertNotNull($sale);

        // O job é despachado e executado de forma síncrona na requisição (driver sync durante o teste),
        // portanto não é necessário invocar manualmente o handle() aqui.

        // Verifica decremento e que o fake registrou invalidação
        $this->assertSame(3, (int) Inventory::where('product_id', $product->id)->first()->quantity);
        $fake = $this->app->make(\App\Infrastructure\Cache\InventoryCache::class);
        // extrai o fakeCache interno via reflexão (test-only) para checar forget
        $ref = new \ReflectionObject($fake);
        $prop = $ref->getProperty('cache');
        $prop->setAccessible(true);
        $inner = $prop->getValue($fake);
        $this->assertNotEmpty($inner->forgotten, 'Cache should have had keys forgotten');
    }
}
