<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Entities;

use App\Domain\Inventory\Services\StockPolicy;

/**
 * Entidade de domínio (não-ORM) para raciocinar sobre estoque.
 */
final class InventoryItem
{
    public function __construct(
        public readonly int $productId,
        private int $quantity
    ) {
        (new StockPolicy)->assertNonNegative($quantity);
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function decrement(int $qty): void
    {
        $policy = new StockPolicy;

        if (! $policy->canDecrement($this->quantity, $qty)) {
            throw new \RuntimeException('Estoque insuficiente.');
        }

        $this->quantity -= $qty;
    }

    public function increment(int $qty): void
    {
        (new StockPolicy)->assertNonNegative($qty);
        $this->quantity += $qty;
    }
}
