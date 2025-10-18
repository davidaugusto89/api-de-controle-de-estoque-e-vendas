<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Infrastructure\Locks\RedisLock;
use Closure;

final class InventoryLockService
{
    public function __construct(
        private readonly RedisLock $lock
    ) {}

    /**
     * Executa um callback protegido por lock do produto.
     */
    public function lock(int $productId, Closure $callback, int $ttlSeconds = 10, int $waitSeconds = 5): mixed
    {
        $key = $this->keyForProduct($productId);

        return $this->lock->run($key, $ttlSeconds, $callback, $waitSeconds);
    }

    /**
     * Lock em múltiplos produtos (ordenação determinística evita deadlock).
     */
    public function lockMany(array $productIds, Closure $callback, int $ttlSeconds = 15, int $waitSeconds = 8): mixed
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        sort($ids); // ordem fixa

        $keys = array_map(fn (int $id) => $this->keyForProduct($id), $ids);

        $runner = function (array $k, Closure $cb) use (&$runner, $ttlSeconds, $waitSeconds) {
            if (empty($k)) {
                return $cb();
            }
            $key = array_shift($k);

            return $this->lock->run($key, $ttlSeconds, fn () => $runner($k, $cb), $waitSeconds);
        };

        return $runner($keys, $callback);
    }

    private function keyForProduct(int $productId): string
    {
        return "lock:inventory:product:{$productId}";
    }
}
