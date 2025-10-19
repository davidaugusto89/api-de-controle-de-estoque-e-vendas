<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Value Object representando um intervalo de datas imutável.
 */
final class DateRange
{
    /**
     * @param  CarbonImmutable  $from  Data inicial
     * @param  CarbonImmutable  $to  Data final
     *
     * @throws InvalidArgumentException Se from for maior que to
     */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to
    ) {
        if ($from->gt($to)) {
            throw new InvalidArgumentException('DateRange inválido: from > to.');
        }
    }

    /**
     * Cria um intervalo normalizado para o dia completo.
     *
     * @param  CarbonImmutable  $from  Data inicial
     * @param  CarbonImmutable  $to  Data final
     * @return self Novo DateRange com from no início do dia e to no final do dia
     */
    public static function of(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($from->startOfDay(), $to->endOfDay());
    }
}
