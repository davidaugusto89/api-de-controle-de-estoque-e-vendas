<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Inventory\Exceptions\InventoryInsufficientException;
use App\Models\Inventory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repositório Eloquent para o modelo Inventory.
 *
 * Fornece operações de busca/upsert e decremento atômico usadas pelos serviços de domínio.
 */
class InventoryRepository
{
    /**
     * Recupera registro de inventário pelo id do produto.
     */
    public function findByProductId(int $productId): ?Inventory
    {
        /** @var Inventory|null $inv */
        $inv = Inventory::query()->where('product_id', $productId)->first();

        return $inv;
    }

    /**
     * Insere ou atualiza o inventário de um produto e atualiza version/last_updated.
     */
    public function upsertByProductId(int $productId, int $quantity, ?Carbon $lastUpdated = null): Inventory
    {
        $lastUpdated = $lastUpdated ?? Carbon::now();

        $inv = $this->findByProductId($productId);

        if (! $inv) {
            $inv = new Inventory([
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
            $inv->version = 0;
        } else {
            $inv->quantity = $quantity;
        }

        $inv->last_updated = $lastUpdated;
        $inv->version = (int) $inv->version + 1;
        $inv->save();

        return $inv;
    }

    /**
     * Decrementa atomica mente a quantidade se houver estoque suficiente.
     * Retorna true quando a atualização afetou uma linha.
     */
    public function decrementIfEnough(int $productId, int $quantity): bool
    {
        // Usar uma query parametrizada para evitar interpolação direta e permitir bindings.
        $now = now();

        $sql = 'UPDATE inventory SET quantity = quantity - ?, version = version + 1, last_updated = ?, updated_at = ? WHERE product_id = ? AND quantity >= ?';

        $affected = DB::update($sql, [$quantity, $now, $now, $productId, $quantity]);

        // Log para auditoria rápida em ambiente de debug/produção.
        if ($affected) {
            Log::info('InventoryRepository: decrementIfEnough applied', ['product_id' => $productId, 'quantity' => $quantity, 'affected' => $affected]);
        } else {
            Log::warning('InventoryRepository: decrementIfEnough no rows affected (possible insufficient stock)', ['product_id' => $productId, 'quantity' => $quantity]);
        }

        return (bool) $affected;
    }

    /**
     * Decrementa a quantidade ou lança DomainException se não for possível.
     * Método utilitário para tornar o uso no job mais explícito e legível.
     *
     * @throws \DomainException
     */
    public function decrementOrFail(int $productId, int $quantity): void
    {
        if (! $this->decrementIfEnough($productId, $quantity)) {
            Log::warning('InventoryRepository: decrementOrFail throwing insufficient exception', ['product_id' => $productId, 'quantity' => $quantity]);
            throw InventoryInsufficientException::forProduct($productId);
        }
    }
}
