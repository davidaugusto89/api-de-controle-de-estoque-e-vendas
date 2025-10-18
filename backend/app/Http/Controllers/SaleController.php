<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Sales\UseCases\CreateSale;
use App\Http\Requests\CreateSaleRequest;
use App\Http\Resources\SaleResource;
use App\Infrastructure\Persistence\Queries\SaleDetailsQuery;
use Illuminate\Http\JsonResponse;

/**
 * Controller responsável por operações relacionadas a vendas.
 */
final class SaleController extends Controller
{
    /**
     * Recebe uma requisição de venda e dispara o caso de uso de criação.
     *
     * @param  CreateSaleRequest  $request  Request validado contendo os itens da venda
     * @param  CreateSale  $useCase  Caso de uso responsável por criar a venda (assíncrono/reativo no domínio)
     * @return JsonResponse Retorna 202 Accepted com o identificador da venda e status pendente
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
     * Recupera os detalhes de uma venda por ID.
     *
     * @param  int  $id  ID da venda
     * @param  SaleDetailsQuery  $query  Query para recuperar detalhes de venda com joins apropriados
     * @return JsonResponse 200 com recurso de venda ou 404 se não existir
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
