<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

final class MarginCalculator
{
    /**
     * Retorna o lucro absoluto (não em %) com 2 casas.
     */
    public function profit(float $totalAmount, float $totalCost): float
    {
        return round($totalAmount - $totalCost, 2);
    }

    /**
     * (Opcional) margem percentual. Retorna 0 quando totalAmount = 0.
     */
    public function marginPercent(float $totalAmount, float $totalCost): float
    {
        if ($totalAmount <= 0.0) {
            return 0.0;
        }

        return round((($totalAmount - $totalCost) / $totalAmount) * 100, 2);
    }
}
