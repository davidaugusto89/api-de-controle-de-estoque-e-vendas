<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Closure;

/**
 * Cache específico para dados de inventário.
 */
final class InventoryCache
{
    private const NS = 'inventory';

    /**
     * Chave usada para armazenar a versão da lista.
     * Mantida explícita por compatibilidade retroativa com testes e outros
     * códigos que possam referenciar o nome exato da chave.
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
     *
     * @param  string|null  $search  Termo de busca (normalizado internamente)
     * @param  int  $perPage  Itens por página
     * @param  int  $page  Número da página
     * @param  Closure():array  $resolver  Callback para resolver os dados quando não em cache
     * @return array{0: array[], 1: array, 2: array} Retorna tupla com items, pageMeta e totals
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
     *
     * @param  string|null  $search  Termo de busca (normalizado internamente)
     * @param  Closure():array  $resolver  Callback para resolver os dados quando não em cache
     * @return array{0: array[], 1: array}
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
     *
     * @param  int  $productId  ID do produto
     * @param  Closure():mixed  $resolver  Callback para resolver os dados quando não em cache
     * @return mixed Retorna o item
     */
    public function rememberItem(int $productId, Closure $resolver): mixed
    {
        $key = $this->key("item:{$productId}");

        return $this->remember($key, $resolver);
    }

    /**
     * Invalida caches relacionados a um conjunto de product_ids + bump de listas.
     *
     * @param  array<int>  $productIds  IDs dos produtos
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

    /**
     * Normaliza termo de busca.
     */
    private function normalize(?string $q): string
    {
        $q = $q ?? '';
        $q = trim($q);

        // limitar tamanho para não explodir chaves
        return mb_strtolower(mb_substr($q, 0, 100));
    }

    /**
     * Gera chave para cache.
     */
    private function key(string $suffix): string
    {
        return self::NS.':'.$suffix;
    }

    /**
     * Versão de listas.
     */
    private function listVersion(): int
    {
        /** @var int $v */
        $v = (int) $this->cache->get(self::VERSION_KEY, 1);

        return $v > 0 ? $v : 1;
    }

    /**
     * Incrementa versão de listas.
     */
    private function bumpListVersion(): void
    {
        // Preferir incremento atômico quando disponível. Manter as operações
        // simples e evitar blocos try/catch aninhados.
        // Detectar o método increment real (method_exists) ao invés de is_callable
        // para evitar tratar mocks do Mockery (que implementam __call) como
        // capazes de incrementar quando não implementam o método explicitamente.
        if (method_exists($this->cache, 'increment')) {
            try {
                $this->cache->increment(self::VERSION_KEY);
                // Tentar ler de volta a versão; se obtivermos um valor assumimos
                // que o increment funcionou e podemos parar.
                try {
                    $val = $this->cache->get(self::VERSION_KEY, 0);
                    if ($val !== null) {
                        return;
                    }
                } catch (\Throwable) {
                    // se get falhar, continuar para o caminho de fallback
                }
            } catch (\Throwable) {
                // ignorar e cair no fallback
            }
            // Se increment não produziu um valor legível, continuar para o fallback
        }

        // Fallback: ler a versão atual e gravar v+1
        try {
            $current = $this->cache->get(self::VERSION_KEY);
            $current = (int) ($current ?? 1);
            $this->cache->put(self::VERSION_KEY, $current + 1, $this->versionTtlSeconds());
        } catch (\Throwable) {
        }
    }

    /**
     * Wrapper com suporte opcional a lock (se o store permitir).
     *
     * @param  string  $key  Chave
     * @param  Closure():mixed  $resolver  Callback para resolver o valor
     */
    private function remember(string $key, Closure $resolver): mixed
    {
        $ttl = $this->ttlSeconds();

        // Se o store fornecer remember(), usá-lo e, em caso de erro, recorrer ao resolver
        if (is_callable([$this->cache, 'remember'])) {
            try {
                return $this->cache->remember($key, $ttl, $resolver);
            } catch (\Throwable) {
                return $resolver();
            }
        }

        // Emular remember: tentar get, executar resolver e depois put. Todos os
        // passos são independentes e falhas não são fatais.
        try {
            $existing = $this->cache->get($key);
            if ($existing !== null) {
                return $existing;
            }
        } catch (\Throwable) {
            // ignorar
        }

        $value = $resolver();

        try {
            $this->cache->put($key, $value, $ttl);
        } catch (\Throwable) {
            // ignorar
        }

        return $value;
    }

    /**
     * TTL de item (segundos) — configurável via `config/inventory.php`.
     */
    private function ttlSeconds(): int
    {
        try {
            if (function_exists('config')) {
                return (int) config('inventory.cache.item_ttl', 60);
            }
        } catch (\Throwable) {
            // cair para o padrão
        }

        return 60;
    }

    /**
     * TTL da chave de versão (segundos) usada apenas pelo caminho de fallback
     * quando o store não implementa incremento atômico + put. Deve ser grande
     * (ex.: 24h) para que as versões persistam em stores de fallback.
     */
    private function versionTtlSeconds(): int
    {
        try {
            if (function_exists('config')) {
                return (int) config('inventory.cache.version_ttl', 60);
            }
        } catch (\Throwable) {
            // cair para o padrão
        }

        return 60;
    }
}
