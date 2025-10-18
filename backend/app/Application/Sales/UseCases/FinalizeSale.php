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
 * Finaliza uma venda: valida, calcula totais e emite evento de pós-processamento.
 *
 * Resumo:
 * - Carrega a venda (com lock), valida itens via {@see App\Domain\Sales\Services\SaleValidator},
 *   calcula totais e margens via {@see App\Domain\Sales\Services\MarginCalculator} e
 *   marca a venda como `completed` persistindo totals.
 * - Emite {@see App\Infrastructure\Events\SaleFinalized} após confirmação para
 *   acionar listeners (p.ex. atualização de inventário, geração de notas, integrações).
 *
 * Contrato:
 * - Entrada: int $saleId
 * - Saída: void
 * - Efeitos colaterais: persistência de totais em `sales`, mudança de estado e dispatch de evento
 *
 * Garantias e observações:
 * - Opera dentro de {@see App\Support\Database\Transactions} para garantir atomicidade.
 * - Utiliza `lockForUpdate()` para evitar condições de corrida; o método é idempotente
 *   (retorna sem ação se a venda já estiver em status `completed`).
 * - A atualização efetiva do inventário é responsabilidade dos listeners do evento `SaleFinalized`;
 *   em ambientes distribuídos, estes listeners devem garantir execução transacional ou compensatória.
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
