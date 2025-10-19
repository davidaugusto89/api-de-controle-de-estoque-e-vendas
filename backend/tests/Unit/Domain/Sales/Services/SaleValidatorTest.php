<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sales\Services;

use App\Domain\Sales\Services\SaleValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cenário
 * Dado: o serviço de validação `SaleValidator` que valida estrutura e valores básicos de items de venda
 * Quando: o método `validate(iterable $items)` é chamado com diferentes payloads
 * Então: validações de presença e formato/natureza dos campos são aplicadas e, quando inválidas, são lançadas InvalidArgumentException com mensagens específicas
 * Regras de Negócio Relevantes:
 *  - Campos validados por item: product_id (int > 0), quantity (int > 0), unit_price (float >= 0), unit_cost (float >= 0)
 *  - Validações de integridade de domínio (estoque disponível / totals) não pertencem a este validador.
 * Observações:
 *  - Entradas podem ser arrays associativos ou objetos com propriedades públicas (ex.: stdClass, Eloquent models).
 */
final class SaleValidatorTest extends TestCase
{
    #[DataProvider('providerValidItems')]
    public function test_validar_venda_sem_excecao_quando_items_sao_validos(iterable $items): void
    {
        // Arrange
        /**
         * Cenário
         * Dado: payloads válidos de itens de venda
         * Quando: `validate($items)` é executado
         * Então: nenhuma exceção é lançada
         */
        $sut = new SaleValidator;

        // Act
        $sut->validate($items);

        // Assert: nenhuma exceção lançada -> consideramos válido
        $this->assertTrue(true);
    }

    public static function providerValidItems(): array
    {
        $objItem = new \stdClass;
        $objItem->product_id = 1;
        $objItem->quantity = 2;
        $objItem->unit_price = 10.5;
        $objItem->unit_cost = 5.0;

        return [
            'itens em array' => [[
                ['product_id' => 1, 'quantity' => 1, 'unit_price' => 0.0, 'unit_cost' => 0.0],
                ['product_id' => 2, 'quantity' => 5, 'unit_price' => 3.25, 'unit_cost' => 1.0],
            ]],
            'itens como objeto' => [[
                $objItem,
            ]],
            'ids numericos em string e floats' => [[
                ['product_id' => '3', 'quantity' => '4', 'unit_price' => '7.5', 'unit_cost' => '2.5'],
            ]],
        ];
    }

    #[DataProvider('providerInvalidProductId')]
    public function test_lancar_excecao_quando_product_id_invalido(iterable $items): void
    {
        // Arrange
        /**
         * Cenário
         * Dado: items com product_id ausente ou inválido
         * Quando: `validate($items)` é executado
         * Então: InvalidArgumentException com mensagem específica é lançada
         */
        $sut = new SaleValidator;

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item inválido: product_id ausente/ inválido.');

        // Act
        $sut->validate($items);
    }

    public static function providerInvalidProductId(): array
    {
        $objMissing = new \stdClass;
        $objMissing->quantity = 1;
        $objMissing->unit_price = 1.0;
        $objMissing->unit_cost = 0.5;

        return [
            'array sem product_id' => [[
                ['quantity' => 1, 'unit_price' => 1.0, 'unit_cost' => 0.5],
            ]],
            'array product_id zero' => [[
                ['product_id' => 0, 'quantity' => 1, 'unit_price' => 1.0, 'unit_cost' => 0.5],
            ]],
            'objeto sem product_id' => [[$objMissing]],
        ];
    }

    #[DataProvider('providerInvalidQuantity')]
    public function test_lancar_excecao_quando_quantidade_invalida(array $item): void
    {
        // Arrange
        /**
         * Cenário
         * Dado: item com quantidade inválida (<=0)
         * Quando: validate é chamado
         * Então: InvalidArgumentException com mensagem 'Item 1: quantity deve ser > 0.' é lançada
         */
        $sut = new SaleValidator;

        // Assert: SaleValidator usa o índice (1-based) do item, não o product_id
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Item 1: quantity deve ser > 0.');

        // Act
        $sut->validate([$item]);
    }

    public static function providerInvalidQuantity(): array
    {
        return [
            'quantidade zero' => [['product_id' => 5, 'quantity' => 0, 'unit_price' => 1.0, 'unit_cost' => 0.5]],
            'quantidade negativa' => [['product_id' => 5, 'quantity' => -3, 'unit_price' => 1.0, 'unit_cost' => 0.5]],
        ];
    }

    #[DataProvider('providerNegativePriceOrCost')]
    public function test_lancar_excecao_quando_preco_ou_custo_negativo(array $item, string $expectedMessage): void
    {
        // Arrange
        /**
         * Cenário
         * Dado: item com unit_price ou unit_cost negativo
         * Quando: validate é executado
         * Então: InvalidArgumentException com mensagem apropriada é lançada
         */
        $sut = new SaleValidator;

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        // Act
        $sut->validate([$item]);
    }

    public static function providerNegativePriceOrCost(): array
    {
        return [
            // Como só há um item no array do teste, o índice é 1
            'preco negativo' => [['product_id' => 3, 'quantity' => 1, 'unit_price' => -0.01, 'unit_cost' => 0.5], 'Item 1: unit_price não pode ser negativo.'],
            'custo negativo' => [['product_id' => 3, 'quantity' => 1, 'unit_price' => 1.0, 'unit_cost' => -2.0], 'Item 1: unit_cost não pode ser negativo.'],
        ];
    }

    public function test_lancar_excecao_quando_lista_vazia(): void
    {
        // Arrange
        /**
         * Cenário
         * Dado: lista vazia de items
         * Quando: validate([]) é chamado
         * Então: InvalidArgumentException indicando que é necessário ao menos um item
         */
        $sut = new SaleValidator;

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A venda deve conter ao menos um item.');

        // Act
        $sut->validate([]);
    }

    public function test_nao_validar_estoque_ou_total_quando_servico_eh_dominio_puro(): void
    {
        // Arrange
        /**
         * Cenário
         * Dado: validator de domínio puro
         * Quando: item com quantidade muito alta é validado
         * Então: nenhuma exceção relacionada a estoque/total é lançada (não é responsabilidade do validador)
         */
        $sut = new SaleValidator;

        // item com quantidade extremamente alta (simulando > estoque)
        $item = ['product_id' => 99, 'quantity' => 1_000_000, 'unit_price' => 1.0, 'unit_cost' => 0.1];

        // Act / Assert: não deve lançar InvalidArgumentException
        $sut->validate([$item]);

        $this->assertTrue(true, 'Validator não valida estoque/total — comportamento esperado neste teste.');
    }
}
