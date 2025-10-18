<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// API-only: sem rotas web/inertia
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /**
         * Grupo 'horizon-web' só para as rotas do Horizon.
         * Inclui o mínimo necessário para a UI (cookies + sessão + CSRF).
         */
        $middleware->group('horizon-web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);

        // Global (aplica para todas as requisições)
        $middleware->append([
            // identificação da requisição
            \App\Http\Middleware\RequestId::class,
            // força respostas JSON e normaliza payloads
            \App\Http\Middleware\ForceJsonResponse::class,
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        // Stack da API
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ], append: [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

        $middleware->web(remove: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API-only: sempre JSON
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // Um único ponto de formatação para TODOS os erros
        $exceptions->render(function (Throwable $e, \Illuminate\Http\Request $request) {
            return \App\Exceptions\ApiExceptionFormatter::from($e, $request);
        });
    })->create();
