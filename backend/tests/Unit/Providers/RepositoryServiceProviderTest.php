<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Persistence\Eloquent\InventoryRepository;
use App\Infrastructure\Persistence\Eloquent\ProductRepository;
use App\Providers\RepositoryServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Tests\TestCase;

final class RepositoryServiceProviderTest extends TestCase
{
    public function test_register_vincula_repositorios_e_inventory_cache_singleton(): void
    {
        /**
         * Cenário
         * Dado: container vazio e factory de cache fake
         * Quando: register do provider é executado
         * Então: ProductRepository e InventoryRepository são vinculados e InventoryCache é singleton
         */
        $app = new Container;

        // Fábrica de cache fake e repositório com os métodos mínimos usados pelo provider
        $factory = new class
        {
            public function store($name = null)
            {
                return new class
                {
                    public function tags($names)
                    {
                        return $this;
                    }

                    public function flush() {}

                    public function get($key, $default = null) {}

                    public function put($key, $value, $seconds = null) {}

                    public function has($key)
                    {
                        return false;
                    }

                    public function pull($key, $default = null) {}

                    public function increment($key, $value = 1) {}

                    public function decrement($key, $value = 1) {}

                    public function forever($key, $value) {}

                    public function forget($key) {}

                    public function many($keys) {}

                    public function putMany($values, $seconds = null) {}
                };
            }
        };

        $app->instance(CacheFactory::class, $factory);

        $provider = new RepositoryServiceProvider($app);

        $provider->register();

        // ProductRepository e InventoryRepository devem estar vinculados (resolvíveis)
        $this->assertTrue($app->bound(ProductRepository::class));
        $this->assertTrue($app->bound(InventoryRepository::class));

        // InventoryCache singleton deve ser resolvível e instância de InventoryCache
        $instance = $app->make(InventoryCache::class);
        $this->assertInstanceOf(InventoryCache::class, $instance);
    }
}
