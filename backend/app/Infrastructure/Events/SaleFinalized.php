<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

/**
 * Evento disparado quando uma venda é finalizada.
 */
final class SaleFinalized
{
    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function __construct(
        public readonly int $saleId,
        public readonly array $items
    ) {}
}
