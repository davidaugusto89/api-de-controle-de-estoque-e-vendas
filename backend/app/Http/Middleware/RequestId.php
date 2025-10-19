<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para adicionar um ID único a cada requisição.
 */
final class RequestId
{
    /**
     * Adiciona um ID único a cada requisição.
     *
     * @param  Request  $request  Requisição HTTP atual
     * @param  Closure  $next  Próxima função da cadeia
     * @return Response Resposta HTTP com header X-Request-Id
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Request-Id') ?? bin2hex(random_bytes(8));

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
