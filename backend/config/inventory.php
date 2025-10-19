<?php

declare(strict_types=1);

return [
    'cache' => [
        // TTL (segundos) para itens individuais de inventário (rememberItem)
        // - Manter pequeno para permitir leituras relativamente frescas. Padrão: 60s
        'item_ttl' => env('INVENTORY_CACHE_ITEM_TTL', 60),

        // TTL para a chave de versão de fallback quando increment não está
        // disponível no store
        // - Deve ser grande para evitar resets acidentais em stores sem incremento atômico.
        'version_ttl' => env('INVENTORY_CACHE_VERSION_TTL', 86400),
    ],
];
