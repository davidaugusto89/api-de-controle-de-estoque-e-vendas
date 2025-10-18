<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reports\UseCases\GenerateSalesReport;
use App\Http\Resources\ReportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller de relatórios.
 *
 * Expõe endpoints para geração de relatórios agregados do domínio de vendas.
 */
final class ReportController extends Controller
{
    /**
     * @param  GenerateSalesReport  $report  Serviço que gera relatórios de vendas
     */
    public function __construct(
        private readonly GenerateSalesReport $report
    ) {}

    /**
     * GET /api/reports/sales
     * Query params obrigatórios do teste: start_date (YYYY-MM-DD), end_date (YYYY-MM-DD), product_sku (opcional)
     * Extras opcionais: top (int), order_by (string), cache_ttl (int seconds)
     */
    public function sales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // requisitos do teste
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'product_sku' => ['nullable', 'string', 'max:100'],

            // opcionais seus
            'top' => ['nullable', 'integer', 'min:1', 'max:1000'],
            // 'order_by'  => ['nullable', Rule::in(['total_amount', 'total_profit', 'quantity', 'sku', 'date'])],
            'cache_ttl' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        // mapeamento para o caso de uso (aceitando também aliases antigos para compatibilidade, se quiser)
        $payload = [
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'product_sku' => $validated['product_sku'] ?? null,
            'top' => $validated['top'] ?? (int) $request->query('top', 100),
            'order_by' => $validated['order_by'] ?? 'date',
            'cache_ttl' => $validated['cache_ttl'] ?? 300,
        ];

        $data = $this->report->handle($payload);

        // sempre JSON (API-only), 200 OK
        return response()->json((new ReportResource($data))->resolve(), 200);
    }
}
