<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Sales\UseCases\CreateSale;
use App\Http\Requests\CreateSaleRequest;
use App\Http\Resources\SaleResource;
use App\Infrastructure\Persistence\Queries\SaleDetailsQuery;
use Illuminate\Http\JsonResponse;

/**
 * Controller de vendas.
 *
 * Endpoints para criação de vendas. Utiliza CreateSale para criação e
 * SaleDetailsQuery para leitura de detalhes.
 */
final class SaleController extends Controller
{
    /**
     * Recebe uma requisição de venda e aciona o caso de uso CreateSale.
     *
     * @return JsonResponse Resposta com status 202 e id da venda criada.
     */
    public function store(CreateSaleRequest $request, CreateSale $useCase): JsonResponse
    {
        $saleId = $useCase->execute($request->validated()['items']);

        return response()->json([
            'message' => 'Venda recebida e será processada.',
            'sale_id' => $saleId,
            'status' => 'pending',
        ], 202);
    }

    /**
     * Recupera detalhes da venda por id.
     *
     * @param  int  $id  ID da venda
     * @return JsonResponse Resposta com detalhes da venda ou erro 404 se não encontrada
     */
    public function show(int $id, SaleDetailsQuery $query): JsonResponse
    {
        $row = $query->byId($id);

        if ($row === null) {
            return response()->json([
                'error' => [
                    'code' => 'SaleNotFound',
                    'message' => 'Venda não encontrada.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => (new SaleResource($row))->resolve(),
        ]);
    }
}
