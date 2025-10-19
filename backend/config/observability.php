<?php

declare(strict_types=1);

return [
    // Whitelist of metric keys (without the metrics: prefix) to expose on /metrics
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
    // IPs allowed to access the /metrics endpoint. Empty array disables restriction (dev).
    'allowed_ips' => env('OBS_ALLOWED_IPS') ? explode(',', env('OBS_ALLOWED_IPS')) : [],
];
