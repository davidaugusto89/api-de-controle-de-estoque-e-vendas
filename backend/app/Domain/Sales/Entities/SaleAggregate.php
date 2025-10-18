<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

/**
 * Representa o agregado de uma Venda em memória (somatória de itens).
 */
final class SaleAggregate
{
    /** @var array<int, array{product_id:int, quantity:int, unit_price:float, unit_cost:float}> */
    private array $items = [];

    public function addItem(int $productId, int $qty, float $price, float $cost): void
    {
        $this->items[] = [
            'product_id' => $productId,
            'quantity' => $qty,
            'unit_price' => $price,
            'unit_cost' => $cost,
        ];
    }

    public function totalAmount(): float
    {
        return array_reduce($this->items, fn ($c, $i) => $c + ($i['quantity'] * $i['unit_price']), 0.0);
    }

    public function totalCost(): float
    {
        return array_reduce($this->items, fn ($c, $i) => $c + ($i['quantity'] * $i['unit_cost']), 0.0);
    }

    public function totalProfit(): float
    {
        return $this->totalAmount() - $this->totalCost();
    }

    /**
     * @return array<int, array{product_id:int, quantity:int, unit_price:float, unit_cost:float}>
     */
    public function items(): array
    {
        return $this->items;
    }
}
