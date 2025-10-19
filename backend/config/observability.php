<?php

declare(strict_types=1);

return [
    // Lista branca (whitelist) de chaves de métricas (sem o prefixo metrics:) a expor em /metrics
    'metrics_whitelist' => env('OBS_METRICS_WHITELIST') ? explode(',', env('OBS_METRICS_WHITELIST')) : [
        'inventory.job.start',
        'inventory.job.completed',
        'inventory.job.failed',
        'inventory.item.decrement',
        'inventory.item.failure',
        'inventory.cache.invalidated',
        'cache.inventory.item.hits',
        'cache.inventory.item.misses',
    ],
    // IPs permitidos a acessar o endpoint /metrics. Array vazio desabilita restrição (dev).
    'allowed_ips' => env('OBS_ALLOWED_IPS') ? explode(',', env('OBS_ALLOWED_IPS')) : [],
];
