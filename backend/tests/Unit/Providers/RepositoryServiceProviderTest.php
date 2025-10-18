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
        $app = new Container;

        // Fake cache factory and repository with minimal methods used by provider
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

        // ProductRepository and InventoryRepository should be bound (resolvable)
        $this->assertTrue($app->bound(ProductRepository::class));
        $this->assertTrue($app->bound(InventoryRepository::class));

        // InventoryCache singleton should be resolvable and instance of InventoryCache
        $instance = $app->make(InventoryCache::class);
        $this->assertInstanceOf(InventoryCache::class, $instance);
    }
}
