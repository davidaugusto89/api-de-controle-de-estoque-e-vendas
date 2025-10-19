<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para restringir acesso por IP.
 */
final class RestrictIp
{
    /**
     * Restringe acesso com base em uma lista de IPs permitidos.
     *
     * @param  Request  $request  Requisição HTTP atual
     * @param  Closure  $next  Próxima função da cadeia
     * @return Response Resposta HTTP
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('observability.allowed_ips', []);

        if (empty($allowed)) {
            return $next($request);
        }

        $ip = $request->ip();

        if (! in_array($ip, $allowed, true)) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
