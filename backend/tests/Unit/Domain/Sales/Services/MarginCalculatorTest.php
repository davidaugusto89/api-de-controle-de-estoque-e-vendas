<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Services;

use App\Domain\Sales\Services\MarginCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cenário
 * Dado: o serviço de domínio `MarginCalculator` que calcula profit e marginPercent
 * Quando: os métodos `profit(totalAmount, totalCost)` e `marginPercent(totalAmount, totalCost)` são invocados com vários pares de valores
 * Então: espera-se profit arredondado a 2 casas e marginPercent (em %) também arredondado a 2 casas; para totalAmount <= 0 o percentual é 0.0
 * Regras de Negócio Relevantes:
 *  - Arredondamento é feito com round(..., 2).
 *  - Para totalAmount <= 0, `marginPercent` retorna 0.0 (evitar divisão por zero).
 * Observações:
 *  - Testes são 100% unitários (sem dependências externas).
 *  - Usamos data provider para cobrir limites (zeros, negativos, casos de arredondamento e grandes valores).
 */
final class MarginCalculatorTest extends TestCase
{
    /**
     * Provider com vários cenários (totalAmount, totalCost, expectedProfit, expectedMarginPercent)
     *
     * Note: expectedProfit corresponde ao valor arredondado a 2 casas do (totalAmount - totalCost).
     * expectedMarginPercent corresponde a:
     *   - 0.0 quando totalAmount <= 0
     *   - round(((totalAmount - totalCost) / totalAmount) * 100, 2) caso contrário
     */
    public static function providerForProfitAndMargin(): array
    {
        return [
            'zeros' => [
                0.0, 0.0,
                0.00, 0.00,
            ],
            'positivo simples' => [
                100.0, 60.0,
                40.00, 40.00,
            ],
            'arredondamento para cima' => [
                1.235, 0.0,
                1.24, 100.00, // profit arredonda para 1.24, percentual sobre 1.235 é 100.00%
            ],
            'arredondamento para baixo' => [
                1.234, 0.0,
                1.23, 100.00, // profit arredonda para 1.23
            ],
            'pequeno percentual' => [
                10.0, 9.995,
                0.01, 0.05, // profit = round(0.005,2)=0.01 ; percent = round((0.005/10)*100,2)=0.05
            ],
            'valores negativos' => [
                -100.0, -50.0,
                -50.00, 0.00, // totalAmount <= 0 -> percent = 0.0 (proteção)
            ],
            'multiplos valores' => [
                200.0, 50.0,
                150.00, 75.00,
            ],
            'caso de precisao' => [
                100.0, 33.3333,
                66.67, 66.67, // difference ~66.6667 -> round 66.67
            ],
            'large_numbers' => [
                1000000000.125, 500000000.0625,
                500000000.06, 50.00, // diff = 500000000.0625 -> rounded 500000000.06 ; percent approx 50.00
            ],
        ];
    }

    #[DataProvider('providerForProfitAndMargin')]
    public function test_deve_calcular_profit_e_percentual_corretamente(
        float $totalAmount,
        float $totalCost,
        float $expectedProfit,
        float $expectedMarginPercent
    ): void {
        /**
         * Cenário
         * Dado: o serviço de domínio `MarginCalculator` que calcula profit e marginPercent
         * Quando: `profit(totalAmount, totalCost)` e `marginPercent(totalAmount, totalCost)` são invocados com pares de valores
         * Então: profit e marginPercent são retornados conforme esperado, arredondados a 2 casas
         * Regras de Negócio Relevantes:
         *  - Arredondamento com round(..., 2).
         *  - Para totalAmount <= 0, marginPercent retorna 0.0.
         */
        $sut = new MarginCalculator;

        // Act
        $profit = $sut->profit($totalAmount, $totalCost);
        $percent = $sut->marginPercent($totalAmount, $totalCost);

        // Assert - profit arredondado a 2 casas conforme implementação
        $this->assertEqualsWithDelta(
            $expectedProfit,
            $profit,
            0.00001,
            sprintf('Esperado profit %.2f para amount=%.6f cost=%.6f', $expectedProfit, $totalAmount, $totalCost)
        );

        // Assert - percent com branch especial quando totalAmount <= 0
        $this->assertEqualsWithDelta(
            $expectedMarginPercent,
            $percent,
            0.00001,
            sprintf('Esperado marginPercent %.2f para amount=%.6f cost=%.6f', $expectedMarginPercent, $totalAmount, $totalCost)
        );
    }

    /**
     * Casos explícitos para garantir proteção contra divisão por zero e comportamento quando totalAmount <= 0.
     */
    public function test_nao_deve_dividir_por_zero_e_retornar_percentual_zero_quando_totalamount_zero(): void
    {
        /**
         * Cenário
         * Dado: totalAmount == 0
         * Quando: marginPercent e profit são calculados
         * Então: marginPercent == 0.0 e profit calculado corretamente (pode ser negativo), com arredondamento
         */
        $sut = new MarginCalculator;

        // Act
        $percentZero = $sut->marginPercent(0.0, 10.0);
        $profitZero = $sut->profit(0.0, 10.0);

        // Assert
        $this->assertSame(0.0, $percentZero, 'marginPercent deve retornar 0.0 quando totalAmount == 0.');
        $this->assertEqualsWithDelta(-10.00, $profitZero, 0.00001, 'profit deve ser (0 - 10) arredondado a 2 casas = -10.00');
    }

    public function test_nao_deve_dividir_por_zero_e_retornar_percentual_zero_quando_totalamount_negativo(): void
    {
        /**
         * Cenário
         * Dado: totalAmount negativo
         * Quando: marginPercent e profit são calculados
         * Então: marginPercent == 0.0 e profit refletindo a diferença, com arredondamento
         */
        $sut = new MarginCalculator;

        // Act
        $percentNegative = $sut->marginPercent(-1.0, 0.0);
        $profitNegative = $sut->profit(-1.0, 0.0);

        // Assert
        $this->assertSame(0.0, $percentNegative, 'marginPercent deve retornar 0.0 quando totalAmount < 0.');
        $this->assertEqualsWithDelta(-1.00, $profitNegative, 0.00001, 'profit deve ser (-1 - 0) arredondado a 2 casas = -1.00');
    }

    /**
     * Casos pontuais de arredondamento (limiar .005).
     */
    public function test_arredondamento_do_profit_em_casos_de_limiar(): void
    {
        /**
         * Cenário
         * Dado: valores próximos do limiar de arredondamento
         * Quando: profit() é chamado com 1.234 e 1.235
         * Então: comportamento de arredondamento segue round(..., 2)
         */
        $sut = new MarginCalculator;

        // Act & Assert - 1.234 -> 1.23
        $this->assertEqualsWithDelta(1.23, $sut->profit(1.234, 0.0), 0.00001);

        // Act & Assert - 1.235 -> 1.24 (arredondamento para cima)
        $this->assertEqualsWithDelta(1.24, $sut->profit(1.235, 0.0), 0.00001);
    }
}
