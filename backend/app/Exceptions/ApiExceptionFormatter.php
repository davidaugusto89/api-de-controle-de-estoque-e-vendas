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
 *
 * ### Objetivo
 * Centralizar o mapeamento de exceções comuns (validação, authZ/authN, HTTP, DB, rate limit)
 * para um payload JSON estável, adequado a clientes e observabilidade.
 *
 * ### Comportamento
 * - **Ordem de verificação importa**: casos mais específicos são tratados primeiro.
 * - **Sem vazamento de informação sensível**: mensagens de exceção originais são omitidas,
 *   exceto para `HttpExceptionInterface` (mantendo o status) e somente exibidas no bloco `debug`
 *   quando `app.debug=true`.
 * - **Metadado de correlação**: injeta `meta.request_id` (do cabeçalho `X-Request-Id` ou gera um id).
 * - **Cabeçalhos**: propaga cabeçalhos relevantes (ex.: `Allow`, `Retry-After`) quando presentes.
 *
 * ### Dependências
 * - **Request**: usado para extrair o `X-Request-Id`.
 * - **Exceções do Laravel/Symfony**: insumos para o mapeamento de status/códigos.
 *
 * @phpstan-type DebugPayload array{
 *   exception: string,
 *   file: string,
 *   line: int,
 *   trace: list<string>
 * }
 * @phpstan-type ErrorBody array{
 *   code: string,
 *   message: string,
 *   details?: array,
 *   debug?: DebugPayload
 * }
 * @phpstan-type ApiPayload array{
 *   error: ErrorBody,
 *   meta: array{request_id: string}
 * }
 */
final class ApiExceptionFormatter
{
    /**
     * Constrói uma resposta JSON padronizada a partir de uma exceção.
     *
     * @param  Throwable  $e  Exceção original capturada pelo handler.
     * @param  Request  $request  Requisição atual (para correlação e hints).
     * @return JsonResponse Resposta JSON serializada pelo Laravel.
     *
     * @phpstan-return JsonResponse
     */
    public static function from(Throwable $e, Request $request): JsonResponse
    {
        $debug = (bool) config('app.debug', false);
        $requestId = self::requestId($request);

        // Validação (422)
        if ($e instanceof ValidationException) {
            return self::json(
                status: 422,
                code: 'ValidationError',
                message: 'Os dados enviados são inválidos.',
                requestId: $requestId,
                details: ['errors' => $e->errors()],
            );
        }

        // Não autenticado (401)
        if ($e instanceof AuthenticationException) {
            return self::json(
                status: 401,
                code: 'Unauthenticated',
                message: 'Não autenticado.',
                requestId: $requestId,
            );
        }

        // Proibido (403)
        if ($e instanceof AuthorizationException) {
            return self::json(
                status: 403,
                code: 'Forbidden',
                message: 'Você não tem permissão para executar esta ação.',
                requestId: $requestId,
            );
        }

        // Model não encontrado (404)
        if ($e instanceof ModelNotFoundException) {
            return self::json(
                status: 404,
                code: 'ResourceNotFound',
                message: 'Recurso não encontrado.',
                requestId: $requestId,
            );
        }

        // Rota não encontrada (404)
        if ($e instanceof NotFoundHttpException) {
            return self::json(
                status: 404,
                code: 'RouteNotFound',
                message: 'Rota não encontrada.',
                requestId: $requestId,
            );
        }

        // Método não permitido (405) — inclui cabeçalho Allow
        if ($e instanceof MethodNotAllowedHttpException) {
            return self::json(
                status: 405,
                code: 'MethodNotAllowed',
                message: 'Método HTTP não permitido para esta rota.',
                requestId: $requestId,
                headers: ['Allow' => implode(', ', $e->getHeaders()['Allow'] ?? [])],
            );
        }

        // Rate limit (429) — propaga cabeçalhos de throttling
        if ($e instanceof ThrottleRequestsException) {
            return self::json(
                status: 429,
                code: 'TooManyRequests',
                message: 'Muitas requisições. Tente novamente mais tarde.',
                requestId: $requestId,
                headers: $e->getHeaders(),
            );
        }

        // Exceções HTTP (mantém status, mensagem opcional, e headers)
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

        // Erros de banco (500), sem detalhes sensíveis
        if ($e instanceof QueryException) {
            return self::json(
                status: 500,
                code: 'DatabaseError',
                message: 'Erro interno no servidor.',
                requestId: $requestId,
                debug: $debug ? self::debugPayload($e) : null,
            );
        }

        // Fallback (500)
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
     * @param  int  $status  Código HTTP.
     * @param  string  $code  Código de erro estável (consumido por clientes).
     * @param  string  $message  Mensagem amigável e genérica ao cliente.
     * @param  string  $requestId  Identificador de correlação da requisição.
     * @param  array|null  $details  Dados adicionais do erro (ex.: mapa de campos inválidos).
     * @param  array|null  $headers  Cabeçalhos extras a aplicar na resposta.
     * @param  array|null  $debug  Informações de debug (somente em ambiente com debug ativo).
     *
     * @phpstan-param array<string, mixed>|null $details
     * @phpstan-param array<string, string>|null $headers
     * @phpstan-param DebugPayload|null $debug
     *
     * @phpstan-return JsonResponse
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
        /** @var ApiPayload $payload */
        $payload = [
            'error' => [
                'code' => $code,
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

        // Serialização pelo helper do Laravel, com flags para Unicode/URLs legíveis
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
     *
     * @phpstan-return DebugPayload
     */
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
