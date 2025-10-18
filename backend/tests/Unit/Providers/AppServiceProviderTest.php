<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Persistence\Queries\InventoryQuery;
use Tests\TestCase;

final class AppServiceProviderTest extends TestCase
{
    public function test_bindings_de_inventario_sao_registrados_como_singletons(): void
    {
        $firstQuery  = $this->app->make(InventoryQuery::class);
        $secondQuery = $this->app->make(InventoryQuery::class);

        $this->assertInstanceOf(InventoryQuery::class, $firstQuery);
        $this->assertSame($firstQuery, $secondQuery, 'InventoryQuery should be registered as a singleton');

        $firstCache  = $this->app->make(InventoryCache::class);
        $secondCache = $this->app->make(InventoryCache::class);

        $this->assertInstanceOf(InventoryCache::class, $firstCache);
        $this->assertSame($firstCache, $secondCache, 'InventoryCache should be registered as a singleton');
    }
}
