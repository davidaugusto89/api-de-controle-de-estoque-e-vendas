<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Persistence\Queries\InventoryQuery;
use App\Support\Database\Contracts\Transactions as TransactionsContract;
use App\Support\Database\Transactions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(InventoryQuery::class);
        $this->app->singleton(InventoryCache::class);

        $this->app->bind(TransactionsContract::class, Transactions::class);
    }

    /**
     * Bootstrap any application services.'
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
    }
}
