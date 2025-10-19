<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Persistence\Queries\InventoryQuery;
use Tests\TestCase;

/**
 * Cenário: listar itens de inventário e retornar estrutura JSON esperada.
 */
final class InventoryControllerTest extends TestCase
{
    public function test_lista_inventario_com_per_page_zero_retorna_totais_e_cache_headers(): void
    {
        // Construímos um InventoryQuery real com um resolver 'fake' para o DB
        $row = (object) ['product_id' => 1, 'sku' => 'X', 'name' => 'Prod', 'quantity' => 5];

        $get = new class($row)
        {
            private $row;

            public function __construct($row)
            {
                $this->row = $row;
            }

            public function get()
            {
                return collect([$this->row]);
            }

            public function limit($n)
            {
                return $this;
            }
        };

        $inv = new class($get, $row)
        {
            private $get;

            private $row;

            public function __construct($get, $row)
            {
                $this->get = $get;
                $this->row = $row;
            }

            public function join()
            {
                return $this;
            }

            public function select()
            {
                return $this;
            }

            public function orderBy()
            {
                return $this;
            }

            public function where()
            {
                return $this;
            }

            public function whereRaw()
            {
                return $this;
            }

            public function orWhereRaw()
            {
                return $this;
            }

            public function limit($n)
            {
                return $this;
            }

            public function get()
            {
                return $this->get->get();
            }

            public function selectRaw()
            {
                return $this;
            }

            public function first()
            {
                return (object) ['total_cost' => 100.0, 'total_sale' => 150.0];
            }
        };

        $query = new InventoryQuery;
        $query->setDbResolver(fn () => $inv);

        $totals = ['total_cost' => 100.0, 'total_sale' => 150.0, 'projected_profit' => 50.0];

        // Fake de cache simples que executa o resolver quando remember é chamado
        $fakeCache = new class
        {
            public function remember($key, $ttl, $resolver)
            {
                return $resolver();
            }

            public function get($k, $d = null)
            {
                return $d;
            }

            public function put($k, $v, $t) {}

            public function forget($k) {}
        };

        $cache = new InventoryCache($fakeCache);

        // Registrar instâncias no container para injeção via DI quando a rota for chamada
        $this->instance(InventoryQuery::class, $query);
        $this->instance(InventoryCache::class, $cache);

        $response = $this->getJson('/api/v1/inventory?per_page=0');

        $this->assertEquals(200, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('meta', $payload);
        $this->assertEquals($totals, $payload['meta']['totals']);
        $this->assertStringContainsString('max-age', $response->headers->get('Cache-Control'));
    }

    public function test_show_inventario_nao_encontrado_retorna_404(): void
    {
        // byProductId usa baseQuery()->first(), então reutilizamos um resolver simples
        $inv2 = new class
        {
            public function join()
            {
                return $this;
            }

            public function select()
            {
                return $this;
            }

            public function orderBy()
            {
                return $this;
            }

            public function where($col, $val)
            {
                return $this;
            }

            public function whereRaw()
            {
                return $this;
            }

            public function orWhereRaw()
            {
                return $this;
            }

            public function first()
            {
                return null;
            }
        };

        $query2 = new InventoryQuery;
        $query2->setDbResolver(fn () => $inv2);

        $fakeCache2 = new class
        {
            public function remember($key, $ttl, $resolver)
            {
                return $resolver();
            }
        };

        $cache2 = new InventoryCache($fakeCache2);

        $this->instance(InventoryQuery::class, $query2);
        $this->instance(InventoryCache::class, $cache2);

        $response = $this->getJson('/api/v1/inventory/9999');

        $this->assertEquals(404, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $payload);
    }
}
