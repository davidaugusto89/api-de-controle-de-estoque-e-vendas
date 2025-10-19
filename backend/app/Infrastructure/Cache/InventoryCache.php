<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Closure;

final class InventoryCache
{
    private const NS = 'inventory';

    /**
     * Key used to store the list version. Kept explicit for backwards compatibility
     * with tests and other code that may reference the exact key name.
     */
    private const VERSION_KEY = 'inventory:list_version';

    /**
     * Aceita um cache-like qualquer para facilitar fakes nos testes.
     */
    public function __construct(
        private readonly mixed $cache
    ) {}

    /**
     * Lista + metadados paginados + totais (cacheados).
     * Retorna: [items(array[]), pageMeta(array), totals(array)]
     */
    public function rememberListAndTotalsPaged(
        ?string $search,
        int $perPage,
        int $page,
        Closure $resolver
    ): array {
        [$qNorm, $ver] = [$this->normalize($search), $this->listVersion()];

        // (no-op)

        $key = $this->key("list:paged:v{$ver}:".md5(json_encode([
            'q' => $qNorm, 'pp' => $perPage, 'p' => $page,
        ], JSON_THROW_ON_ERROR)));

        return $this->remember($key, $resolver);
    }

    /**
     * Lista não paginada + totais (cuidado com payloads grandes).
     * Retorna: [items(array[]), totals(array)]
     */
    public function rememberListAndTotalsUnpaged(
        ?string $search,
        Closure $resolver
    ): array {
        [$qNorm, $ver] = [$this->normalize($search), $this->listVersion()];

        $key = $this->key("list:unpaged:v{$ver}:".md5(json_encode([
            'q' => $qNorm,
        ], JSON_THROW_ON_ERROR)));

        return $this->remember($key, $resolver);
    }

    /**
     * Item por product_id.
     */
    public function rememberItem(int $productId, Closure $resolver): mixed
    {
        $key = $this->key("item:{$productId}");

        return $this->remember($key, $resolver);
    }

    /**
     * Invalida caches relacionados a um conjunto de product_ids + bump de listas.
     */
    public function invalidateByProducts(array $productIds): void
    {
        foreach ($productIds as $pid) {
            $this->cache->forget($this->key('item:'.(int) $pid));
        }
        // Invalida TODAS as listas de uma vez (barato e seguro)
        $this->bumpListVersion();
    }

    /**
     * Invalida todas as listas (por exemplo, após importações em lote).
     */
    public function invalidateAllLists(): void
    {
        $this->bumpListVersion();
    }

    // ---------------------------
    // Internals
    // ---------------------------

    private function normalize(?string $q): string
    {
        $q = $q ?? '';
        $q = trim($q);

        // limitar tamanho para não explodir chaves
        return mb_strtolower(mb_substr($q, 0, 100));
    }

    private function key(string $suffix): string
    {
        return self::NS.':'.$suffix;
    }

    private function listVersion(): int
    {
        /** @var int $v */
        $v = (int) $this->cache->get(self::VERSION_KEY, 1);

        return $v > 0 ? $v : 1;
    }

    private function bumpListVersion(): void
    {
        // Prefer atomic increment when available. Keep operations flat and
        // avoid nested try/catch blocks.
        // Detect real increment method (method_exists) instead of is_callable to
        // avoid treating Mockery mocks (which implement __call) as increment-capable
        // when they don't explicitly implement the method.
        if (method_exists($this->cache, 'increment')) {
            try {
                $this->cache->increment(self::VERSION_KEY);
                // Try to read back the version; if it yields a value we assume
                // increment worked and we can stop.
                try {
                    $val = $this->cache->get(self::VERSION_KEY, 0);
                    if ($val !== null) {
                        return;
                    }
                } catch (\Throwable) {
                    // if get fails, continue to fallback
                }
            } catch (\Throwable) {
                // ignore and fall back
            }
            // If increment didn't produce a readable value, continue to fallback
        }

        // Fallback: read current version and write v+1
        try {
            $current = $this->cache->get(self::VERSION_KEY);
            $current = (int) ($current ?? 1);
            $this->cache->put(self::VERSION_KEY, $current + 1, $this->versionTtlSeconds());
        } catch (\Throwable) {
            // silent by design
        }
    }

    /**
     * Wrapper com suporte opcional a lock (se o store permitir).
     */
    private function remember(string $key, Closure $resolver): mixed
    {
        $ttl = $this->ttlSeconds();

        // If store provides remember(), use it and fallback to resolver on error
        if (is_callable([$this->cache, 'remember'])) {
            try {
                return $this->cache->remember($key, $ttl, $resolver);
            } catch (\Throwable) {
                return $resolver();
            }
        }

        // Emulate remember: try get, run resolver, then put. All steps are
        // independent and failures are non-fatal.
        try {
            $existing = $this->cache->get($key);
            if ($existing !== null) {
                return $existing;
            }
        } catch (\Throwable) {
            // ignore
        }

        $value = $resolver();

        try {
            $this->cache->put($key, $value, $ttl);
        } catch (\Throwable) {
            // ignore
        }

        return $value;
    }

    /**
     * Item TTL (seconds) — configurable via `config/inventory.php`.
     */
    private function ttlSeconds(): int
    {
        try {
            if (function_exists('config')) {
                return (int) config('inventory.cache.item_ttl', 60);
            }
        } catch (\Throwable) {
            // fall through to default
        }

        return 60;
    }

    /**
     * Version key TTL (seconds) used only by fallback path when store doesn't
     * implement atomic increment + put. Should be large (e.g. 24h) so versions
     * persist in fallback stores.
     */
    private function versionTtlSeconds(): int
    {
        try {
            if (function_exists('config')) {
                return (int) config('inventory.cache.version_ttl', 60);
            }
        } catch (\Throwable) {
            // fall through to default
        }

        return 60;
    }
}
