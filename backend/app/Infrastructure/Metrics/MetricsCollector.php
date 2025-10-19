<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;

/**
 * Coletor de métricas minimalista para contadores e gauges.
 * Implementação simples para ambientes sem Prometheus/Sentry.
 */
final class MetricsCollector
{
    private readonly CacheRepository $cache;

    private readonly LoggerInterface $logger;

    public function __construct(CacheRepository $cache, ?LoggerInterface $logger = null)
    {
        $this->cache  = $cache;
        $this->logger = $logger ?? new \Psr\Log\NullLogger;
    }

    public function increment(string $key, int $by = 1): void
    {
        try {
            if (method_exists($this->cache, 'increment')) {
                $this->cache->increment("metrics:{$key}", $by);
            } else {
                $current = (int) $this->cache->get("metrics:{$key}", 0);
                $this->cache->put("metrics:{$key}", $current + $by, 60 * 60 * 24);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Metrics increment failed', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }

    public function gauge(string $key, int|float $value): void
    {
        try {
            $this->cache->put("metrics:{$key}", $value, 60 * 60 * 24);
        } catch (\Throwable $e) {
            $this->logger->warning('Metrics gauge failed', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }

    public function get(string $key): int|float|null
    {
        return $this->cache->get("metrics:{$key}");
    }
}
