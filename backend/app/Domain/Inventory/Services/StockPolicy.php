<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Regras centrais de integridade de estoque (política de stock).
 *
 * Responsabilidade:
 * - Garantir operações seguras sobre quantidades de estoque por produto,
 *   incluindo validações de limites e normalização de entradas.
 *
 * Contrato resumido:
 * - Métodos aceitam inteiros e retornam inteiros normalizados.
 * - `increase` e `decrease` lançam {@see \InvalidArgumentException} para
 *   parâmetros inválidos e {@see \RuntimeException} quando a operação violaria
 *   as restrições (p.ex. underflow/overflow ou `maxPerProduct` excedido).
 *
 * Observações:
 * - `maxPerProduct` pode ser passado no construtor ou definido via variável de
 *   ambiente `STOCK_MAX_PER_PRODUCT`.
 */
final class StockPolicy
{
    /**
     * Upper bound for per-product stock. Can be overridden in runtime or
     * via env var STOCK_MAX_PER_PRODUCT.
     */
    private int $maxPerProduct;

    public function __construct(?int $maxPerProduct = null)
    {
        $env = getenv('STOCK_MAX_PER_PRODUCT');
        $this->maxPerProduct = $maxPerProduct
            ?? (is_string($env) && ctype_digit($env) ? (int) $env : 1_000_000);
    }

    /**
     * Aumenta a quantidade atual por um delta positivo.
     *
     * @param  int  $current  Quantidade atual (>= 0)
     * @param  int  $delta  Quantidade positiva a adicionar
     * @return int Nova quantidade após o aumento
     *
     * @throws InvalidArgumentException Em caso de entradas inválidas
     * @throws RuntimeException Se o resultado ficar fora dos limites permitidos
     */
    public function increase(int $current, int $delta): int
    {
        $current = $this->normalize($current);
        $delta = $this->normalizeDelta($delta);

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
     * @param  int  $current  Quantidade atual (>= 0)
     * @param  int  $delta  Quantidade positiva a subtrair
     * @return int Nova quantidade após a diminuição
     *
     * @throws InvalidArgumentException Em caso de entradas inválidas
     * @throws RuntimeException Se não houver estoque suficiente
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
     * Ajusta a quantidade por um delta assinado. Positivo aumenta, negativo diminui.
     *
     * @param  int  $current  Quantidade atual
     * @param  int  $delta  Delta assinado
     * @return int Nova quantidade
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
