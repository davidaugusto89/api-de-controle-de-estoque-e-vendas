<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RestrictIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('observability.allowed_ips', []);

        if (empty($allowed)) {
            // No restriction configured — allow by default (safe for dev).
            return $next($request);
        }

        $ip = $request->ip();

        if (! in_array($ip, $allowed, true)) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
