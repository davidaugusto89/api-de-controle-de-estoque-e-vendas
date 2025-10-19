<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Inventory\UseCases\RegisterStockEntry;
use App\Http\Requests\RegisterInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Persistence\Queries\InventoryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controller de inventário.
 *
 * Endpoints para consulta e criação de registros de inventário. Utiliza
 * InventoryQuery para leitura e InventoryCache para cache/invalidação.
 */
final class InventoryController extends Controller
{
    /**
     * Lista registros de inventário.
     *
     * Suporta paginação via `per_page` e `page`. Quando `per_page=0` retorna
     * uma lista não paginada (limitada por um cap do servidor) e inclui totais.
     */
    public function index(Request $request, InventoryQuery $query, InventoryCache $cache): JsonResponse
    {
        $search  = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $perPage = (int) max(0, $request->integer('per_page', 15));
        $page    = (int) max(1, $request->integer('page', 1));

        // Cap para listas não paginadas
        $MAX_UNPAGED = 5000;

        if ($perPage === 0) {
            // Cache de lista + totais por "q"
            [$items, $totals] = $cache->rememberListAndTotalsUnpaged(
                $search,
                function () use ($query, $search, $MAX_UNPAGED) {
                    $items  = $query->list($search, $MAX_UNPAGED); // limite aplicado no SQL
                    $totals = $query->totals($search);              // { total_cost, total_sale, projected_profit }

                    return [$items, $totals];
                }
            );

            $data = InventoryResource::collection(collect($items))->resolve();

            return response()->json([
                'data' => $data,
                'meta' => [
                    'pagination' => null,
                    'query'      => ['q' => $search, 'per_page' => $perPage, 'page' => $page],
                    'totals'     => $totals,
                ],
            ])->withHeaders([
                'Cache-Control' => 'public, max-age=30',
            ]);
        }

        // Paginação padrão (cachear dados + metadados simples, não o paginator em si)
        [$pageItems, $pageMeta, $totals] = $cache->rememberListAndTotalsPaged(
            $search,
            $perPage,
            $page,
            function () use ($query, $perPage, $search, $page) {
                // Atenção: garanta que seu InventoryQuery::paginate aceite page explicitamente,
                // ou use o Request global. Aqui passamos page explicitamente.
                $paginator = $query->paginate(perPage: $perPage, search: $search, page: $page);

                return [
                    $paginator->items(),
                    [
                        'current_page' => $paginator->currentPage(),
                        'per_page'     => $paginator->perPage(),
                        'total'        => $paginator->total(),
                        'last_page'    => $paginator->lastPage(),
                    ],
                    $query->totals($search),
                ];
            }
        );

        $data = InventoryResource::collection(collect($pageItems))->resolve();

        return response()->json([
            'data' => $data,
            'meta' => [
                'pagination' => $pageMeta,
                'query'      => ['q' => $search, 'per_page' => $perPage, 'page' => $page],
                'totals'     => $totals,
            ],
        ])->withHeaders([
            'Cache-Control' => 'public, max-age=30',
        ]);
    }

    /**
     * Exibe inventário para um dado produto.
     */
    public function show(int $productId, InventoryQuery $query, InventoryCache $cache): JsonResponse
    {
        $row = $cache->rememberItem($productId, fn () => $query->byProductId($productId));

        if ($row === null) {
            return response()->json([
                'error' => [
                    'code'    => 'InventoryNotFound',
                    'message' => 'Inventário não encontrado para o produto informado.',
                ],
            ], 404);
        }

        $data = (new InventoryResource($row))->resolve();

        return response()->json(['data' => $data])
            ->withHeaders(['Cache-Control' => 'public, max-age=30']);
    }

    /**
     * Registra uma entrada de estoque.
     *
     * Executa RegisterStockEntry e invalida caches relacionados. Exceções são
     * registradas e retornam resposta genérica 500.
     */
    public function store(
        RegisterInventoryRequest $request,
        RegisterStockEntry $useCase,
        InventoryCache $cache
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $payload = $useCase->execute(
                productId: (int) $validated['product_id'],
                quantity: (int) $validated['quantity'],
                unitCost: array_key_exists('unit_cost', $validated) ? (float) $validated['unit_cost'] : null
            );

            // Invalida o cache do item e listas relacionadas ao produto
            $cache->invalidateByProducts([(int) $validated['product_id']]);

            return response()->json([
                'message' => 'Entrada de estoque registrada com sucesso.',
                'data'    => (new InventoryResource($payload))->resolve(),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Erro ao registrar entrada de estoque', [
                'product_id' => $validated['product_id'] ?? null,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code'    => 'StockEntryError',
                    'message' => 'Erro ao registrar a entrada de estoque.',
                ],
            ], 500);
        }
    }
}
