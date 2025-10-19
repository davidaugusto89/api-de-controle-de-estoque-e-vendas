<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que toda resposta HTTP seja JSON.
 */
class ForceJsonResponse
{
    /**
     * Garante que toda resposta HTTP seja JSON.
     *
     * @param  Request  $request  Requisição HTTP atual
     * @param  Closure  $next  Próxima função da cadeia
     * @return Response Resposta HTTP em formato JSON
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Permite acesso normal ao Horizon
        if ($request->is('horizon') || $request->is('horizon/*')) {
            return $next($request);
        }

        // Força header Accept para JSON se o cliente não enviar
        if (! $request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Converte respostas string/array para JSON padronizado
        if (! $response instanceof \Illuminate\Http\JsonResponse) {
            $content = $response->getContent();

            // tenta decodificar se já for JSON
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            } else {
                $data = ['message' => $content];
            }

            $response = response()->json(
                $data,
                $response->getStatusCode(),
                $response->headers->all()
            );
        }

        // Garante header de tipo correto
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
