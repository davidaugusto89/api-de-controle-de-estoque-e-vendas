<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Persistence\Eloquent\InventoryRepository;
use App\Infrastructure\Persistence\Eloquent\ProductRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Cache default
        $this->app->bind(CacheRepository::class, function ($app) {
            /** @var CacheFactory $factory */
            $factory = $app->make(CacheFactory::class);

            return $factory->store(config('cache.default'));
        });

        // Repositórios concretos (injeção direta por classe)
        $this->app->bind(ProductRepository::class);
        $this->app->bind(InventoryRepository::class);

        // Cache específico do inventário (singleton, chave padronizada)
        $this->app->singleton(InventoryCache::class, function ($app) {
            /** @var CacheRepository $cache */
            $cache = $app->make(CacheRepository::class);

            return new InventoryCache($cache, 'inventory:snapshot', 60);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
