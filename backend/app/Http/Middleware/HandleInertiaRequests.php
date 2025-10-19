<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Middleware para requisições Inertia.js.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Determina a versão atual dos assets.
     *
     * @param  Request  $request  Requisição HTTP atual
     * @return string|null Versão dos assets ou null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Compartilha dados padrão com todas as respostas Inertia.
     *
     * @param  Request  $request  Requisição HTTP atual
     * @return array Dados compartilhados
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
        ];
    }
}
