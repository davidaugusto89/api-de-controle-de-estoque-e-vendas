<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $created = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'total_amount' => '0.00',
            'total_cost'   => '0.00',
            'total_profit' => '0.00',
            'status'       => Sale::STATUS_COMPLETED,
            'created_at'   => $created,
            'updated_at'   => $created,
        ];
    }

    /**
     * Cria 1–4 itens e recalcula totais SEM nova query.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Sale $sale) {
            $itemsCount = fake()->numberBetween(1, 4);

            /** @var \Illuminate\Database\Eloquent\Collection<int, SaleItem> $items */
            $items = SaleItem::factory()
                ->count($itemsCount)
                ->for($sale)
                ->create();

            $totalAmount = $items->reduce(
                fn ($sum, SaleItem $i) => bcadd($sum, bcmul((string) $i->quantity, (string) $i->unit_price, 2), 2),
                '0.00'
            );

            $totalCost = $items->reduce(
                fn ($sum, SaleItem $i) => bcadd($sum, bcmul((string) $i->quantity, (string) $i->unit_cost, 2), 2),
                '0.00'
            );

            $totalProfit = bcsub($totalAmount, $totalCost, 2);

            // evita eventos/listeners durante a seed
            $sale->updateQuietly([
                'total_amount' => $totalAmount,
                'total_cost'   => $totalCost,
                'total_profit' => $totalProfit,
            ]);
        });
    }

    // Estados úteis
    public function queued(): static
    {
        return $this->state(fn () => ['status' => Sale::STATUS_QUEUED]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => Sale::STATUS_PROCESSING]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => Sale::STATUS_COMPLETED]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Sale::STATUS_CANCELLED]);
    }
}
