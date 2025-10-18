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
 * Controller responsável pelas operações relacionadas ao inventário.
 *
 * Fornece endpoints para listar itens de inventário, exibir um item
 * por produto e registrar entradas de estoque. Utiliza `InventoryQuery`
 * para leitura persistente e `InventoryCache` para cache de listas e itens.
 */
final class InventoryController extends Controller
{
    /**
     * Listar registros de inventário.
     *
     * Suporta paginação via query params `per_page` e `page`. Quando `per_page=0`
     * retorna uma lista não paginada (até um limite máximo) e inclui totais.
     *
     * @param  Request  $request  Instância de request HTTP com parâmetros de busca e paginação
     * @param  InventoryQuery  $query  Objeto de consulta para recuperar dados do repositório
     * @param  InventoryCache  $cache  Serviço de cache para listas e itens de inventário
     * @return JsonResponse Resposta JSON contendo os dados, metadados de paginação e totais
     */
    public function index(Request $request, InventoryQuery $query, InventoryCache $cache): JsonResponse
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $perPage = (int) max(0, $request->integer('per_page', 15));
        $page = (int) max(1, $request->integer('page', 1));

        // Cap para listas não paginadas
        $MAX_UNPAGED = 5000;

        if ($perPage === 0) {
            // Cache de lista + totais por "q"
            [$items, $totals] = $cache->rememberListAndTotalsUnpaged(
                $search,
                function () use ($query, $search, $MAX_UNPAGED) {
                    $items = $query->list($search, $MAX_UNPAGED); // limite aplicado no SQL
                    $totals = $query->totals($search);              // { total_cost, total_sale, projected_profit }

                    return [$items, $totals];
                }
            );

            $data = InventoryResource::collection(collect($items))->resolve();

            return response()->json([
                'data' => $data,
                'meta' => [
                    'pagination' => null,
                    'query' => ['q' => $search, 'per_page' => $perPage, 'page' => $page],
                    'totals' => $totals,
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
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
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
                'query' => ['q' => $search, 'per_page' => $perPage, 'page' => $page],
                'totals' => $totals,
            ],
        ])->withHeaders([
            'Cache-Control' => 'public, max-age=30',
        ]);
    }

    /**
     * Exibir inventário por produto.
     *
     * @param  int  $productId  ID do produto
     * @param  InventoryQuery  $query  Objeto de consulta para recuperar o inventário
     * @param  InventoryCache  $cache  Serviço de cache para o item solicitado
     * @return JsonResponse Retorna 200 com o recurso de inventário ou 404 se não encontrado
     */
    public function show(int $productId, InventoryQuery $query, InventoryCache $cache): JsonResponse
    {
        $row = $cache->rememberItem($productId, fn () => $query->byProductId($productId));

        if ($row === null) {
            return response()->json([
                'error' => [
                    'code' => 'InventoryNotFound',
                    'message' => 'Inventário não encontrado para o produto informado.',
                ],
            ], 404);
        }

        $data = (new InventoryResource($row))->resolve();

        return response()->json(['data' => $data])
            ->withHeaders(['Cache-Control' => 'public, max-age=30']);
    }

    /**
     * Registrar uma entrada de estoque.
     *
     * Valida o request via `RegisterInventoryRequest`, executa o caso de uso
     * `RegisterStockEntry` e invalida cache relevante. Em caso de erro, registra
     * a exceção no logger e retorna erro 500 genérico.
     *
     * @param  RegisterInventoryRequest  $request  Request validado contendo product_id, quantity e opcional unit_cost
     * @param  RegisterStockEntry  $useCase  Caso de uso que registra a entrada de estoque
     * @param  InventoryCache  $cache  Serviço de cache para invalidação pós-gravação
     * @return JsonResponse 201 com recurso criado ou 500 em caso de falha
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
                'data' => (new InventoryResource($payload))->resolve(),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Erro ao registrar entrada de estoque', [
                'product_id' => $validated['product_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'StockEntryError',
                    'message' => 'Erro ao registrar a entrada de estoque.',
                ],
            ], 500);
        }
    }
}
