<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Controlador para endpoints de observabilidade.
 */
final class ObservabilityController
{
    /**
     * Exibe métricas no formato Prometheus.
     *
     * @param  Request  $request  Requisição HTTP atual
     * @return Response Resposta HTTP com métricas no formato Prometheus
     */
    public function metrics(Request $request): Response
    {
        $cache = app()->make('cache.store');

        // Lista branca (whitelist) de chaves de métricas
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
            $name = 'app_'.str_replace('.', '_', $k);
            $lines[] = "# TYPE {$name} counter";
            $lines[] = "{$name} {$v}";
        }

        $body = implode("\n", $lines)."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }
}
