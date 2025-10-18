<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

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

    public static function of(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self($from->startOfDay(), $to->endOfDay());
    }
}
