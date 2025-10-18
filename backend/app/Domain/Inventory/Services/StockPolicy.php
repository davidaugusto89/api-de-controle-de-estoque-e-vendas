<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * StockPolicy
 *
 * Regras centrais de integridade de estoque.
 * - Garante quantidades inteiras e não-negativas.
 * - Impede underflow/overflow.
 * - Expõe operações de aumento/diminuição usadas por Inventory e Sales.
 */
final class StockPolicy
{
    /**
     * Limite superior de segurança por produto.
     * Ajuste via env se quiser: STOCK_MAX_PER_PRODUCT
     */
    private int $maxPerProduct;

    public function __construct(?int $maxPerProduct = null)
    {
        $env = getenv('STOCK_MAX_PER_PRODUCT');
        $this->maxPerProduct = $maxPerProduct
            ?? (is_string($env) && ctype_digit($env) ? (int) $env : 1_000_000);
    }

    /**
     * Aumenta a quantidade atual em $delta (>0).
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function increase(int $current, int $delta): int
    {
        $current = $this->normalize($current);
        $delta = $this->normalizeDelta($delta);

        $new = $current + $delta;
        if ($new < 0) {
            // overflow de inteiro (muito improvável em PHP 64 bits, mas deixamos a guarda)
            throw new RuntimeException('Overflow ao aumentar estoque.');
        }
        if ($new > $this->maxPerProduct) {
            throw new RuntimeException('Quantidade máxima por produto excedida.');
        }

        return $new;
    }

    /**
     * Diminui a quantidade atual em $delta (>0).
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function decrease(int $current, int $delta): int
    {
        $current = $this->normalize($current);
        $delta = $this->normalizeDelta($delta);

        if ($delta > $current) {
            throw new RuntimeException('Estoque insuficiente para a operação.');
        }

        return $current - $delta;
    }

    /**
     * Ajuste genérico por delta (positivo aumenta, negativo diminui).
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function adjust(int $current, int $delta): int
    {
        if ($delta === 0) {
            return $this->normalize($current);
        }

        return $delta > 0
            ? $this->increase($current, $delta)
            : $this->decrease($current, -$delta);
    }

    private function normalize(int $q): int
    {
        if ($q < 0) {
            throw new InvalidArgumentException('Quantidade atual não pode ser negativa.');
        }

        return $q;
    }

    private function normalizeDelta(int $delta): int
    {
        if ($delta <= 0) {
            throw new InvalidArgumentException('Delta deve ser positivo.');
        }

        return $delta;
    }
}
