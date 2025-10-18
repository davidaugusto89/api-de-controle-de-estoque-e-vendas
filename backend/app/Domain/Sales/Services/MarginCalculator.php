<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

/**
 * Calculadora de margens e lucros para vendas.
 *
 * Responsabilidade:
 * - Determinar lucro absoluto (rounded) e margem percentual com regras defensivas
 *   (retorna 0 quando `totalAmount` for zero ou negativo).
 *
 * Observações:
 * - Retorna valores arredondados para 2 casas decimais prontos para persistência/apresentação.
 */
final class MarginCalculator
{
    /**
     * Calcula o lucro absoluto (arredondado para 2 casas decimais).
     *
     * @param  float  $totalAmount  Valor total da venda
     * @param  float  $totalCost  Custo total
     * @return float Lucro arredondado para 2 casas
     */
    public function profit(float $totalAmount, float $totalCost): float
    {
        return round($totalAmount - $totalCost, 2);
    }

    /**
     * Calcula a margem percentual. Retorna 0 quando totalAmount é zero ou negativo.
     *
     * @param  float  $totalAmount  Valor total da venda
     * @param  float  $totalCost  Custo total
     * @return float Margem percentual arredondada para 2 casas
     */
    public function marginPercent(float $totalAmount, float $totalCost): float
    {
        if ($totalAmount <= 0.0) {
            return 0.0;
        }

        return round((($totalAmount - $totalCost) / $totalAmount) * 100, 2);
    }
}
