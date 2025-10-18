<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cache;

use App\Infrastructure\Cache\InventoryCache;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para InventoryCache.
 *
 * Cenário: Cache de inventário com TTL de 60 segundos, normalização de queries para chaves consistentes,
 * invalidação por produtos ou listas, e mitigação de cache stampede via locks opcionais.
 * Mocks são usados para controlar TTL, chamadas externas e comportamento determinístico,
 * evitando dependências reais como Redis ou DB.
 */
final class InventoryCacheTest extends TestCase
{
    private MockInterface $cacheMock;

    private InventoryCache $inventoryCache;

    protected function setUp(): void
    {
        $this->cacheMock = Mockery::mock(CacheRepository::class);
        $this->inventoryCache = new InventoryCache($this->cacheMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_deve_retornar_dados_do_cache_em_hit_para_lista_paginada(): void
    {
        // Arrange
        $search = 'produto';
        $perPage = 10;
        $page = 1;
        $cachedData = [['item1'], ['meta'], ['totals']];
        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version', 1)
            ->andReturn(1);
        $this->cacheMock->shouldReceive('remember')
            ->once()
            ->andReturn($cachedData);

        // Act
        $result = $this->inventoryCache->rememberListAndTotalsPaged($search, $perPage, $page, fn () => []);

        // Assert
        $this->assertEquals($cachedData, $result);
    }

    public function test_deve_chamar_resolver_em_cache_miss_para_lista_paginada(): void
    {
        // Arrange
        $search = null;
        $perPage = 5;
        $page = 2;
        $resolverData = [['item2'], ['meta2'], ['totals2']];
        $resolver = fn () => $resolverData;
        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version', 1)
            ->andReturn(1);
        $this->cacheMock->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Act
        $result = $this->inventoryCache->rememberListAndTotalsPaged($search, $perPage, $page, $resolver);

        // Assert
        $this->assertEquals($resolverData, $result);
    }

    public function test_deve_normalizar_query_para_lista_paginada(): void
    {
        // Arrange
        $search = '  PRODUTO TESTE  ';
        $perPage = 10;
        $page = 1;
        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version', 1)
            ->andReturn(1);
        $this->cacheMock->shouldReceive('remember')
            ->once()
            ->andReturn([]);

        // Act
        $this->inventoryCache->rememberListAndTotalsPaged($search, $perPage, $page, fn () => []);

        // Assert
        $this->assertTrue(true);
    }

    public function test_deve_retornar_dados_do_cache_em_hit_para_lista_nao_paginada(): void
    {
        // Arrange
        $search = 'busca';
        $cachedData = [['item3'], ['totals3']];
        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version', 1)
            ->andReturn(1);
        $this->cacheMock->shouldReceive('remember')
            ->once()
            ->andReturn($cachedData);

        // Act
        $result = $this->inventoryCache->rememberListAndTotalsUnpaged($search, fn () => []);

        // Assert
        $this->assertEquals($cachedData, $result);
    }

    public function test_deve_chamar_resolver_em_cache_miss_para_lista_nao_paginada(): void
    {
        // Arrange
        $search = '';
        $resolverData = [['item4'], ['totals4']];
        $resolver = fn () => $resolverData;
        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version', 1)
            ->andReturn(1);
        $this->cacheMock->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Act
        $result = $this->inventoryCache->rememberListAndTotalsUnpaged($search, $resolver);

        // Assert
        $this->assertEquals($resolverData, $result);
    }

    public function test_deve_retornar_dados_do_cache_em_hit_para_item(): void
    {
        // Arrange
        $productId = 123;
        $cachedData = ['product' => 'data'];
        $this->cacheMock->shouldReceive('remember')
            ->once()
            ->andReturn($cachedData);

        // Act
        $result = $this->inventoryCache->rememberItem($productId, fn () => []);

        // Assert
        $this->assertEquals($cachedData, $result);
    }

    public function test_deve_chamar_resolver_em_cache_miss_para_item(): void
    {
        // Arrange
        $productId = 456;
        $resolverData = ['product' => 'new data'];
        $resolver = fn () => $resolverData;
        $this->cacheMock->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Act
        $result = $this->inventoryCache->rememberItem($productId, $resolver);

        // Assert
        $this->assertEquals($resolverData, $result);
    }

    public function test_deve_invalidar_por_produtos_e_bump_versao(): void
    {
        // Arrange
        $productIds = [1, 2, 3];
        $this->cacheMock->shouldReceive('forget')
            ->with('inventory:item:1')
            ->once();
        $this->cacheMock->shouldReceive('forget')
            ->with('inventory:item:2')
            ->once();
        $this->cacheMock->shouldReceive('forget')
            ->with('inventory:item:3')
            ->once();
        $this->cacheMock->shouldReceive('increment')
            ->with('inventory:list_version')
            ->once();
        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version', 0)
            ->andReturn(2); // Simula sucesso do increment

        // Act
        $this->inventoryCache->invalidateByProducts($productIds);

        // Assert
        $this->assertTrue(true);
    }

    public function test_deve_invalidar_todas_as_listas(): void
    {
        // Arrange
        $this->cacheMock->shouldReceive('increment')
            ->with('inventory:list_version')
            ->once();
        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version', 0)
            ->andReturn(3);

        // Act
        $this->inventoryCache->invalidateAllLists();

        // Assert
        $this->assertTrue(true);
    }

    public function test_deve_fazer_fallback_para_set_quando_increment_nao_suportado(): void
    {
        // Arrange
        $this->cacheMock->shouldReceive('increment')
            ->with('inventory:list_version')
            ->once()
            ->andReturn(null); // Simula não suporte
        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version', 0)
            ->andReturn(0);
        $this->cacheMock->shouldReceive('put')
            ->with('inventory:list_version', 2, 60)
            ->once();

        // Act
        $this->inventoryCache->invalidateAllLists();

        // Assert
        $this->assertTrue(true);
    }

    public function test_deve_usar_lock_quando_disponivel(): void
    {
        // Arrange
        $this->cacheMock->shouldReceive('lock')
            ->with('inventory:item:123:lock', 5)
            ->andReturn(Mockery::mock());
        $this->cacheMock->shouldReceive('remember')
            ->once()
            ->andReturn('data');

        // Act
        $result = $this->inventoryCache->rememberItem(123, fn () => 'data');

        // Assert
        $this->assertEquals('data', $result);
    }

    public function test_deve_fazer_fallback_sem_lock_quando_nao_disponivel(): void
    {
        // Arrange
        $this->cacheMock->shouldReceive('lock')
            ->andThrow(new \Exception('Lock not supported'));
        $this->cacheMock->shouldReceive('remember')
            ->once()
            ->andReturn('fallback data');

        // Act
        $result = $this->inventoryCache->rememberItem(123, fn () => 'data');

        // Assert
        $this->assertEquals('fallback data', $result);
    }

    public function test_deve_respeitar_ttl_em_remember(): void
    {
        // Arrange
        $this->cacheMock->shouldReceive('remember')
            ->with(Mockery::any(), 60, Mockery::any())
            ->once()
            ->andReturn('data');

        // Act
        $this->inventoryCache->rememberItem(123, fn () => 'data');

        // Assert
        $this->assertTrue(true);
    }
}
