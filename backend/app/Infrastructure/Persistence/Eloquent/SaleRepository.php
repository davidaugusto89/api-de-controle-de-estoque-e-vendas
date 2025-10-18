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
     * Recupera uma venda por id.
     */
    public function findById(int $id): ?Sale
    {
        return Sale::query()->find($id);
    }

    /**
     * Recupera uma venda por id com itens carregados (eager-load).
     */
    public function findWithItems(int $id): ?Sale
    {
        return Sale::query()
            ->with('items')
            ->find($id);
    }

    /**
     * Pagina vendas com filtros opcionais.
     *
     * @param  array{status?: string|null, date_start?: string|null, date_end?: string|null, per_page?: int|null}  $filters
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
     * Cria um novo registro de venda.
     *
     * @param  array{total_amount: float|int|string, total_cost: float|int|string, total_profit: float|int|string, status: string}  $data
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
     * Atualiza campos da venda por id.
     *
     * @param  array<string, mixed>  $data
     * @return bool True se a atualização afetou uma linha
     */
    public function update(int $id, array $data): bool
    {
        return (bool) Sale::query()
            ->whereKey($id)
            ->update($data);
    }

    /**
     * Remove venda por id.
     *
     * @return bool|null True em sucesso, false em falha, null se não existir
     */
    public function delete(int $id): ?bool
    {
        $sale = $this->findById($id);

        return $sale?->delete();
    }

    /**
     * Retorna coleção de vendas por ids.
     *
     * @param  int[]  $ids
     * @return Collection<int, Sale>
     */
    public function findMany(array $ids): Collection
    {
        return Sale::query()
            ->whereIn('id', $ids)
            ->get();
    }
}
