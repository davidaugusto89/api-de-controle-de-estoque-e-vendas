<?php

declare(strict_types=1);

namespace App\Application\Sales\UseCases;

use App\Domain\Sales\Services\MarginCalculator;
use App\Domain\Sales\Services\SaleValidator;
use App\Infrastructure\Events\SaleFinalized;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Database\Transactions;
use Illuminate\Support\Facades\Event;

/**
 * Caso de uso para finalizar uma venda.
 *
 * Calcula totais, valida regras de negócio e despacha eventos pós-finalização.
 */
final class FinalizeSale
{
    public function __construct(
        private readonly Transactions $tx,
        private readonly SaleValidator $validator,
        private readonly MarginCalculator $margin
    ) {}

    /**
     * Executa o processo de finalização da venda.
     *
     * @param  int  $saleId  ID da venda a ser finalizada
     */
    public function execute(int $saleId): void
    {
        $this->tx->run(function () use ($saleId) {
            /** @var Sale $sale */
            $sale = Sale::query()->lockForUpdate()->findOrFail($saleId);
            if ($sale->status === Sale::STATUS_COMPLETED) {
                return; // idempotência básica
            }

            $items = SaleItem::query()->where('sale_id', $sale->id)->get();

            // valida e calcula totais
            $this->validator->validate($items);

            $totalAmount = 0.0;
            $totalCost = 0.0;
            foreach ($items as $it) {
                $totalAmount += $it->unit_price * $it->quantity;
                $totalCost += $it->unit_cost * $it->quantity;
            }
            $totalProfit = $this->margin->profit($totalAmount, $totalCost);

            // persiste totais + status
            $sale->total_amount = round($totalAmount, 2);
            $sale->total_cost = round($totalCost, 2);
            $sale->total_profit = round($totalProfit, 2);
            $sale->status = Sale::STATUS_COMPLETED;
            $sale->save();

            // Emite evento para atualizar estoque de forma assíncrona
            Event::dispatch(new SaleFinalized(
                saleId: $sale->id,
                items: $items->map(fn ($i) => [
                    'product_id' => (int) $i->product_id,
                    'quantity' => (int) $i->quantity,
                ])->all()
            ));
        });
    }
}
