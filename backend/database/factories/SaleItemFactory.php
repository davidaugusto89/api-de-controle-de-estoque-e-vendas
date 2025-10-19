<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        // Quantidade pequena e preços com 2 casas.
        return [
            'quantity' => $this->faker->numberBetween(1, 5),
            'unit_price' => $this->formatMoney($this->faker->randomFloat(2, 5, 50)),
            'unit_cost' => $this->formatMoney($this->faker->randomFloat(2, 2, 40)),
        ];
    }

    private function formatMoney(float $v): string
    {
        // Garante string com 2 casas
        return number_format($v, 2, '.', '');
    }
}
