<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Traits;

use App\Support\Traits\WithCacheInvalidation;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class WithCacheInvalidationTest extends TestCase
{
    public function test_invalidar_cache_de_vendas_chama_tags_e_flush(): void
    {
        // Expect tags(...)->flush() called for sales and reports
        Cache::shouldReceive('tags')->with(['sales'])->once()->andReturnSelf();
        Cache::shouldReceive('tags')->with(['reports'])->once()->andReturnSelf();
        Cache::shouldReceive('flush')->twice()->andReturnTrue();

        $obj = new class
        {
            use WithCacheInvalidation;

            public function callBustSalesCaches(): void
            {
                $this->bustSalesCaches();
            }
        };
        $obj->callBustSalesCaches();
    }

    public function test_invalidar_cache_de_inventario_chama_tags_e_flush(): void
    {
        Cache::shouldReceive('tags')->with(['inventory'])->once()->andReturnSelf();
        Cache::shouldReceive('tags')->with(['products'])->once()->andReturnSelf();
        Cache::shouldReceive('flush')->twice()->andReturnTrue();

        $obj = new class
        {
            use WithCacheInvalidation;

            public function callBustInventoryCaches(): void
            {
                $this->bustInventoryCaches();
            }
        };
        $obj->callBustInventoryCaches();
    }
}
