<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Metrics\MetricsCollector;
use App\Infrastructure\Persistence\Queries\InventoryQuery;
use App\Support\Database\Contracts\Transactions as TransactionsContract;
use App\Support\Database\Transactions;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Provedor de serviços da aplicação.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(InventoryQuery::class);
        $this->app->singleton(InventoryCache::class);
        $this->app->singleton(MetricsCollector::class, function ($app) {
            /** @var CacheRepository $cache */
            $cache = $app->make(CacheRepository::class);
            $logger = $app->has(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;

            return new MetricsCollector($cache, $logger);
        });

        $this->app->bind(TransactionsContract::class, Transactions::class);
    }

    /**
     * Bootstrap any application services.'
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        // Register route middleware alias for IP restriction if router is available
        if ($this->app->bound('router')) {
            $this->app['router']->aliasMiddleware('restrict.ip', \App\Http\Middleware\RestrictIp::class);
        }
    }
}
