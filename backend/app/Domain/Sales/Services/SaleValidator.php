<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use InvalidArgumentException;

/**
 * Valida os itens de uma venda garantindo integridade de dados antes da finalização.
 */
final class SaleValidator
{
    /**
     * Verifica se os itens da venda são válidos.
     *
     * @param  iterable<array|object>  $items
     *
     * @throws InvalidArgumentException
     */
    public function validate(iterable $items): void
    {
        $count = 0;

        foreach ($items as $it) {
            $count++;

            $productId = is_array($it) ? (int) ($it['product_id'] ?? 0) : (int) ($it->product_id ?? 0);
            $qty = is_array($it) ? (int) ($it['quantity'] ?? 0) : (int) ($it->quantity ?? 0);
            $price = is_array($it) ? (float) ($it['unit_price'] ?? 0) : (float) ($it->unit_price ?? 0);
            $cost = is_array($it) ? (float) ($it['unit_cost'] ?? 0) : (float) ($it->unit_cost ?? 0);

            if ($productId <= 0) {
                throw new InvalidArgumentException('Item inválido: product_id ausente ou inválido.');
            }

            if ($qty <= 0) {
                throw new InvalidArgumentException("Item {$productId}: quantity deve ser maior que 0.");
            }

            if ($price < 0) {
                throw new InvalidArgumentException("Item {$productId}: unit_price não pode ser negativo.");
            }

            if ($cost < 0) {
                throw new InvalidArgumentException("Item {$productId}: unit_cost não pode ser negativo.");
            }
        }

        if ($count === 0) {
            throw new InvalidArgumentException('A venda deve conter pelo menos um item.');
        }
    }
}
