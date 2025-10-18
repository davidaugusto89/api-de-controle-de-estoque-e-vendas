<?php

declare(strict_types=1);

namespace App\Application\Sales\UseCases;

use App\Infrastructure\Persistence\Eloquent\SaleRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Recupera detalhes de uma venda junto com os itens associados.
 *
 * Contrato:
 * - Entrada: int $saleId
 * - Saída: array com campos básicos da venda e uma lista de itens (ver phpdoc do método `execute`)
 * - Exceções: lança {@see \Illuminate\Database\Eloquent\ModelNotFoundException} quando não encontrar a venda
 *
 * Observações:
 * - A implementação delega a busca ao repositório {@see App\Infrastructure\Persistence\Eloquent\SaleRepository}
 *   que deve otimizar carregamento de items (eager loading) para evitar N+1.
 */
final class GetSaleDetails
{
    public function __construct(
        private readonly SaleRepository $sales
    ) {}

    /**
     * @return array{
     *   id:int,
     *   total_amount:float,
     *   total_cost:float,
     *   total_profit:float,
     *   status:string,
     *   created_at:string,
     *   items: array<int, array{
     *     product_id:int,
     *     quantity:int,
     *     unit_price:float,
     *     unit_cost:float
     *   }>
     * }
     */
    public function execute(int $saleId): array
    {
        $sale = $this->sales->findWithItems($saleId);

        if (! $sale) {
            throw new ModelNotFoundException('Venda não encontrada.');
        }

        return [
            'id' => $sale->id,
            'total_amount' => (float) $sale->total_amount,
            'total_cost' => (float) $sale->total_cost,
            'total_profit' => (float) $sale->total_profit,
            'status' => (string) $sale->status,
            'created_at' => $sale->created_at?->toISOString() ?? '',
            'items' => $sale->items->map(fn ($i) => [
                'product_id' => (int) $i->product_id,
                'quantity' => (int) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'unit_cost' => (float) $i->unit_cost,
            ])->all(),
        ];
    }
}
