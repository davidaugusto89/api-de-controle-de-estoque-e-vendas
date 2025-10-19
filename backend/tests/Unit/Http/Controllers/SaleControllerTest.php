<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Infrastructure\Persistence\Queries\SaleDetailsQuery;
use Tests\TestCase;

/**
 * Cenário: criar venda e recuperar detalhes de venda.
 */
final class SaleControllerTest extends TestCase
{
    public function test_show_venda_nao_encontrada_retorna_404(): void
    {
        // Criar SaleDetailsQuery real com resolver que retorna null
        $inv = new class
        {
            public function where()
            {
                return $this;
            }

            public function select()
            {
                return $this;
            }

            public function first()
            {
                return null;
            }

            public function join()
            {
                return $this;
            }

            public function get()
            {
                return collect([]);
            }
        };

        $saleQuery = new SaleDetailsQuery;
        $saleQuery->setDbResolver(fn () => $inv);

        $this->instance(SaleDetailsQuery::class, $saleQuery);

        $response = $this->getJson('/api/v1/sales/9999');

        $this->assertEquals(404, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $payload);
    }
}
