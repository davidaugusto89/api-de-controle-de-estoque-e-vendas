<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObjects;

use App\Domain\Shared\ValueObjects\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cenário coberto:
 * - Testes unitários para o Value Object `Money`.
 * - Suposições: a classe aceita float|int|string e tenta normalizar strings numéricas.
 *   Strings não numéricas são convertidas para 0.0 (comportamento observado por cast para float).
 * - Limites: não validamos formatos exóticos (ex.: múltiplos separadores de milhar ambíguos).
 * - Objetivo: garantir normalização, formatação (2 casas) e operações imutáveis add/sub.
 *
 * Justificativa dos choices:
 * - Teste unitário puro (estende PHPUnit TestCase) para rapidez e isolamento.
 * - Data providers para cobrir várias entradas de forma legível e determinística.
 */
final class MoneyTest extends TestCase
{
    /**
     * Provider cobrindo diferentes tipos de entrada e o valor esperado após normalização.
     *
     * Cada item: [entrada, expectedFloat, expectedStringRepresentation]
     */
    public static function providerParaNormalizacao(): array
    {
        return [
            'integer'               => [100, 100.0, '100.00'],
            'float'                 => [100.5, 100.5, '100.50'],
            'numeric-string-dot'    => ['1234.56', 1234.56, '1234.56'],
            'numeric-string-comma'  => ['1234,56', 1234.56, '1234.56'],
            'negative-string-comma' => ['-1234,56', -1234.56, '-1234.56'],
            'zero-string'           => ['0', 0.0, '0.00'],
            'invalid-string'        => ['not-a-number', 0.0, '0.00'],
        ];
    }

    /**
     * Provider para testar rounding/formatagem em __toString()
     *
     * Cada item: [entradaFloat, stringEsperado]
     */
    public static function providerParaFormatacao(): array
    {
        return [
            'round-down'         => [1.234, '1.23'],
            'round-up'           => [1.235, '1.24'],
            'one-decimal'        => [1.2, '1.20'],
            'exact-two-decimals' => [2.50, '2.50'],
        ];
    }

    #[DataProvider('providerParaNormalizacao')]
    public function test_normaliza_varios_inputs_e_expoe_float_e_string(mixed $input, float $expectedFloat, string $expectedString): void
    {
        // Arrange
        // Act
        $money = new Money($input);

        // Assert - asFloat
        $this->assertSame($expectedFloat, $money->asFloat(), 'asFloat() deve retornar o float normalizado esperado.');

        // Assert - __toString formatting (sempre com 2 casas)
        $this->assertSame($expectedString, (string) $money, '__toString() deve devolver valor com 2 casas decimais (ponto como separador).');
    }

    #[DataProvider('providerParaFormatacao')]
    public function test_formata_com_duas_casas_e_arredonda_corretamente(float $input, string $expectedString): void
    {
        // Arrange
        $money = new Money($input);

        // Act
        $string = (string) $money;

        // Assert
        $this->assertSame($expectedString, $string, 'Formato e arredondamento para 2 casas decimais devem ser consistentes.');
    }

    public function test_add_e_sub_retornam_novas_instancias_e_nao_mutam_originais(): void
    {
        // Arrange
        $originalA = new Money(100.00);
        $originalB = new Money('40,55');

        // Act
        $sum  = $originalA->add($originalB);
        $diff = $originalA->sub($originalB);

        // Assert - resultados corretos
        $this->assertSame(140.55, $sum->asFloat(), 'add() deve retornar a soma correta.');
        $this->assertSame(59.45, $diff->asFloat(), 'sub() deve retornar a diferença correta.');

        // Assert - imutabilidade (objetos originais não devem ser alterados)
        $this->assertSame(100.00, $originalA->asFloat(), 'Operações não devem alterar a instância original A.');
        $this->assertSame(40.55, $originalB->asFloat(), 'Operações não devem alterar a instância original B.');

        // Assert - tipos
        $this->assertInstanceOf(Money::class, $sum, 'add() deve retornar uma instância de Money.');
        $this->assertInstanceOf(Money::class, $diff, 'sub() deve retornar uma instância de Money.');
    }

    public function test_sub_pode_produzir_valores_negativos_e_string_reflete_sinal(): void
    {
        // Arrange
        $small = new Money(10);
        $big   = new Money(20);

        // Act
        $result = $small->sub($big);

        // Assert
        $this->assertSame(-10.0, $result->asFloat(), 'Resultado pode ser negativo quando subtraído de valor maior.');
        $this->assertSame('-10.00', (string) $result, 'Representação em string deve refletir o sinal negativo com 2 casas.');
    }

    public function test_construtor_e_metodos_sao_deterministicos_para_inputs_limite(): void
    {
        // Arrange / Act
        $fromNullLikeString = new Money(''); // string vazia -> cast float = 0.0
        $fromWhitespace     = new Money('   '); // whitespace -> cast float = 0.0
        $fromZeroInt        = new Money(0);

        // Assert
        $this->assertSame(0.0, $fromNullLikeString->asFloat(), 'String vazia deve resultar em 0.0 após normalização/cast.');
        $this->assertSame('0.00', (string) $fromNullLikeString, 'String vazia deve formatar como 0.00.');
        $this->assertSame(0.0, $fromWhitespace->asFloat(), 'String com whitespace deve resultar em 0.0 após normalização/cast.');
        $this->assertSame(0.0, $fromZeroInt->asFloat(), 'Integer zero deve resultar em 0.0.');
    }
}
