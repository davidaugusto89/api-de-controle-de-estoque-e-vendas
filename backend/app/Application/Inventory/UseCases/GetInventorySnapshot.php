<?php

declare(strict_types=1);

namespace App\Application\Inventory\UseCases;

use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Persistence\Queries\InventoryQuery;

/**
 * Caso de uso para recuperar um snapshot do inventário.
 *
 * Utiliza caching através de {@see InventoryCache} para evitar consultas repetidas
 * e delega a recuperação dos dados para {@see InventoryQuery} quando necessário.
 */
final class GetInventorySnapshot
{
    /**
     * @param  InventoryQuery  $query  Query de persistência responsável por construir o snapshot
     * @param  InventoryCache  $cache  Cache responsável por armazenar/lembrar o snapshot
     */
    public function __construct(
        private readonly InventoryQuery $query,
        private readonly InventoryCache $cache,
    ) {}

    /**
     * Retorna o snapshot atual do inventário.
     *
     * O snapshot é obtido a partir do cache; caso não exista, realiza-se a consulta
     * via {@see InventoryQuery} e armazena-se o resultado.
     *
     * Formato de retorno esperado:
     * - items: lista de itens de inventário; cada item é um array associativo com chaves
     *          como `product_id`, `sku`, `name`, `quantity`, `stock_cost_value`, `stock_sale_value`, etc.
     * - totals: array com totais agregados (`total_cost`, `total_sale`, `projected_profit`).
     *
     * @return array{items: array<int, array<string, mixed>>, totals: array{total_cost: float, total_sale: float, projected_profit: float}}
     */
    public function handle(): array
    {
        [$items, $totals] = $this->cache->rememberListAndTotalsUnpaged(null, function () {
            $items = $this->query->list(null);

            // Convertemos a coleção para array puro para armazenamento/serialização consistente
            return [$items->all(), $this->query->totals(null)];
        });

        return [
            'items' => $items,
            'totals' => $totals,
        ];
    }
}
