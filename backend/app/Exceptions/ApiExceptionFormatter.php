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

/**
 * Formata exceções de aplicação em respostas JSON consistentes para APIs.
 */
final class ApiExceptionFormatter
{
    /**
     * Constrói uma resposta JSON padronizada a partir de uma exceção.
     */
    public static function from(Throwable $e, Request $request): JsonResponse
    {
        $debug     = (bool) config('app.debug', false);
        $requestId = self::requestId($request);

        // 422 Validation
        if ($e instanceof ValidationException) {
            // Usa diretamente $e->errors() (array), sem tentar resumir a mensagem
            return self::json(
                status: 422,
                code: 'ValidationError',
                message: 'Os dados enviados são inválidos.',
                requestId: $requestId,
                details: ['errors' => $e->errors()],
            );
        }

        // 401 Unauthenticated
        if ($e instanceof AuthenticationException) {
            return self::json(
                status: 401,
                code: 'Unauthenticated',
                message: 'Não autenticado.',
                requestId: $requestId,
            );
        }

        // 403 Forbidden
        if ($e instanceof AuthorizationException) {
            return self::json(
                status: 403,
                code: 'Forbidden',
                message: 'Você não tem permissão para executar esta ação.',
                requestId: $requestId,
            );
        }

        // 404 Model not found
        if ($e instanceof ModelNotFoundException) {
            return self::json(
                status: 404,
                code: 'ResourceNotFound',
                message: 'Recurso não encontrado.',
                requestId: $requestId,
            );
        }

        // 404 Route not found
        if ($e instanceof NotFoundHttpException) {
            return self::json(
                status: 404,
                code: 'RouteNotFound',
                message: 'Rota não encontrada.',
                requestId: $requestId,
            );
        }

        // 405 Method Not Allowed — normaliza header Allow
        if ($e instanceof MethodNotAllowedHttpException) {
            $headers   = $e->getHeaders();
            $allow     = $headers['Allow'] ?? null;
            $allowList = is_array($allow) ? $allow : (is_string($allow) ? [$allow] : []);

            return self::json(
                status: 405,
                code: 'MethodNotAllowed',
                message: 'Método HTTP não permitido para esta rota.',
                requestId: $requestId,
                headers: ['Allow' => implode(', ', $allowList)],
            );
        }

        // 429 Too Many Requests — propaga headers e inclui debug quando habilitado
        if ($e instanceof ThrottleRequestsException) {
            $headers = $e->getHeaders() ?? [];

            return self::json(
                status: 429,
                code: 'TooManyRequests',
                message: 'Muitas requisições. Tente novamente mais tarde.',
                requestId: $requestId,
                headers: $headers,
                debug: $debug ? self::debugPayload($e) : null,
            );
        }

        // Outras HTTP exceptions (mantém status, headers e debug opcional)
        if ($e instanceof HttpExceptionInterface) {
            return self::json(
                status: $e->getStatusCode(),
                code: class_basename($e),
                message: $e->getMessage() ?: 'Erro ao processar a requisição.',
                requestId: $requestId,
                headers: $e->getHeaders(),
                debug: $debug ? self::debugPayload($e) : null,
            );
        }

        // 500 Database error (sem detalhes sensíveis)
        if ($e instanceof QueryException) {
            return self::json(
                status: 500,
                code: 'DatabaseError',
                message: 'Erro interno no servidor.',
                requestId: $requestId,
                debug: $debug ? self::debugPayload($e) : null,
            );
        }

        // 500 Fallback
        return self::json(
            status: 500,
            code: 'InternalServerError',
            message: 'Erro interno no servidor.',
            requestId: $requestId,
            debug: $debug ? self::debugPayload($e) : null,
        );
    }

    /**
     * Cria a resposta JSON final com payload padronizado.
     *
     * @param  array<string,mixed>|null $details
     * @param  array<string,string>|null $headers
     * @param  array{exception:string,file:string,line:int,trace:list<string>}|null $debug
     */
    private static function json(
        int $status,
        string $code,
        string $message,
        string $requestId,
        ?array $details = null,
        ?array $headers = null,
        ?array $debug = null,
    ): JsonResponse {
        $payload = [
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
            'meta' => [
                'request_id' => $requestId,
            ],
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        if ($debug !== null) {
            $payload['error']['debug'] = $debug;
        }

        $response = response()->json(
            $payload,
            $status,
            [],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if (! empty($headers)) {
            $response->withHeaders($headers);
        }

        return $response;
    }

    /**
     * Obtém o identificador da requisição a partir do header ou gera um novo.
     */
    private static function requestId(Request $request): string
    {
        return $request->header('X-Request-Id') ?? bin2hex(random_bytes(8));
    }

    /**
     * Constrói o bloco de informações de debug com metadados mínimos.
     *
     * @return array{exception:string,file:string,line:int,trace:list<string>}
     */
    private static function debugPayload(Throwable $e): array
    {
        return [
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => collect(explode("\n", $e->getTraceAsString()))->take(20)->all(),
        ];
    }
}
