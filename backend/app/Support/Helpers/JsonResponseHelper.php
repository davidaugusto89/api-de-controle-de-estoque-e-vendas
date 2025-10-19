<?php

declare(strict_types=1);

namespace App\Support\Helpers;

use Illuminate\Http\JsonResponse;

/**
 * Helper para respostas JSON padronizadas.
 */
final class JsonResponseHelper
{
    /**
     * Resposta de sucesso padronizada.
     */
    public static function ok(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'meta' => $meta,
            'data' => $data,
        ], $status);
    }

    /**
     * Resposta de erro padronizada.
     */
    public static function error(string $code, string $message, int $status = 400, ?string $requestId = null, array $details = []): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'request_id' => $requestId,
        ], $status);
    }
}
