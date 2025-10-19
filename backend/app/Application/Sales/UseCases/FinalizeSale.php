<?php

declare(strict_types=1);

namespace App\Application\Sales\UseCases;

use App\Domain\Sales\Enums\SaleStatus;
use App\Domain\Sales\Services\MarginCalculator;
use App\Domain\Sales\Services\SaleValidator;
use App\Infrastructure\Events\SaleFinalized;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Database\Transactions;
use Illuminate\Support\Facades\Event;

/**
 * Finaliza uma venda: valida itens, calcula totais e emite evento.
 */
class FinalizeSale
{
    public function __construct(
        private readonly Transactions $tx,
        private readonly SaleValidator $validator,
        private readonly MarginCalculator $margin
    ) {}

    /**
     * Conclui a venda e persiste totais; idempotente para vendas já concluídas.
     *
     * @throws \Throwable
     */
    public function execute(int $saleId): void
    {
        $this->tx->run(function () use ($saleId): void {
            /** @var Sale $sale */
            $sale = Sale::query()->lockForUpdate()->findOrFail($saleId);
            if ($sale->status === SaleStatus::COMPLETED->value) {
                return;
            }

            $items = SaleItem::query()
                ->where('sale_id', $sale->id)
                ->get();

            $this->validator->validate($items);

            $totalAmount = 0.0;
            $totalCost   = 0.0;

            foreach ($items as $it) {
                $totalAmount += $it->unit_price * $it->quantity;
                $totalCost   += $it->unit_cost  * $it->quantity;
            }

            $totalProfit = $this->margin->profit($totalAmount, $totalCost);

            $sale->total_amount = round($totalAmount, 2);
            $sale->total_cost   = round($totalCost, 2);
            $sale->total_profit = round($totalProfit, 2);
            $sale->status       = SaleStatus::COMPLETED->value;
            $sale->save();

            Event::dispatch(new SaleFinalized(
                saleId: $sale->id,
                items: $items->map(
                    static fn ($i): array => [
                        'product_id' => (int) $i->product_id,
                        'quantity'   => (int) $i->quantity,
                    ]
                )->all()
            ));
        });
    }

    /**
     * Atalho invocável para permitir uso como callable nos testes.
     *
     * @throws \Throwable
     */
    public function __invoke(int $saleId): void
    {
        $this->execute($saleId);
    }
}
