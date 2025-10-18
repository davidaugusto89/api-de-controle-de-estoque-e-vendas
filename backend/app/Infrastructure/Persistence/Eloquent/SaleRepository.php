<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repositório Eloquent para a agregação de Vendas.
 *
 * Responsável por operações de leitura e escrita no modelo Sale,
 * mantendo o controller/use-cases desacoplados do Eloquent.
 */
final class SaleRepository
{
    /**
     * Retorna uma venda pelo ID (sem itens carregados).
     */
    public function findById(int $id): ?Sale
    {
        return Sale::query()->find($id);
    }

    /**
     * Retorna uma venda pelo ID com relacionamento de itens carregado.
     */
    public function findWithItems(int $id): ?Sale
    {
        return Sale::query()
            ->with('items')
            ->find($id);
    }

    /**
     * Lista vendas paginadas, com filtros simples (opcional).
     *
     * @param  array{
     *   status?: string|null,
     *   date_start?: string|null,
     *   date_end?: string|null,
     *   per_page?: int|null
     * }  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Sale::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_start']) && ! empty($filters['date_end'])) {
            $query->whereBetween('created_at', [$filters['date_start'], $filters['date_end']]);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Cria uma nova venda.
     *
     * @param  array{
     *   total_amount: float|int|string,
     *   total_cost: float|int|string,
     *   total_profit: float|int|string,
     *   status: string
     * }  $data
     */
    public function create(array $data): Sale
    {
        /** @var Sale $sale */
        $sale = Sale::query()->create([
            'total_amount' => $data['total_amount'],
            'total_cost' => $data['total_cost'],
            'total_profit' => $data['total_profit'],
            'status' => $data['status'],
        ]);

        return $sale;
    }

    /**
     * Atualiza campos da venda.
     *
     * @param  array<string, mixed>  $data
     * @return bool true se houve atualização
     */
    public function update(int $id, array $data): bool
    {
        return (bool) Sale::query()
            ->whereKey($id)
            ->update($data);
    }

    /**
     * Remove uma venda.
     *
     * @return bool|null true em sucesso, false em falha, null se modelo não existir
     */
    public function delete(int $id): ?bool
    {
        $sale = $this->findById($id);

        return $sale?->delete();
    }

    /**
     * Retorna coleção de vendas por IDs.
     *
     * @param  array<int>  $ids
     * @return Collection<int, Sale>
     */
    public function findMany(array $ids): Collection
    {
        return Sale::query()
            ->whereIn('id', $ids)
            ->get();
    }
}
