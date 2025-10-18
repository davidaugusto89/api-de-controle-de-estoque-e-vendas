<?php

declare(strict_types=1);

namespace App\Infrastructure\Locks;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Lock as CacheLockContract;
use Illuminate\Contracts\Cache\LockProvider;
use RuntimeException;

final class RedisLock
{
    public function __construct(
        private readonly CacheFactory $cache
    ) {}

    /**
     * Executa um callback enquanto mantém um lock distribuído no Redis.
     *
     * @param  string  $key  Chave do lock
     * @param  int  $ttlSeconds  Tempo de vida (TTL) do lock, em segundos
     * @param  Closure(): mixed  $callback  Callback executado enquanto o lock está ativo
     * @param  int  $waitSeconds  Segundos a aguardar para adquirir o lock antes de falhar
     * @param  int  $sleepMs  Milissegundos de espera entre tentativas de aquisição
     * @return mixed Resultado retornado pelo callback
     *
     * @throws RuntimeException Quando não for possível adquirir o lock no tempo de espera
     */
    public function run(string $key, int $ttlSeconds, Closure $callback, int $waitSeconds = 5, int $sleepMs = 100): mixed
    {
        $store = $this->cache->store();
        /** @var LockProvider $store */
        /** @var CacheLockContract $lock */
        $lock = $store->lock($key, $ttlSeconds);

        if ($lock->block($waitSeconds, $sleepMs)) {
            try {
                return $callback();
            } finally {
                optional($lock)->release();
            }
        }

        throw new RuntimeException("Não foi possível obter lock: {$key}");
    }
}
