<?php

declare(strict_types=1);

namespace App\Application\Sales\UseCases;

use App\Infrastructure\Jobs\FinalizeSaleJob;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Database\Transactions;

/**
 * Cria uma nova venda e enfileira a finalização assíncrona.
 *
 * Resumo:
 * - Persiste uma entidade `Sale` em estado enfileirado e insere os itens
 *   correspondentes em `sale_items` dentro de uma transação.
 * - Valida existência dos produtos usados no payload e normaliza preços (payload > preço do produto).
 * - Despacha um job (`FinalizeSaleJob`) na fila `sales` para realizar a finalização/conciliacão posteriormente.
 *
 * Contrato:
 * - Entrada: array<int, array{product_id:int, quantity:int, unit_price?:?float}> $items
 * - Saída: int ID da venda criada
 * - Efeitos colaterais: gravação em `sales`/`sale_items` e dispatch de job para fila
 *
 * Observações:
 * - A operação roda em transação via {@see App\Support\Database\Transactions} para garantir atomicidade.
 * - O preço unitário preferido é o enviado no payload; quando ausente, usa-se `product.sale_price`.
 * - A finalização da venda é delegada ao job enfileirado; escolha de prioridade/queue deve ser avaliada conforme carga.
 */
final class CreateSale
{
    /**
     * @param  Transactions  $tx  Helper de transações capaz de executar transactions no BD
     * @param  FinalizeSale  $finalizeSale  Caso de uso responsável pela finalização da venda
     */
    public function __construct(
        private readonly Transactions $tx,
        private readonly FinalizeSale $finalizeSale
    ) {}

    /**
     * Cria uma nova venda com itens e enfileira o job de finalização.
     *
     * Contrato do parâmetro `$items`:
     * - É um array indexado com chaves inteiras; cada item deve conter ao menos:
     *   - product_id: int (ID do produto)
     *   - quantity: int (quantidade)
     *   - unit_price?: ?float (preço unitário opcional; quando informado, prevalece)
     *
     * Retorno:
     * - int: ID da venda recém-criada (status inicial: queued)
     *
     * Efeitos colaterais e exceções:
     * - Insere registros em `sale_items` e cria um `Sale` dentro de uma transação;
     * - Despacha {@see App\Infrastructure\Jobs\FinalizeSaleJob} para a fila `sales`.
     * - Não lança exceções explícitas aqui, mas falhas na transação/BD serão propagadas.
     */
    public function execute(array $items): int
    {
        // valida existência básica de produtos e normaliza preços
        $productMap = Product::query()
            ->whereIn('id', array_column($items, 'product_id'))
            ->get(['id', 'sale_price', 'cost_price'])
            ->keyBy('id');

        return $this->tx->run(function () use ($items, $productMap) {
            /** @var Sale $sale */
            $sale = new Sale;
            $sale->status = Sale::STATUS_QUEUED;
            $sale->total_amount = 0;
            $sale->total_cost = 0;
            $sale->total_profit = 0;
            $sale->save();

            $rows = [];
            foreach ($items as $it) {
                $p = $productMap[$it['product_id']] ?? null;
                // preço unitário: payload > produto
                $unitPrice = isset($it['unit_price']) ? (float) $it['unit_price'] : (float) ($p?->sale_price ?? 0);
                $rows[] = [
                    'sale_id' => $sale->id,
                    'product_id' => (int) $it['product_id'],
                    'quantity' => (int) $it['quantity'],
                    'unit_price' => $unitPrice,
                    'unit_cost' => (float) ($p?->cost_price ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows) {
                SaleItem::query()->insert($rows);
            }

            // Despacha a FINALIZAÇÃO para a fila "sales"
            dispatch(new FinalizeSaleJob($sale->id))->onQueue('sales');

            return $sale->id;
        });
    }
}
