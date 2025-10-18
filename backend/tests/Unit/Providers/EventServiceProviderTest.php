<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\EventServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(EventServiceProvider::class)]
final class EventServiceProviderTest extends TestCase
{
    public function test_listen_contem_mapeamento_para_sale_finalized(): void
    {
    // Instancia diretamente passando o container para o construtor.
    // Usar $this->app->make() tenta resolver dependências do ServiceProvider
    // via auto-wiring e falha ao resolver o parâmetro `$app`.
    $provider = new EventServiceProvider($this->app);

        $listen = $this->getProtectedProperty($provider, 'listen');

        $this->assertArrayHasKey(\App\Infrastructure\Events\SaleFinalized::class, $listen);
        $this->assertContains(\App\Infrastructure\Listeners\UpdateInventoryListener::class, $listen[\App\Infrastructure\Events\SaleFinalized::class]);
    }

    /**
     * Small helper to get protected properties from provider instances.
     */
    private function getProtectedProperty(object $obj, string $prop)
    {
        $ref = new \ReflectionObject($obj);
        $p   = $ref->getProperty($prop);
        $p->setAccessible(true);

        return $p->getValue($obj);
    }
}
