<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

/**
 * VO simples para valores monetários (não persistido).
 * Mantém invariante de 2 casas quando formatado.
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

    public function add(Money $other): Money
    {
        return new self($this->value + $other->value);
    }

    public function sub(Money $other): Money
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
            $value = str_replace(['.', ','], ['.', '.'], str_replace(',', '.', $value));
        }

        return (float) $value;
    }
}
