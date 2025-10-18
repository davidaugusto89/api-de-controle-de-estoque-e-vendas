<?php

declare(strict_types=1);

namespace App\Support\Traits;

use Illuminate\Support\Facades\Cache;

trait WithCacheInvalidation
{
    /**
     * Invalida tags comuns do domínio quando dados de vendas mudam.
     */
    protected function bustSalesCaches(): void
    {
        Cache::tags(['sales'])->flush();
        Cache::tags(['reports'])->flush();
    }

    /**
     * Invalida caches ligados a inventário/produtos.
     */
    protected function bustInventoryCaches(): void
    {
        Cache::tags(['inventory'])->flush();
        Cache::tags(['products'])->flush();
    }
}
