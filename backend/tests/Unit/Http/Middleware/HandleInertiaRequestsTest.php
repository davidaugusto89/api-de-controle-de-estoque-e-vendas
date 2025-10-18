<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Tests\TestCase;

final class HandleInertiaRequestsTest extends TestCase
{
    public function test_version_delega_para_parent_e_retorna_valor(): void
    {
        $request = Request::create('/');

        $middleware = new HandleInertiaRequests;

        $version = $middleware->version($request);

        // por padrão parent::version devolve null a não ser que haja asset version configurada
        $this->assertNull($version);
    }

    public function test_share_inclui_name_quote_e_auth_user_quando_user_presente(): void
    {
        $user = (object) ['id' => 42, 'name' => 'Tester'];

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $middleware = new HandleInertiaRequests;

        $shared = $middleware->share($request);

        $this->assertArrayHasKey('name', $shared);
        $this->assertSame(config('app.name'), $shared['name']);

        $this->assertArrayHasKey('quote', $shared);
        $this->assertIsArray($shared['quote']);
        $this->assertArrayHasKey('message', $shared['quote']);
        $this->assertArrayHasKey('author', $shared['quote']);
        $this->assertIsString($shared['quote']['message']);
        $this->assertIsString($shared['quote']['author']);

        $this->assertArrayHasKey('auth', $shared);
        $this->assertArrayHasKey('user', $shared['auth']);
        $this->assertSame($user, $shared['auth']['user']);
    }

    public function test_share_inclui_auth_user_null_quando_nao_houver_user(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => null);

        $middleware = new HandleInertiaRequests;

        $shared = $middleware->share($request);

        $this->assertArrayHasKey('auth', $shared);
        $this->assertArrayHasKey('user', $shared['auth']);
        $this->assertNull($shared['auth']['user']);
    }
}
