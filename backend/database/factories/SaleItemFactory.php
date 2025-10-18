<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        // garante produto coerente; pode ser sobrescrito ao usar a factory
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();

        $qty = fake()->numberBetween(1, 5);
        $unitCost = (float) $product->cost_price;
        $unitPrice = (float) $product->sale_price;

        return [
            'sale_id' => Sale::factory(),
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'unit_price' => $unitPrice,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** Estado para quantidade maior (teste de volume) */
    public function bulk(): static
    {
        return $this->state(fn () => ['quantity' => fake()->numberBetween(6, 20)]);
    }
}
