<?php

declare(strict_types=1);

namespace App\Application\Inventory\UseCases;

use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Persistence\Queries\InventoryQuery;

/**
 * Obtém um snapshot do inventário (itens e totais) com cache.
 */
final class GetInventorySnapshot
{
    public function __construct(
        private readonly InventoryQuery $query,
        private readonly InventoryCache $cache,
    ) {}

    /**
     * Retorna o snapshot atual do inventário a partir do cache
     * ou gera e armazena quando ausente.
     *
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   totals: array{total_cost: float, total_sale: float, projected_profit: float}
     * }
     */
    public function handle(): array
    {
        [$items, $totals] = $this->cache->rememberListAndTotalsUnpaged(null, function () {
            $items = $this->query->list(null);

            return [$items->all(), $this->query->totals(null)];
        });

        return [
            'items' => $items,
            'totals' => $totals,
        ];
    }
}
