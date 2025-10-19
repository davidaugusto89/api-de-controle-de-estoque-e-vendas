<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use DomainException;

/**
 * Exceção lançada quando uma operação tenta reduzir o estoque
 * além da quantidade disponível.
 */
final class InventoryInsufficientException extends DomainException
{
    /**
     * Cria uma exceção para o produto com estoque insuficiente.
     *
     * @param  int  $productId  O ID do produto com estoque insuficiente.
     * @return self A instância da exceção.
     */
    public static function forProduct(int $productId): self
    {
        return new self(sprintf('Estoque insuficiente para o produto %d', $productId));
    }
}
