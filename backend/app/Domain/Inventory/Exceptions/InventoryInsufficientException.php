<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use DomainException;

final class InventoryInsufficientException extends DomainException
{
    public static function forProduct(int $productId): self
    {
        return new self(sprintf('Estoque insuficiente para o produto %d', $productId));
    }
}
