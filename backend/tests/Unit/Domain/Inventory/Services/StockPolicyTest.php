<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Inventory\Services;

use App\Domain\Inventory\Services\StockPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Cenário
 * Dado: uma política de estoque `StockPolicy` que valida operações de increase/decrease/adjust
 * Quando: métodos são invocados com valores válidos e inválidos
 * Então: os retornos e as exceções seguem as regras de negócio (delta positivo, não exceder máximo, não ficar negativo)
 * Regras de Negócio Relevantes:
 *  - Delta deve ser positivo (>0) para increase/decrease quando aplicável.
 *  - current não pode ser negativo.
 *  - Existe um limite superior (maxPerProduct) configurável; exceder lança RuntimeException.
 * Observações:
 *  - Testes validam mensagens de exceção literais conforme implementação atual.
 */
final class StockPolicyTest extends TestCase
{
    #[DataProvider('providerIncreaseSuccess')]
    public function test_aumentar_retorna_valor_quando_delta_valido(int $current, int $delta, int $expected): void
    {
        // Arrange
        $sut = $this->makePolicy(1_000_000);

        // Act
        $result = $sut->increase($current, $delta);

        // Assert
        $this->assertSame($expected, $result);
    }

    public static function providerIncreaseSuccess(): array
    {
        return [
            'incremento simples' => [0, 1, 1],
            'incremento grande' => [100, 900, 1000],
            'no limite' => [999_999, 1, 1_000_000],
        ];
    }

    public function test_nao_deve_permitir_delta_invalido_no_increase(): void
    {
        // Arrange
        $sut = $this->makePolicy();

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delta deve ser positivo.');

        // Act
        $sut->increase(10, 0);
    }

    public function test_nao_deve_permitir_quantidade_atual_negativa_no_increase(): void
    {
        // Arrange
        $sut = $this->makePolicy();

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantidade atual não pode ser negativa.');

        // Act
        $sut->increase(-1, 1);
    }

    public function test_nao_deve_permitir_exceder_maximo_por_produto(): void
    {
        // Arrange: limite baixo para testar overflow de regra
        $sut = $this->makePolicy(5);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Quantidade máxima por produto excedida.');

        // Act
        $sut->increase(5, 1);
    }

    #[DataProvider('providerDecreaseSuccess')]
    public function test_diminuir_retorna_valor_quando_delta_valido(int $current, int $delta, int $expected): void
    {
        // Arrange
        $sut = $this->makePolicy();

        // Act
        $result = $sut->decrease($current, $delta);

        // Assert
        $this->assertSame($expected, $result);
    }

    public static function providerDecreaseSuccess(): array
    {
        return [
            'diminuição simples' => [5, 1, 4],
            'zerar estoque' => [3, 3, 0],
        ];
    }

    public function test_nao_deve_permitir_decrease_com_delta_maior_que_current(): void
    {
        // Arrange
        $sut = $this->makePolicy();

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Estoque insuficiente para a operação.');

        // Act
        $sut->decrease(2, 3);
    }

    public function test_nao_deve_permitir_delta_invalido_no_decrease(): void
    {
        // Arrange
        $sut = $this->makePolicy();

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delta deve ser positivo.');

        // Act
        $sut->decrease(2, 0);
    }

    public function test_nao_deve_permitir_current_negativo_no_decrease(): void
    {
        // Arrange
        $sut = $this->makePolicy();

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantidade atual não pode ser negativa.');

        // Act
        $sut->decrease(-5, 1);
    }

    #[DataProvider('providerAdjust')]
    public function test_adjust_comportamento(int $current, int $delta, int $expected, ?string $expectedExceptionMessage = null): void
    {
        // Arrange
        $sut = $this->makePolicy(1_000_000);

        if ($expectedExceptionMessage !== null) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        // Act
        $result = $sut->adjust($current, $delta);

        // Assert
        if ($expectedExceptionMessage === null) {
            $this->assertSame($expected, $result);
        }
    }

    public static function providerAdjust(): array
    {
        return [
            'delta zero' => [10, 0, 10, null],
            'delta positivo' => [5, 3, 8, null],
            'delta negativo suficiente' => [5, -3, 2, null],
            'delta negativo insuficiente' => [2, -3, 0, 'Estoque insuficiente para a operação.'],
        ];
    }

    public function test_limite_superior_configurado_via_env(): void
    {
        // Arrange: simular variável de ambiente (note: getenv é lida no construtor se não passar explicitamente)
        putenv('STOCK_MAX_PER_PRODUCT=42');

        $sut = $this->makePolicy();

        // Assert: tentar aumentar além do limite
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Quantidade máxima por produto excedida.');

        // Act
        $sut->increase(42, 1);
    }

    private function makePolicy(?int $maxPerProduct = null): StockPolicy
    {
        if ($maxPerProduct === null) {
            return new StockPolicy;
        }

        return new StockPolicy($maxPerProduct);
    }
}
