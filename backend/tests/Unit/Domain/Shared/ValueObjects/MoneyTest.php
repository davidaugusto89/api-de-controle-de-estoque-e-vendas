<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObjects;

use App\Domain\Shared\ValueObjects\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cenário
 * Dado: o Value Object `Money` que normaliza e opera sobre valores monetários
 * Quando: for construído a partir de diferentes tipos (float, int, string) e
 *       submetido a operações aritméticas simples
 * Então: deve garantir:
 *   - normalização correta com round(..., 2) e conversão de vírgula para ponto;
 *   - representação string com 2 casas decimais (ponto como separador);
 *   - operações add/sub imutáveis retornando novas instâncias;
 *   - comportamento determinístico para entradas-limite (string vazia, whitespace).
 *
 * Regras de Negócio Relevantes:
 *  - Normalização aplica round(..., 2).
 *  - Strings numéricas com vírgula são aceitas (ex.: "12,34").
 *
 * Observações:
 *  - Testes 100% unitários.
 */
#[CoversClass(Money::class)]
final class MoneyTest extends TestCase
{
    #[DataProvider('providerForNormalization')]
    public function test_normaliza_varios_inputs_e_expoe_float_e_string(mixed $input, float $expectedFloat, string $expectedString): void
    {
        /**
         * Cenário
         * Dado: diversos tipos de entrada para `Money`
         * Quando: instanciado e convertido para float/string
         * Então: normalização e formatação devem corresponder aos valores esperados
         */
        $money = new Money($input);

        $this->assertSame($expectedFloat, $money->asFloat(), 'asFloat() deve retornar o float normalizado esperado.');
        $this->assertSame($expectedString, (string) $money, '__toString() deve devolver valor com 2 casas decimais (ponto como separador).');
    }

    public static function providerForNormalization(): array
    {
        return [
            'integer' => [100, 100.0, '100.00'],
            'float' => [100.5, 100.5, '100.50'],
            'numeric-string-dot' => ['1234.56', 1234.56, '1234.56'],
            'numeric-string-comma' => ['1234,56', 1234.56, '1234.56'],
            'negative-string-comma' => ['-1234,56', -1234.56, '-1234.56'],
            'zero-string' => ['0', 0.0, '0.00'],
            'invalid-string' => ['not-a-number', 0.0, '0.00'],
        ];
    }

    #[DataProvider('providerForFormatting')]
    public function test_formata_e_arredonda_corretamente(float $input, string $expectedString): void
    {
        /**
         * Cenário
         * Dado: floats com precisão limiar
         * Quando: convertido para string
         * Então: string reflete arredondamento para 2 casas
         */
        $money = new Money($input);

        $this->assertSame($expectedString, (string) $money, 'Formato e arredondamento para 2 casas decimais devem ser consistentes.');
    }

    public static function providerForFormatting(): array
    {
        return [
            'round-down' => [1.234, '1.23'],
            'round-up' => [1.235, '1.24'],
            'one-decimal' => [1.2, '1.20'],
            'exact-two-decimals' => [2.50, '2.50'],
        ];
    }

    public function test_add_e_sub_retornam_novas_instancias_e_nao_mutam_originais(): void
    {
        /**
         * Cenário
         * Dado: dois Money A e B
         * Quando: add/sub são executados
         * Então: retornam novas instâncias com valores corretos e não mutam originais
         */
        $a = new Money(100.00);
        $b = new Money('40,55');

        $sum = $a->add($b);
        $diff = $a->sub($b);

        $this->assertSame(140.55, $sum->asFloat(), 'add() deve retornar a soma correta.');
        $this->assertSame(59.45, $diff->asFloat(), 'sub() deve retornar a diferença correta.');

        $this->assertSame(100.00, $a->asFloat(), 'Operações não devem alterar a instância original A.');
        $this->assertSame(40.55, $b->asFloat(), 'Operações não devem alterar a instância original B.');

        $this->assertInstanceOf(Money::class, $sum, 'add() deve retornar uma instância de Money.');
        $this->assertInstanceOf(Money::class, $diff, 'sub() deve retornar uma instância de Money.');
    }

    public function test_sub_pode_produzir_valores_negativos_e_string_reflete_sinal(): void
    {
        $small = new Money(10);
        $big = new Money(20);

        $result = $small->sub($big);

        $this->assertSame(-10.0, $result->asFloat(), 'Resultado pode ser negativo quando subtraído de valor maior.');
        $this->assertSame('-10.00', (string) $result, 'Representação em string deve refletir o sinal negativo com 2 casas.');
    }

    public function test_comportamento_para_inputs_limite(): void
    {
        /**
         * Cenário
         * Dado: inputs limite (string vazia, whitespace, zero int)
         * Quando: instanciados como Money
         * Então: normalizam para 0.0 e string '0.00'
         */
        $fromEmpty = new Money('');
        $fromWhitespace = new Money('   ');
        $fromZeroInt = new Money(0);

        $this->assertSame(0.0, $fromEmpty->asFloat());
        $this->assertSame('0.00', (string) $fromEmpty);
        $this->assertSame(0.0, $fromWhitespace->asFloat());
        $this->assertSame(0.0, $fromZeroInt->asFloat());
    }
}

// Como rodar (Docker / Sail):
// docker compose exec php vendor/bin/phpunit --testsuite Unit --filter MoneyTest --coverage-text
// ./vendor/bin/sail test --testsuite=Unit --filter=MoneyTest --coverage-text
