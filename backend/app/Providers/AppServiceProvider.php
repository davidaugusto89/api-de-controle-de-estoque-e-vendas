<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Persistence\Queries\InventoryQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
    }

    /**
     * Bootstrap any application services.'
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        // Blueprint::macro('checkConstraint', function (string $expression, ?string $name = null) {
        //     /** @var \Illuminate\Database\Schema\Blueprint $this */
        //     $table = $this->getTable();
        //     $constraint = $name ?? 'chk_'.substr(md5($table.$expression), 0, 8);

        //     DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$expression})");
        // });
    }
}
