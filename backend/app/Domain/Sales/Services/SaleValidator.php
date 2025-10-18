<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use InvalidArgumentException;

/**
 * Valida itens da venda antes da finalização.
 * Lança InvalidArgumentException em caso de falha.
 */
final class SaleValidator
{
    /**
     * @param  iterable<array|object>  $items  (Eloquent\Collection, array de arrays, etc.)
     */
    public function validate(iterable $items): void
    {
        $count = 0;

        /** @var array|object $it */
        foreach ($items as $it) {
            $count++;

            // suporte a array ou objeto Eloquent
            $productId = is_array($it) ? (int) ($it['product_id'] ?? 0) : (int) ($it->product_id ?? 0);
            $qty = is_array($it) ? (int) ($it['quantity'] ?? 0) : (int) ($it->quantity ?? 0);
            $price = is_array($it) ? (float) ($it['unit_price'] ?? 0) : (float) ($it->unit_price ?? 0);
            $cost = is_array($it) ? (float) ($it['unit_cost'] ?? 0) : (float) ($it->unit_cost ?? 0);

            if ($productId <= 0) {
                throw new InvalidArgumentException('Item inválido: product_id ausente/ inválido.');
            }
            if ($qty <= 0) {
                throw new InvalidArgumentException("Item {$productId}: quantity deve ser > 0.");
            }
            if ($price < 0) {
                throw new InvalidArgumentException("Item {$productId}: unit_price não pode ser negativo.");
            }
            if ($cost < 0) {
                throw new InvalidArgumentException("Item {$productId}: unit_cost não pode ser negativo.");
            }
        }

        if ($count === 0) {
            throw new InvalidArgumentException('A venda deve conter ao menos um item.');
        }
    }
}
