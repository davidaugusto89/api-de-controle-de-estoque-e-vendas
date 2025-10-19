<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Closure;

final class InventoryCache
{
    private const TTL_SECONDS = 60;

    private const NS = 'inventory';

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
        // usando increment para evitar colisões entre processos — tolerate stores que não implementam increment
        try {
            if (method_exists($this->cache, 'increment')) {
                $this->cache->increment(self::VERSION_KEY);
            } else {
                // fallback: ler versão atual e setar v+1 para preservar monotonicidade
                $current = (int) ($this->cache->get(self::VERSION_KEY) ?? 1);
                $this->cache->put(self::VERSION_KEY, $current + 1, self::TTL_SECONDS);
            }
        } catch (\Throwable) {
            try {
                $current = (int) ($this->cache->get(self::VERSION_KEY) ?? 1);
                $this->cache->put(self::VERSION_KEY, $current + 1, self::TTL_SECONDS);
            } catch (\Throwable) {
                // silencioso
            }
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

        try {
            return $this->cache->remember($key, self::TTL_SECONDS, $resolver);
        } catch (\Throwable) {
            return $resolver();
        }
    }
}
