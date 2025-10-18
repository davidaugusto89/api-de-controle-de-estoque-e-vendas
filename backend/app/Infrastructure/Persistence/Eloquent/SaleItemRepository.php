<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repositório Eloquent para os itens de venda.
 *
 * Responsável por persistir e consultar itens relacionados
 * à agregação de vendas (SaleAggregate).
 */
final class SaleItemRepository
{
    /**
     * Cria um novo item de venda.
     *
     * @param  array{
     *   sale_id: int,
     *   product_id: int,
     *   quantity: int,
     *   unit_price: float|int|string,
     *   unit_cost: float|int|string
     * }  $data
     */
    public function create(array $data): SaleItem
    {
        /** @var SaleItem $item */
        $item = SaleItem::query()->create([
            'sale_id' => $data['sale_id'],
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'unit_cost' => $data['unit_cost'],
        ]);

        return $item;
    }

    /**
     * Busca um item específico de venda.
     */
    public function findById(int $id): ?SaleItem
    {
        return SaleItem::query()->find($id);
    }

    /**
     * Retorna todos os itens de uma venda específica.
     *
     * @return Collection<int, SaleItem>
     */
    public function findBySaleId(int $saleId): Collection
    {
        return SaleItem::query()
            ->where('sale_id', $saleId)
            ->get();
    }

    /**
     * Atualiza um item de venda existente.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool
    {
        return (bool) SaleItem::query()
            ->whereKey($id)
            ->update($data);
    }

    /**
     * Exclui um item de venda.
     */
    public function delete(int $id): ?bool
    {
        $item = $this->findById($id);

        return $item?->delete();
    }

    /**
     * Retorna todos os itens de um conjunto de vendas.
     *
     * @param  array<int>  $saleIds
     * @return Collection<int, SaleItem>
     */
    public function findBySales(array $saleIds): Collection
    {
        return SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->get();
    }
}
