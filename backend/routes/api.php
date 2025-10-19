<?php

declare(strict_types=1);

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::middleware([
    // Garanta que estes dois estão registrados no Kernel ou aplique aqui:
    // \App\Http\Middleware\ForceJsonResponse::class,
    // \App\Http\Middleware\RequestId::class,
    'throttle:api', // fallback geral; específicos abaixo
])->prefix('v1')->group(function () {

    /**
     * Healthcheck: GET /api/v1/up
     */
    Route::get('/up', function () {
        return response()->json([
            'status'    => 'ok',
            'message'   => 'API operacional 🚀',
            'timestamp' => now()->toISOString(),
        ]);
    })->name('health.up');

    /**
     * Inventário
     * - POST /api/v1/inventory
     * - GET  /api/v1/inventory
     * - GET  /api/v1/inventory/{productId} (opcional: detalhe por produto)
     */
    Route::prefix('inventory')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])
            ->name('inventory.index')
            ->middleware('throttle:inventory-read');

        Route::get('/{productId}', [InventoryController::class, 'show'])
            ->whereNumber('productId')
            ->name('inventory.show')
            ->middleware('throttle:inventory-read');

        Route::post('/', [InventoryController::class, 'store'])
            ->name('inventory.store')
            ->middleware('throttle:inventory-write');
    });

    /**
     * Vendas
     * - POST /api/v1/sales
     * - GET  /api/v1/sales/{id}
     */
    Route::prefix('sales')->group(function () {
        Route::post('/', [SaleController::class, 'store'])
            ->name('sales.store')
            ->middleware('throttle:sales-write'); // mais restritivo

        Route::get('/{id}', [SaleController::class, 'show'])
            ->whereNumber('id')
            ->name('sales.show')
            ->middleware('throttle:sales-read');
    });

    /**
     * Relatórios
     * - GET /api/v1/reports/sales?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&product_sku=SKU123
     *   (parâmetros opcionais, mas estes nomes são os exigidos)
     */
    Route::get('reports/sales', [ReportController::class, 'sales'])
        ->name('reports.sales')
        ->middleware('throttle:reports'); // tipicamente mais agressivo

    /**
     * Erro 500 simulado (debug)
     * - GET /api/v1/error
     */
    Route::get('/error', function () {
        Log::error('Simulação de erro interno para teste de API Exception Formatter.');
        throw new \RuntimeException('Erro interno simulado.');
    })->name('debug.error');

    // Observability endpoints (metrics)
    Route::prefix('observability')->group(function () {
        Route::get('/metrics', [\App\Http\Controllers\ObservabilityController::class, 'metrics'])
            ->name('observability.metrics')
            ->middleware(['throttle:observability', 'restrict.ip']);
    });
});
