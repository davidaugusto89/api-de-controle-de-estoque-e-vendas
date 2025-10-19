<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Entities;

use App\Domain\Sales\Entities\SaleAggregate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cenário
 * Dado: um agregado em memória `SaleAggregate` que acumula itens (product_id, quantity, unit_price, unit_cost)
 * Quando: itens são adicionados ao agregado via `addItem(...)` em diferentes combinações
 * Então: os totais (`totalAmount`, `totalCost`, `totalProfit`) e a lista de `items` refletem os cálculos esperados
 * Regras de Negócio Relevantes:
 *  - A classe atual não valida valores negativos; testes acompanham o comportamento atual.
 *  - `items()` deve retornar uma cópia independente (imutabilidade observável).
 * Observações:
 *  - Testes são 100% unitários (sem IO/DB) e usam assertEqualsWithDelta para comparações numéricas.
 */
final class SaleAggregateTest extends TestCase
{
    public function test_retornar_totais_zero_quando_nao_existirem_itens(): void
    {
        /**
         * Cenário
         * Dado: agregado vazio `SaleAggregate`
         * Quando: chamamos totalAmount/totalCost/totalProfit/items
         * Então: todos os totais são 0.0 e items() retorna array vazio
         */
        // Arrange
        $sut = new SaleAggregate;

        // Act
        $amount = $sut->totalAmount();
        $cost = $sut->totalCost();
        $profit = $sut->totalProfit();
        $items = $sut->items();

        // Assert
        $this->assertEqualsWithDelta(0.0, $amount, 0.00001, 'totalAmount() deve ser 0.0 para agregado vazio.');
        $this->assertEqualsWithDelta(0.0, $cost, 0.00001, 'totalCost() deve ser 0.0 para agregado vazio.');
        $this->assertEqualsWithDelta(0.0, $profit, 0.00001, 'totalProfit() deve ser 0.0 para agregado vazio.');
        $this->assertSame([], $items, 'items() deve retornar array vazio quando não há itens.');
    }

    /**
     * Provider com combinações de itens e totais esperados.
     *
     * Cada caso: [itemsArray, expectedAmount, expectedCost, expectedProfit]
     * itemsArray: array of [productId, qty, price, cost]
     */
    public static function providerForTotals(): array
    {
        return [
            'item unico' => [
                [[1, 2, 10.00, 6.00]],
                20.00, // amount = 2 * 10
                12.00, // cost = 2 * 6
                8.00,  // profit = amount - cost
            ],
            'multiplos itens' => [
                [
                    [1, 1, 9.99, 4.50],
                    [2, 3, 5.50, 2.00],
                    [3, 2, 100.00, 60.00],
                ],
                (1 * 9.99) + (3 * 5.50) + (2 * 100.00),
                (1 * 4.50) + (3 * 2.00) + (2 * 60.00),
                ((1 * 9.99) + (3 * 5.50) + (2 * 100.00)) - ((1 * 4.50) + (3 * 2.00) + (2 * 60.00)),
            ],
            'quantidade_zero_e_preco_zero' => [
                [
                    [1, 0, 10.00, 5.00],
                    [2, 5, 0.00, 0.00],
                ],
                0.0, // no positive amount
                0.0, // no positive cost
                0.0,
            ],
            'valores_negativos_permitidos_pela_impl_atual' => [
                [
                    [1, 2, -10.00, 5.00],  // negative price
                    [2, -1, 20.00, -8.00], // negative qty and negative cost (odd but allowed)
                ],
                (2 * -10.00) + (-1 * 20.00),
                (2 * 5.00) + (-1 * -8.00),
                ((2 * -10.00) + (-1 * 20.00)) - ((2 * 5.00) + (-1 * -8.00)),
            ],
        ];
    }

    #[DataProvider('providerForTotals')]
    public function test_calcular_totais_corretamente_quando_itens_variados(array $itemsToAdd, float $expectedAmount, float $expectedCost, float $expectedProfit): void
    {
        // Arrange
        $sut = new SaleAggregate;

        // Act - adiciona os itens fornecidos
        foreach ($itemsToAdd as $raw) {
            [$productId, $qty, $price, $cost] = $raw;
            $sut->addItem((int) $productId, (int) $qty, (float) $price, (float) $cost);
        }

        $amount = $sut->totalAmount();
        $cost = $sut->totalCost();
        $profit = $sut->totalProfit();

        // Assert usando delta para tolerância de ponto flutuante
        $this->assertEqualsWithDelta($expectedAmount, $amount, 0.0001, 'totalAmount() deve refletir soma de quantidade*unit_price.');
        $this->assertEqualsWithDelta($expectedCost, $cost, 0.0001, 'totalCost() deve refletir soma de quantidade*unit_cost.');
        $this->assertEqualsWithDelta($expectedProfit, $profit, 0.0001, 'totalProfit() deve ser amount - cost.');
    }

    public function test_retornar_itens_preservando_ordem_e_estrutura(): void
    {
        // Arrange
        $sut = new SaleAggregate;
        $sut->addItem(10, 1, 1.5, 0.5);
        $sut->addItem(20, 2, 2.0, 1.0);

        // Act
        $items = $sut->items();

        // Assert - estrutura esperada e ordem preservada
        $this->assertCount(2, $items);
        $this->assertSame(10, $items[0]['product_id']);
        $this->assertSame(1, $items[0]['quantity']);
        $this->assertEqualsWithDelta(1.5, $items[0]['unit_price'], 0.0001);
        $this->assertEqualsWithDelta(0.5, $items[0]['unit_cost'], 0.0001);

        $this->assertSame(20, $items[1]['product_id']);
        $this->assertSame(2, $items[1]['quantity']);
    }

    public function test_items_retornar_copia_e_nao_permitir_mutacao_externa(): void
    {
        // Arrange
        $sut = new SaleAggregate;
        $sut->addItem(1, 1, 10.0, 5.0);

        // Act
        $itemsCopy = $sut->items();
        $itemsCopy[0]['quantity'] = 999; // muta a cópia retornada
        $itemsCopy[] = ['product_id' => 999, 'quantity' => 1, 'unit_price' => 1.0, 'unit_cost' => 0.5];

        // Assert - o agregado original não deve refletir estas mudanças
        $originalItems = $sut->items();
        $this->assertSame(1, $originalItems[0]['quantity'], 'Mutação no array retornado não deve afetar o agregado interno.');
        $this->assertCount(1, $originalItems, 'Adicionar elementos ao array retornado não altera o agregado interno.');
    }

    public function test_total_profit_ser_consistente_com_amount_menos_cost_quando_casos_complexos(): void
    {
        // Arrange
        $sut = new SaleAggregate;
        $sut->addItem(1, 3, 9.99, 4.33);
        $sut->addItem(2, 7, 1.25, 0.75);

        // Act
        $amount = $sut->totalAmount();
        $cost = $sut->totalCost();
        $profit = $sut->totalProfit();

        // Assert
        $this->assertEqualsWithDelta($amount - $cost, $profit, 0.0001, 'totalProfit deve ser igual a totalAmount - totalCost.');
        // Sanity: valores numéricos esperados aproximados
        $this->assertEqualsWithDelta((3 * 9.99) + (7 * 1.25), $amount, 0.0001);
        $this->assertEqualsWithDelta((3 * 4.33) + (7 * 0.75), $cost, 0.0001);
    }
}
