<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

/**
 * Agregado de domínio que representa uma venda em memória (soma de itens).
 */
final class SaleAggregate
{
    /** @var array<int, array{product_id:int, quantity:int, unit_price:float, unit_cost:float}> */
    private array $items = [];

    /**
     * Adiciona um item ao agregado.
     */
    public function addItem(int $productId, int $qty, float $price, float $cost): void
    {
        $this->items[] = [
            'product_id' => $productId,
            'quantity'   => $qty,
            'unit_price' => $price,
            'unit_cost'  => $cost,
        ];
    }

    /**
     * Retorna o valor total da venda.
     */
    public function totalAmount(): float
    {
        return array_reduce(
            $this->items,
            static fn (float $carry, array $item): float => $carry + ($item['quantity'] * $item['unit_price']),
            0.0
        );
    }

    /**
     * Retorna o custo total da venda.
     */
    public function totalCost(): float
    {
        return array_reduce(
            $this->items,
            static fn (float $carry, array $item): float => $carry + ($item['quantity'] * $item['unit_cost']),
            0.0
        );
    }

    /**
     * Retorna o lucro total da venda.
     */
    public function totalProfit(): float
    {
        return $this->totalAmount() - $this->totalCost();
    }

    /**
     * Retorna todos os itens do agregado.
     *
     * @return array<int, array{product_id:int, quantity:int, unit_price:float, unit_cost:float}>
     */
    public function items(): array
    {
        return $this->items;
    }
}
