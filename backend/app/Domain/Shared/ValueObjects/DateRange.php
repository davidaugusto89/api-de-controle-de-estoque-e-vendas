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
     */
    public static function of(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($from->startOfDay(), $to->endOfDay());
    }
}
