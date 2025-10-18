<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Inventory\UseCases;

use App\Application\Inventory\UseCases\GetInventorySnapshot;
use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Persistence\Queries\InventoryQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(GetInventorySnapshot::class)]
final class GetInventorySnapshotTest extends TestCase
{
    public function test_handle_retorna_dados_do_cache_quando_existem(): void
    {
        $items = [
            ['product_id' => 1, 'sku' => 'SKU-1', 'name' => 'P1', 'quantity' => 2],
        ];

        $totals = ['total_cost' => 10.0, 'total_sale' => 20.0, 'projected_profit' => 10.0];

        // Usar um InventoryCache real com store 'array' e pré-popular a chave para simular cache hit
        $realQuery = new InventoryQuery();
        $cacheRepo = $this->app['cache']->store('array');
        $realCache = new InventoryCache($cacheRepo);

        // Calcular a chave usada por rememberListAndTotalsUnpaged para search null
        $ver = (int) $cacheRepo->get('inventory:list_version', 1);
        $suffix = 'list:unpaged:v'.$ver.':'.md5(json_encode(['q' => ''], JSON_THROW_ON_ERROR));
        $key = 'inventory:'.$suffix;

        // Armazenar o valor no cache para simular cache hit
        $cacheRepo->put($key, [$items, $totals], 60);

        $useCase = new GetInventorySnapshot($realQuery, $realCache);
        $res = $useCase->handle();

        $this->assertSame($items, $res['items']);
        $this->assertSame($totals, $res['totals']);
    }

    public function test_handle_consulta_e_armazena_quando_cache_vazio(): void
    {
        $rows = [
            (object) ['product_id' => 2, 'sku' => 'SKU-2', 'name' => 'P2', 'quantity' => 5],
        ];

        $collection = new Collection($rows);

        $totals = ['total_cost' => 5.0, 'total_sale' => 12.0, 'projected_profit' => 7.0];

        // Para testar cache miss, usamos um InventoryCache real com store 'array' (sem valor pre-populado)
        $realQuery = new InventoryQuery();
        $cacheRepo = $this->app['cache']->store('array');
        $realCache = new InventoryCache($cacheRepo);

        $useCase = new GetInventorySnapshot($realQuery, $realCache);

        // Mock do DB para que InventoryQuery::list() e ::totals() retornem os valores desejados
        $mockQB = \Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $mockQB->shouldReceive('join')->andReturnSelf();
        $mockQB->shouldReceive('select')->andReturnSelf();
        $mockQB->shouldReceive('orderBy')->andReturnSelf();
        $mockQB->shouldReceive('where')->andReturnSelf();
        $mockQB->shouldReceive('whereRaw')->andReturnSelf();
        $mockQB->shouldReceive('orWhereRaw')->andReturnSelf();
        $mockQB->shouldReceive('limit')->andReturnSelf();
        $mockQB->shouldReceive('selectRaw')->andReturnSelf();
        $mockQB->shouldReceive('get')->andReturn($collection);
        $mockQB->shouldReceive('first')->andReturn((object) ['total_cost' => $totals['total_cost'], 'total_sale' => $totals['total_sale']]);

        \Mockery::getConfiguration()->allowMockingNonExistentMethods(true);
        DB::shouldReceive('raw')->andReturnUsing(fn($s) => new \Illuminate\Database\Query\Expression($s));
        DB::shouldReceive('table')->andReturn($mockQB);
        $res = $useCase->handle();

        // Os items devem ser arrays (->all() no código)
        $this->assertIsArray($res['items']);
        $this->assertSame([ (array) $rows[0] ], $res['items']);
        $this->assertSame($totals, $res['totals']);
        // Verificar que o cache 'armazenou' chamando o resolver
    // Verificar que o cache store 'array' agora tem a chave populada
    $ver2 = (int) $cacheRepo->get('inventory:list_version', 1);
    $suffix2 = 'list:unpaged:v'.$ver2.':'.md5(json_encode(['q' => ''], JSON_THROW_ON_ERROR));
    $key2 = 'inventory:'.$suffix2;

    $this->assertNotNull($cacheRepo->get($key2));
    }

    public function test_handle_retorna_vazio_quando_nao_ha_itens(): void
    {
        $collection = new Collection([]);
        $totals = ['total_cost' => 0.0, 'total_sale' => 0.0, 'projected_profit' => 0.0];

        $realQuery = new InventoryQuery();
        $cacheRepo = $this->app['cache']->store('array');
        $realCache = new InventoryCache($cacheRepo);
        $useCase = new GetInventorySnapshot($realQuery, $realCache);

        // Mock DB para retornar coleção vazia e totals zeros
        $mockQB = \Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $mockQB->shouldReceive('join')->andReturnSelf();
        $mockQB->shouldReceive('select')->andReturnSelf();
        $mockQB->shouldReceive('orderBy')->andReturnSelf();
        $mockQB->shouldReceive('where')->andReturnSelf();
        $mockQB->shouldReceive('whereRaw')->andReturnSelf();
        $mockQB->shouldReceive('orWhereRaw')->andReturnSelf();
        $mockQB->shouldReceive('limit')->andReturnSelf();
        $mockQB->shouldReceive('selectRaw')->andReturnSelf();
        $mockQB->shouldReceive('get')->andReturn(new \Illuminate\Support\Collection([]));
        $mockQB->shouldReceive('first')->andReturn((object) ['total_cost' => 0, 'total_sale' => 0]);

        \Mockery::getConfiguration()->allowMockingNonExistentMethods(true);
        DB::shouldReceive('raw')->andReturnUsing(fn($s) => new \Illuminate\Database\Query\Expression($s));
        DB::shouldReceive('table')->andReturn($mockQB);
        $res = $useCase->handle();

        $this->assertSame([], $res['items']);
        $this->assertSame($totals, $res['totals']);
    }
}
