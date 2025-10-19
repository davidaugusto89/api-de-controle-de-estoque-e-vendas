<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ObservabilityController
{
    /**
     * Exposes basic counters stored under cache keys `metrics:*` in a simple
     * Prometheus text format. This is intentionally minimal — for production
     * integrate a proper metrics exporter.
     */
    public function metrics(Request $request): Response
    {
        $cache = app()->make('cache.store');

        // Collect all known metric keys from config — keep a whitelist to avoid
        // scanning the whole cache store.
        $keys = config('observability.metrics_whitelist', [
            'inventory.job.start',
            'inventory.job.completed',
            'inventory.job.failed',
            'inventory.item.decrement',
            'inventory.item.failure',
            'inventory.cache.invalidated',
            'cache.inventory.item.hits',
            'cache.inventory.item.misses',
        ]);

        $lines = [];

        foreach ($keys as $k) {
            $v = (int) $cache->get('metrics:'.$k, 0);
            // Prometheus metric name: replace dots with underscores
            $name    = 'app_'.str_replace('.', '_', $k);
            $lines[] = "# TYPE {$name} counter";
            $lines[] = "{$name} {$v}";
        }

        $body = implode("\n", $lines)."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }
}
