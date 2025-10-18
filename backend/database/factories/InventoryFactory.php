<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(10, 500),
            'last_updated' => now(),
            'version' => 0,
        ];
    }

    /**
     * Estado auxiliar: estoque vazio
     */
    public function empty(): static
    {
        return $this->state(fn () => ['quantity' => 0]);
    }

    /**
     * Estado auxiliar: estoque baixo
     */
    public function lowStock(): static
    {
        return $this->state(fn () => ['quantity' => $this->faker->numberBetween(1, 10)]);
    }

    /**
     * Estado auxiliar: estoque alto
     */
    public function highStock(): static
    {
        return $this->state(fn () => ['quantity' => $this->faker->numberBetween(200, 1000)]);
    }
}
