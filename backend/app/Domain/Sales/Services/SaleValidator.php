<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use InvalidArgumentException;

/**
 * Valida itens de venda antes da finalização.
 *
 * Contrato:
 * - Entrada: iterable de arrays ou objetos representando itens (product_id, quantity, unit_price, unit_cost)
 * - Comportamento: lança {@see \InvalidArgumentException} em caso de qualquer violação (IDs inválidos,
 *   quantidades não-positivas, preços/custos negativos ou venda sem itens).
 *
 * Observações:
 * - Aceita tanto coleções Eloquent quanto arrays simples; normaliza internamente para extração de campos.
 */
final class SaleValidator
{
    /**
     * Valida coleção de itens da venda.
     * Aceita arrays ou objetos (ex.: modelos/coleções Eloquent).
     *
     * @param  iterable<array|object>  $items
     *
     * @throws InvalidArgumentException Em caso de validação inválida
     */
    public function validate(iterable $items): void
    {
        $count = 0;

        /** @var array|object $it */
        foreach ($items as $it) {
            $count++;

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
