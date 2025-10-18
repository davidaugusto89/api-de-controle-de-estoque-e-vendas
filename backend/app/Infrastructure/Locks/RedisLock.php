<?php

declare(strict_types=1);

namespace App\Infrastructure\Locks;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use RuntimeException;

final class RedisLock
{
    public function __construct(
        private readonly CacheFactory $cache
    ) {}

    public function run(string $key, int $ttlSeconds, Closure $callback, int $waitSeconds = 5, int $sleepMs = 100): mixed
    {
        $lock = $this->cache->store()->lock($key, $ttlSeconds);

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
