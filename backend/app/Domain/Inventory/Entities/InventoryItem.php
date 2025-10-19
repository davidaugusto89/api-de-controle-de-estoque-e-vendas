<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Entities;

use App\Domain\Inventory\Services\StockPolicy;

/**
 * Entidade de domínio para representar e manipular o estoque de um produto,
 * aplicando regras de negócio definidas pela {@see StockPolicy}.
 */
final class InventoryItem
{
    private StockPolicy $policy;

    public function __construct(
        public readonly int $productId,
        private int $quantity,
        ?StockPolicy $policy = null
    ) {
        $this->policy   = $policy ?? new StockPolicy;
        $this->quantity = $this->policy->adjust($quantity, 0);
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    /**
     * Reduz a quantidade em estoque.
     *
     *
     * @throws \RuntimeException
     */
    public function decrement(int $qty): void
    {
        $this->quantity = $this->policy->decrease($this->quantity, $qty);
    }

    /**
     * Aumenta a quantidade em estoque.
     */
    public function increment(int $qty): void
    {
        $this->quantity = $this->policy->increase($this->quantity, $qty);
    }
}
