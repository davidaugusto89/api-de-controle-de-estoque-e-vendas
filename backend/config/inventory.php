<?php

declare(strict_types=1);

return [
    'cache' => [
        // TTL (seconds) for individual inventory items (rememberItem)
        // - Keep small to allow relatively fresh item reads. Default: 60s
        'item_ttl' => env('INVENTORY_CACHE_ITEM_TTL', 60),

        // TTL for fallback version key when increment is not available on the store
        // - Should be large to avoid accidental resets in stores without atomic increment.
        'version_ttl' => env('INVENTORY_CACHE_VERSION_TTL', 86400),
    ],
];
