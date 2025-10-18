<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Entities;

use App\Domain\Inventory\Services\StockPolicy;

/**
 * Entidade de domínio imutável-parcial para raciocinar sobre o estoque de um produto.
 *
 * Responsabilidades:
 * - Representar a quantidade em estoque de um produto dentro do domínio,
 *   aplicando as regras de {@see App\Domain\Inventory\Services\StockPolicy}.
 * - Fornecer operações de incremento/decremento que validam e lançam erro em
 *   situações inválidas (p.ex. decremento maior que o disponível).
 */
final class InventoryItem
{
    private StockPolicy $policy;

    public function __construct(
        public readonly int $productId,
        private int $quantity,
        ?StockPolicy $policy = null
    ) {
        $this->policy = $policy ?? new StockPolicy();

        // normalize / valida quantidade inicial via StockPolicy público
        $this->quantity = $this->policy->adjust($quantity, 0);
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    /**
     * Decrementa a quantidade em estoque.
     *
     * @param  int  $qty  Quantidade a subtrair (deve ser positiva)
     *
     * @throws \RuntimeException Quando não houver estoque suficiente conforme regras de {@see StockPolicy}
     */
    public function decrement(int $qty): void
    {
        $this->quantity = $this->policy->decrease($this->quantity, $qty);
    }

    public function increment(int $qty): void
    {
        /**
         * Incrementa a quantidade em estoque.
         *
         * @param  int  $qty  Quantidade a adicionar (deve ser não-negativa)
         */
        $this->quantity = $this->policy->increase($this->quantity, $qty);
    }
}
