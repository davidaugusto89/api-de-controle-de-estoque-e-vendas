<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Sales\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        // Dica: mantenha valores determinísticos por padrão.
        // Nada de criar itens aqui e nem recalcular totais.
        // Assim, a factory "crua" é previsível para unit tests.
        $created = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'total_amount' => '0.00',
            'total_cost'   => '0.00',
            'total_profit' => '0.00',
            'status'       => SaleStatus::QUEUED->value,
            'created_at'   => $created,
            'updated_at'   => $created,
        ];
    }

    // --- Estados de status ----------------------------------------------

    public function queued(): static
    {
        return $this->state(fn () => ['status' => SaleStatus::QUEUED->value]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => SaleStatus::PROCESSING->value]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => SaleStatus::COMPLETED->value]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => SaleStatus::CANCELLED->value]);
    }

    // --- Estados de data -------------------------------------------------

    /**
     * Define created_at/updated_at para um dia específico (e deixa o DB gerar sale_date, se for coluna gerada).
     */
    public function onDate(\DateTimeInterface|string $when): static
    {
        $ts = $when instanceof \DateTimeInterface ? $when : new \DateTimeImmutable($when);

        return $this->state(fn () => [
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
    }

    // --- Estados de itens ------------------------------------------------

    /**
     * Anexa N itens sem recalcular totais. Útil quando o teste vai validar lógica própria.
     */
    public function withItems(int $count = 1): static
    {
        return $this->has(SaleItem::factory()->count($count), 'items');
    }

    /**
     * Anexa 1–4 itens (aleatório) sem recalcular totais.
     */
    public function withRandomItems(): static
    {
        $count = $this->faker->numberBetween(1, 4);

        return $this->withItems($count);
    }

    /**
     * Após criar (create()), recalcula os totais a partir dos itens.
     * Combine com withItems()/withRandomItems() quando precisar de valores coerentes.
     */
    public function recalculateTotals(): static
    {
        return $this->afterCreating(function (Sale $sale) {
            /** @var Collection<int, SaleItem> $items */
            $items = $sale->items()->get();

            // Se não houver itens, mantemos zero para não surpreender.
            if ($items->isEmpty()) {
                return;
            }

            // Usa bc* para precisão decimal determinística.
            $totalAmount = $items->reduce(
                fn (string $sum, SaleItem $i) => bcadd($sum, bcmul((string) $i->quantity, (string) $i->unit_price, 2), 2),
                '0.00'
            );

            $totalCost = $items->reduce(
                fn (string $sum, SaleItem $i) => bcadd($sum, bcmul((string) $i->quantity, (string) $i->unit_cost, 2), 2),
                '0.00'
            );

            $totalProfit = bcsub($totalAmount, $totalCost, 2);

            // Evita eventos/listeners durante testes/seed
            $sale->updateQuietly([
                'total_amount' => $totalAmount,
                'total_cost'   => $totalCost,
                'total_profit' => $totalProfit,
            ]);
        });
    }
}
