<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

/**
 * Calculadora de margem e lucro de vendas.
 */
final class MarginCalculator
{
    /**
     * Retorna o lucro absoluto arredondado para 2 casas decimais.
     */
    public function profit(float $totalAmount, float $totalCost): float
    {
        return round($totalAmount - $totalCost, 2);
    }

    /**
     * Retorna a margem percentual (0 se totalAmount for zero ou negativo).
     */
    public function marginPercent(float $totalAmount, float $totalCost): float
    {
        if ($totalAmount <= 0.0) {
            return 0.0;
        }

        return round((($totalAmount - $totalCost) / $totalAmount) * 100, 2);
    }
}
