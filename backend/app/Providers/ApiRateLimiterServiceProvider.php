<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

final class ApiRateLimiterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // chave por usuário autenticado ou IP
        $by = fn (Request $r) => optional($r->user())->id ?: $r->ip();

        // Inventário (leituras frequentes, limites mais altos)
        RateLimiter::for('inventory-read', function (Request $request) use ($by) {
            $key = 'inventory-read:'.$by($request);

            return [
                Limit::perMinute(120)->by($key)->response($this->tooMany()),
                Limit::perSecond(10)->by($key.':burst')->response($this->tooMany()),
            ];
        });

        RateLimiter::for('inventory-write', function (Request $request) use ($by) {
            $key = 'inventory-write:'.$by($request);

            return [Limit::perMinute(30)->by($key)->response($this->tooMany())];
        });

        // Vendas (protege POST fortemente, GET moderado)
        RateLimiter::for('sales-write', function (Request $request) use ($by) {
            $key = 'sales-write:'.$by($request);

            return [Limit::perMinute(20)->by($key)->response($this->tooMany())];
        });

        RateLimiter::for('sales-read', function (Request $request) use ($by) {
            $key = 'sales-read:'.$by($request);

            return [Limit::perMinute(60)->by($key)->response($this->tooMany())];
        });

        // Relatórios (consultas pesadas)
        RateLimiter::for('reports', function (Request $request) use ($by) {
            $key = 'reports:'.$by($request);

            return [
                Limit::perMinute(15)->by($key)->response($this->tooMany()),
                // burst curto para evitar picos
                Limit::perSecond(5)->by($key.':burst')->response($this->tooMany()),
            ];
        });

        // Fallback padrão para 'throttle:api'
        RateLimiter::for('api', function (Request $request) use ($by) {
            $key = 'api:'.$by($request);

            return [Limit::perMinute(60)->by($key)->response($this->tooMany())];
        });

        // Observability endpoint: métricas prometheus-style, exposto
        // internamente; proteger com limite moderado por IP para evitar scraping
        // agressivo em ambientes públicos.
        RateLimiter::for('observability', function (Request $request) use ($by) {
            $key = 'observability:'.$by($request);

            return [Limit::perMinute(60)->by($key)->response($this->tooMany())];
        });
    }

    private function tooMany(): \Closure
    {
        return function () {
            return response()->json([
                'error' => [
                    'code'    => 'TooManyRequests',
                    'message' => 'Muitas requisições. Tente novamente em instantes.',
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        };
    }
}
