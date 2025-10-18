<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionFormatter
{
    public static function from(Throwable $e, Request $request): JsonResponse
    {
        $debug = (bool) config('app.debug', false);
        $requestId = self::requestId($request);

        // Mapeamento por tipo (ordem importa)
        if ($e instanceof ValidationException) {
            return self::json(
                status: 422,
                code: 'ValidationError',
                message: 'Os dados enviados são inválidos.',
                requestId: $requestId,
                details: ['errors' => $e->errors()],
            );
        }

        if ($e instanceof AuthenticationException) {
            return self::json(
                status: 401,
                code: 'Unauthenticated',
                message: 'Não autenticado.',
                requestId: $requestId,
            );
        }

        if ($e instanceof AuthorizationException) {
            return self::json(
                status: 403,
                code: 'Forbidden',
                message: 'Você não tem permissão para executar esta ação.',
                requestId: $requestId,
            );
        }

        if ($e instanceof ModelNotFoundException) {
            return self::json(
                status: 404,
                code: 'ResourceNotFound',
                message: 'Recurso não encontrado.',
                requestId: $requestId,
            );
        }

        if ($e instanceof NotFoundHttpException) {
            return self::json(
                status: 404,
                code: 'RouteNotFound',
                message: 'Rota não encontrada.',
                requestId: $requestId,
            );
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return self::json(
                status: 405,
                code: 'MethodNotAllowed',
                message: 'Método HTTP não permitido para esta rota.',
                requestId: $requestId,
                headers: ['Allow' => implode(', ', $e->getHeaders()['Allow'] ?? [])],
            );
        }

        if ($e instanceof ThrottleRequestsException) {
            // 429 com headers de rate limit se disponíveis
            return self::json(
                status: 429,
                code: 'TooManyRequests',
                message: 'Muitas requisições. Tente novamente mais tarde.',
                requestId: $requestId,
                headers: $e->getHeaders(),
            );
        }

        if ($e instanceof HttpExceptionInterface) {
            // Mantém status de HttpException mas não expõe detalhes sensíveis
            return self::json(
                status: $e->getStatusCode(),
                code: class_basename($e),
                message: $e->getMessage() ?: 'Erro ao processar a requisição.',
                requestId: $requestId,
                headers: $e->getHeaders(),
                debug: $debug ? self::debugPayload($e) : null,
            );
        }

        if ($e instanceof QueryException) {
            // Erros de DB retornam 500 sem detalhes sensíveis
            return self::json(
                status: 500,
                code: 'DatabaseError',
                message: 'Erro interno no servidor.',
                requestId: $requestId,
                debug: $debug ? self::debugPayload($e) : null,
            );
        }

        // Fallback 500
        return self::json(
            status: 500,
            code: 'InternalServerError',
            message: 'Erro interno no servidor.',
            requestId: $requestId,
            debug: $debug ? self::debugPayload($e) : null,
        );
    }

    private static function json(
        int $status,
        string $code,
        string $message,
        string $requestId,
        ?array $details = null,
        ?array $headers = null,
        ?array $debug = null,
    ): \Illuminate\Http\JsonResponse {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => [
                'request_id' => $requestId,
            ],
        ];

        if ($details) {
            $payload['error']['details'] = $details;
        }

        if ($debug) {
            $payload['error']['debug'] = $debug;
        }

        // ✅ Deixe o Laravel serializar; não use $json=true do Symfony
        $response = response()->json(
            $payload,
            $status,
            [],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Aplique cabeçalhos extras (ex.: Allow, Retry-After, etc.)
        if (! empty($headers)) {
            $response->withHeaders($headers);
        }

        return $response;
    }

    private static function requestId(Request $request): string
    {
        // Usa um header existente ou gera um id simples
        return $request->header('X-Request-Id') ?? bin2hex(random_bytes(8));
    }

    private static function debugPayload(Throwable $e): array
    {
        return [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => collect(explode("\n", $e->getTraceAsString()))->take(20)->all(),
        ];
    }
}
