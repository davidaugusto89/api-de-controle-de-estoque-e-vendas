<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class InventoryCache
{
    private const TTL_SECONDS = 60;

    private const NS = 'inventory';

    private const VERSION_KEY = 'inventory:list_version';

    public function __construct(
        private readonly CacheRepository $cache
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
        // usando increment para evitar colisões entre processos
        $this->cache->increment(self::VERSION_KEY);
        // se o store não suportar increment, caia para set:
        if ((int) $this->cache->get(self::VERSION_KEY, 0) === 0) {
            $this->cache->put(self::VERSION_KEY, 2, self::TTL_SECONDS);
        }
    }

    /**
     * Wrapper com suporte opcional a lock (se o store permitir).
     */
    private function remember(string $key, Closure $resolver): mixed
    {
        // Store pode não suportar locks (ex.: file). Fallback para remember simples.
        if (method_exists($this->cache, 'lock')) {
            try {
                $lock = $this->cache->lock($key.':lock', 5);

                return $this->cache->remember($key, self::TTL_SECONDS, function () use ($resolver, $lock) {
                    try {
                        return $resolver();
                    } finally {
                        optional($lock)->release();
                    }
                });
            } catch (\Throwable) {
                // fallback silencioso
            }
        }

        return $this->cache->remember($key, self::TTL_SECONDS, $resolver);
    }
}
