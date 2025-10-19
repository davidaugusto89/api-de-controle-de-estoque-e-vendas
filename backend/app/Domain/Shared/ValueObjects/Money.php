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

    /**
     * @param  float|int|string  $value  Valor monetário
     */
    public function __construct(float|int|string $value)
    {
        $this->value = $this->normalize($value);
    }

    /**
     * Retorna o valor monetário como float
     *
     * @return float Valor monetário como float
     */
    public function asFloat(): float
    {
        return $this->value;
    }

    /**
     * Soma dois valores monetários
     *
     * @param  self  $other  Outro valor monetário
     * @return self Novo valor monetário
     */
    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    /**
     * Subtrai dois valores monetários
     *
     * @param  self  $other  Outro valor monetário
     * @return self Novo valor monetário
     */
    public function sub(self $other): self
    {
        return new self($this->value - $other->value);
    }

    /**
     * Retorna o valor monetário formatado como string com 2 casas decimais
     *
     * @return string Valor monetário formatado
     */
    public function __toString(): string
    {
        return number_format($this->value, 2, '.', '');
    }

    /**
     * Normaliza o valor para float com 2 casas decimais
     *
     * @param  float|int|string  $value  Valor a ser normalizado
     * @return float Valor normalizado
     */
    private function normalize(float|int|string $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return round((float) $value, 2);
    }
}
