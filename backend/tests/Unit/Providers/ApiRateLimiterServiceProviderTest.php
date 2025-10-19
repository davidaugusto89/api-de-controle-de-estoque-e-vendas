<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\ApiRateLimiterServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class ApiRateLimiterServiceProviderTest extends TestCase
{
    public function test_boot_registra_chaves_do_rate_limiter(): void
    {
        /**
         * Cenário
         * Dado: lista de chaves esperadas para RateLimiter
         * Quando: boot do provider é executado
         * Então: RateLimiter::for é chamado para cada chave com uma closure
         */
        // Expect RateLimiter::for called with specific keys and a closure
        $expectedKeys = ['inventory-read', 'inventory-write', 'sales-write', 'sales-read', 'reports', 'api'];

        foreach ($expectedKeys as $key) {
            RateLimiter::shouldReceive('for')
                ->once()
                ->withArgs(function ($name, $closure) use ($key) {
                    return $name === $key && is_callable($closure);
                })
                ->andReturnNull();
        }

        $provider = new ApiRateLimiterServiceProvider($this->app);
        $provider->boot();
    }

    public function test_too_many_closure_retorna_429_json(): void
    {
        /**
         * Cenário
         * Dado: provider instanciado
         * Quando: invocamos a closure 'tooMany' via reflexão
         * Então: retorna Response com status 429 e payload JSON contendo fields esperados
         */
        $provider = new ApiRateLimiterServiceProvider($this->app);

        $rm = new \ReflectionMethod($provider, 'tooMany');
        $rm->setAccessible(true);

        $closure = $rm->invoke($provider);

        $this->assertIsCallable($closure);

        $response = $closure();

        $this->assertSame(429, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertArrayHasKey('error', $payload);
        $this->assertArrayHasKey('code', $payload['error']);
        $this->assertArrayHasKey('message', $payload['error']);
    }
}
