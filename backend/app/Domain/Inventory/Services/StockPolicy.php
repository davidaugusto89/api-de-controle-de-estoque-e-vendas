<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Política de estoque: valida e aplica operações seguras sobre quantidades.
 */
final class StockPolicy
{
    /** Limite máximo por produto (padrão: env STOCK_MAX_PER_PRODUCT ou 1_000_000). */
    private int $maxPerProduct;

    public function __construct(?int $maxPerProduct = null)
    {
        $env                 = getenv('STOCK_MAX_PER_PRODUCT');
        $this->maxPerProduct = $maxPerProduct
            ?? (is_string($env) && ctype_digit($env) ? (int) $env : 1_000_000);
    }

    /**
     * Aumenta a quantidade atual por um delta positivo.
     *
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function increase(int $current, int $delta): int
    {
        $current = $this->normalize($current);
        $delta   = $this->normalizeDelta($delta);

        $new = $current + $delta;
        if ($new < 0) {
            throw new RuntimeException('Overflow ao aumentar estoque.');
        }
        if ($new > $this->maxPerProduct) {
            throw new RuntimeException('Quantidade máxima por produto excedida.');
        }

        return $new;
    }

    /**
     * Diminui a quantidade atual por um delta positivo.
     *
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function decrease(int $current, int $delta): int
    {
        $current = $this->normalize($current);
        $delta   = $this->normalizeDelta($delta);

        if ($delta > $current) {
            throw new RuntimeException('Estoque insuficiente para a operação.');
        }

        return $current - $delta;
    }

    /**
     * Ajusta a quantidade por um delta assinado (positivo aumenta, negativo diminui).
     *
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
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

    /**
     * @throws InvalidArgumentException
     */
    private function normalize(int $q): int
    {
        if ($q < 0) {
            throw new InvalidArgumentException('Quantidade atual não pode ser negativa.');
        }

        return $q;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function normalizeDelta(int $delta): int
    {
        if ($delta <= 0) {
            throw new InvalidArgumentException('Delta deve ser positivo.');
        }

        return $delta;
    }
}
