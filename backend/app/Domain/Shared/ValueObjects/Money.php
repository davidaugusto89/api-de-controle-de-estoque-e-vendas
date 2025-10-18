<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

/**
 * Value Object para representar valores monetários.
 * Garante formatação consistente com duas casas decimais.
 */
final class Money
{
    private float $value;

    public function __construct(float|int|string $value)
    {
        $this->value = $this->normalize($value);
    }

    public function asFloat(): float
    {
        return $this->value;
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function sub(self $other): self
    {
        return new self($this->value - $other->value);
    }

    public function __toString(): string
    {
        return number_format($this->value, 2, '.', '');
    }

    private function normalize(float|int|string $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return round((float) $value, 2);
    }
}
