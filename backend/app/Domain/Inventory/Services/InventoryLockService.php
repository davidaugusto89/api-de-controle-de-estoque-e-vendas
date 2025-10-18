<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Infrastructure\Locks\RedisLock;
use Closure;

/**
 * Fornece locks de inventário por produto (individual e múltiplo).
 */
class InventoryLockService
{
    public function __construct(
        private readonly ?RedisLock $lock = null
    ) {}

    /**
     * Executa um callback sob lock para um produto.
     *
     * @param  Closure():mixed  $callback
     */
    public function lock(int $productId, Closure $callback, int $ttlSeconds = 10, int $waitSeconds = 5): mixed
    {
        if ($this->lock === null) {
            throw new \RuntimeException('RedisLock is not configured for InventoryLockService');
        }

        return $this->lock->run(
            $this->keyForProduct($productId),
            $ttlSeconds,
            $callback,
            $waitSeconds
        );
    }

    /**
     * Executa um callback sob locks para vários produtos (ordem determinística).
     *
     * @param  int[]  $productIds
     * @param  Closure():mixed  $callback
     */
    public function lockMany(array $productIds, Closure $callback, int $ttlSeconds = 15, int $waitSeconds = 8): mixed
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        sort($ids);

        $keys = array_map(fn (int $id): string => $this->keyForProduct($id), $ids);

        if ($this->lock === null) {
            throw new \RuntimeException('RedisLock is not configured for InventoryLockService');
        }

        $runner = function (array $k, Closure $cb) use (&$runner, $ttlSeconds, $waitSeconds) {
            if ($k === []) {
                return $cb();
            }

            $key = array_shift($k);

            return $this->lock->run(
                $key,
                $ttlSeconds,
                fn () => $runner($k, $cb),
                $waitSeconds
            );
        };

        return $runner($keys, $callback);
    }

    private function keyForProduct(int $productId): string
    {
        return "lock:inventory:product:{$productId}";
    }
}
