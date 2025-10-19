<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\ValueObjects;

use App\Domain\Shared\ValueObjects\DateRange;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cenário coberto:
 * - Testes unitários para o Value Object `DateRange`.
 * - Suposições:
 *   * Entradas são `CarbonImmutable` (conforme contrato da classe).
 *   * `DateRange::of` deve normalizar os limites para início e fim do dia.
 * - Limites e decisões:
 *   * Não lidamos com fusos horários diferentes neste conjunto (assume-se timezone padrão do ambiente/Carbon::setTestNow).
 *   * Verificamos explicitamente a mensagem da exceção lançada quando from > to.
 *
 * Nota sobre determinismo:
 * - Todos os testes travam o "now" via Carbon::setTestNow() no setUp para evitar variações temporais.
 */
final class DateRangeTest extends TestCase
{
    protected function setUp(): void
    {
        // Fixar "now" para garantir determinismo em operações que possam depender de now()
        Carbon::setTestNow(Carbon::parse('2025-10-18 12:34:56'));
    }

    protected function tearDown(): void
    {
        // Limpar o test now após cada teste
        Carbon::setTestNow();
    }

    /**
     * Provider com ranges válidos (from <= to).
     *
     * Cada item: [fromIso, toIso]
     */
    public static function validRangesProvider(): array
    {
        return [
            'from antes de to' => ['2025-10-17 00:00:00', '2025-10-18 00:00:00'],
            'mesmo instante' => ['2025-10-18 10:00:00', '2025-10-18 10:00:00'], // from == to is permitido
            'diferentes horarios mesmo dia' => ['2025-10-18 01:23:45', '2025-10-18 23:59:59'],
        ];
    }

    /**
     * Provider com ranges inválidos (from > to).
     *
     * Cada item: [fromIso, toIso]
     */
    public static function invalidRangesProvider(): array
    {
        return [
            'from depois de to um dia' => ['2025-10-19 00:00:00', '2025-10-18 23:59:59'],
            'from depois de to mesmo dia' => ['2025-10-18 12:00:01', '2025-10-18 12:00:00'],
        ];
    }

    #[DataProvider('validRangesProvider')]
    public function test_deve_criar_date_range_quando_from_menor_ou_igual_to(string $fromIso, string $toIso): void
    {
        /**
         * Cenário
         * Dado: pares (from <= to)
         * Quando: instanciamos DateRange(from, to)
         * Então: instancia é criada e preserva os instantes exatos passados
         */
        // Arrange
        $from = CarbonImmutable::parse($fromIso);
        $to = CarbonImmutable::parse($toIso);

        // Act
        $range = new DateRange($from, $to);

        // Assert - instância criada corretamente
        $this->assertInstanceOf(DateRange::class, $range);

        // Assert - as propriedades devem preservar os valores passados (construtor não normaliza)
        $this->assertSame(
            $from->format('Y-m-d H:i:s'),
            $range->from->format('Y-m-d H:i:s'),
            'O construtor deve preservar o instante exato passado para from.'
        );

        $this->assertSame(
            $to->format('Y-m-d H:i:s'),
            $range->to->format('Y-m-d H:i:s'),
            'O construtor deve preservar o instante exato passado para to.'
        );
    }

    #[DataProvider('validRangesProvider')]
    public function test_deve_normalizar_para_inicio_e_fim_do_dia_quando_usar_of(string $fromIso, string $toIso): void
    {
        /**
         * Cenário
         * Dado: dois instantes (from, to)
         * Quando: DateRange::of(from, to) é chamado
         * Então: from normaliza para startOfDay e to para endOfDay
         */
        // Arrange
        $from = CarbonImmutable::parse($fromIso);
        $to = CarbonImmutable::parse($toIso);

        // Act
        $range = DateRange::of($from, $to);

        // Assert - from normalizado para início do dia (00:00:00) e to para fim do dia (23:59:59)
        $this->assertSame(
            $from->format('Y-m-d'),
            $range->from->format('Y-m-d'),
            'DateRange::of deve preservar a data do "from" mas normalizar para início do dia.'
        );
        $this->assertSame('00:00:00', $range->from->format('H:i:s'), 'from deve ser startOfDay (00:00:00).');

        $this->assertSame(
            $to->format('Y-m-d'),
            $range->to->format('Y-m-d'),
            'DateRange::of deve preservar a data do "to" mas normalizar para fim do dia.'
        );
        $this->assertSame('23:59:59', $range->to->format('H:i:s'), 'to deve ser endOfDay (23:59:59).');
    }

    #[DataProvider('invalidRangesProvider')]
    public function test_deve_lancar_invalid_argument_exception_quando_from_maior_que_to(string $fromIso, string $toIso): void
    {
        /**
         * Cenário
         * Dado: from > to
         * Quando: criamos um DateRange
         * Então: InvalidArgumentException com mensagem específica é lançada
         */
        // Arrange
        $from = CarbonImmutable::parse($fromIso);
        $to = CarbonImmutable::parse($toIso);

        // Assert - exceção esperada com mensagem específica
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DateRange inválido: from > to.');

        // Act - deve lançar
        new DateRange($from, $to);
    }

    public function test_date_range_e_objeto_immutavel_em_relacao_as_variaveis_locais(): void
    {
        /**
         * Cenário
         * Dado: DateRange criado a partir de CarbonImmutable
         * Quando: variáveis locais são modificadas posteriormente
         * Então: DateRange mantém imutabilidade dos instantes originalmente normalizados
         */
        // Arrange
        $from = CarbonImmutable::parse('2025-10-18 10:30:00');
        $to = CarbonImmutable::parse('2025-10-19 11:00:00');

        // Act
        $range = DateRange::of($from, $to);

        // Mutação "simulada" das variáveis locais (CarbonImmutable não altera o instance original; criamos novos instantes)
        $fromModified = $from->addDay(); // novo CarbonImmutable
        $toModified = $to->subDay();

        // Assert - o DateRange deve continuar referenciando os instantes normalizados a partir dos valores originais,
        // sem ser afetado pelas reatribuições locais/novas instâncias geradas depois da construção.
        $this->assertSame(
            $from->format('Y-m-d'),
            $range->from->format('Y-m-d'),
            'DateRange.from deve referenciar a data original (normalizada) e não refletir alterações em variáveis locais posteriores.'
        );
        $this->assertSame('00:00:00', $range->from->format('H:i:s'), 'from deve permanecer startOfDay.');

        $this->assertSame(
            $to->format('Y-m-d'),
            $range->to->format('Y-m-d'),
            'DateRange.to deve referenciar a data original (normalizada) e não refletir alterações em variáveis locais posteriores.'
        );
        $this->assertSame('23:59:59', $range->to->format('H:i:s'), 'to deve permanecer endOfDay.');

        // Sanity: as novas variáveis locais são diferentes (garante que testamos imutabilidade corretamente)
        $this->assertNotSame($fromModified->format('Y-m-d H:i:s'), $range->from->format('Y-m-d H:i:s'));
        $this->assertNotSame($toModified->format('Y-m-d H:i:s'), $range->to->format('Y-m-d H:i:s'));
    }
}
