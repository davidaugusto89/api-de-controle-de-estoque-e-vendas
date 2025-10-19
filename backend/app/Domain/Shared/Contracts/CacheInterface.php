<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Contrato genérico para abstração de cache no domínio.
 */
interface CacheInterface
{
    /**
     * Retorna o valor do cache ou executa o callback e armazena o resultado.
     *
     * @param  string  $key  Chave de cache
     * @param  int  $ttl  Tempo de vida em segundos
     * @param  callable  $callback  Função a ser executada se o cache não existir
     * @return mixed Valor em cache ou resultado do callback
     */
    public function remember(string $key, int $ttl, callable $callback): mixed;

    /**
     * Remove uma chave do cache.
     *
     * @param  string  $key  Chave de cache
     * @return bool True se a chave foi removida, false caso contrário.
     */
    public function forget(string $key): bool;
}
