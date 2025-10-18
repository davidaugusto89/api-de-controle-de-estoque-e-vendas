<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();

        $totalSales = 15;

        // Cria vendas com itens usando factory
        Sale::factory()
            ->count($totalSales)
            ->create()
            ->each(function (Sale $sale) use ($products) {
                // seleciona entre 1 e 5 produtos aleatórios
                $items = $products->random(rand(1, 5));

                foreach ($items as $product) {
                    $qty = fake()->numberBetween(1, 10);

                    SaleItem::create([
                        'sale_id'    => $sale->id,
                        'product_id' => $product->id,
                        'quantity'   => $qty,
                        'unit_cost'  => $product->cost_price,
                        'unit_price' => $product->sale_price,
                    ]);
                }

                // recalcula totais
                $totals = $sale->items()
                    ->selectRaw('SUM(quantity * unit_price) as total_amount, SUM(quantity * unit_cost) as total_cost')
                    ->first();

                $totalAmount = (float) ($totals->total_amount ?? 0);
                $totalCost   = (float) ($totals->total_cost ?? 0);
                $totalProfit = $totalAmount - $totalCost;

                $sale->forceFill([
                    'total_amount' => $totalAmount,
                    'total_cost'   => $totalCost,
                    'total_profit' => $totalProfit,
                ])->save();
            });
    }
}
