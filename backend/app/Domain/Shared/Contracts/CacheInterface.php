<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

interface CacheInterface
{
    public function remember(string $key, int $ttl, callable $callback): mixed;

    public function forget(string $key): bool;
}
