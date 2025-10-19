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
        /**
         * Cenário
         * Dado: necessidade de invalidar caches de vendas e reports
         * Quando: bustSalesCaches é chamado
         * Então: tags(...)->flush() é chamado para sales e reports
         */
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
        /**
         * Cenário
         * Dado: necessidade de invalidar caches de inventário e produtos
         * Quando: bustInventoryCaches é chamado
         * Então: tags(...)->flush() é chamado para inventory e products
         */
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
