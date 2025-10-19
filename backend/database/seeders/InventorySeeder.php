<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();

        foreach ($products as $product) {
            Inventory::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'quantity'     => fake()->numberBetween(10, 200),
                    'last_updated' => now()->subDays(fake()->numberBetween(0, 30)),
                    'version'      => 1,
                ]
            );
        }
    }
}
