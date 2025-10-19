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
    /** @var (callable():mixed)|null */
    private $queryResolver;

    /**
     * Injeta um resolver opcional para SaleItem::query() (facilita testes).
     *
     * @param  callable():mixed|null  $resolver
     */
    public function setQueryResolver(?callable $resolver): void
    {
        $this->queryResolver = $resolver;
    }

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
        $qb = $this->queryResolver ? ($this->queryResolver)() : SaleItem::query();

        $item = $qb->create([
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
     *
     * @param  int  $id  ID do item de venda.
     */
    public function findById(int $id): ?SaleItem
    {
        $qb = $this->queryResolver ? ($this->queryResolver)() : SaleItem::query();

        return $qb->find($id);
    }

    /**
     * Retorna todos os itens de uma venda específica.
     *
     * @param  int  $saleId  ID da venda
     * @return Collection<int, SaleItem>
     */
    public function findBySaleId(int $saleId): Collection
    {
        $qb = $this->queryResolver ? ($this->queryResolver)() : SaleItem::query();

        return $qb->where('sale_id', $saleId)->get();
    }

    /**
     * Atualiza um item de venda existente.
     *
     * @param  int  $id  ID do item de venda.
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool
    {
        $qb = $this->queryResolver ? ($this->queryResolver)() : SaleItem::query();

        return (bool) $qb->whereKey($id)->update($data);
    }

    /**
     * Exclui um item de venda.
     *
     * @param  int  $id  ID do item de venda.
     */
    public function delete(int $id): ?bool
    {
        $item = $this->findById($id);

        return $item?->delete();
    }

    /**
     * Retorna todos os itens de um conjunto de vendas.
     *
     * @param  array<int>  $saleIds  IDs das vendas
     * @return Collection<int, SaleItem>
     */
    public function findBySales(array $saleIds): Collection
    {
        $qb = $this->queryResolver ? ($this->queryResolver)() : SaleItem::query();

        return $qb->whereIn('sale_id', $saleIds)->get();
    }
}
