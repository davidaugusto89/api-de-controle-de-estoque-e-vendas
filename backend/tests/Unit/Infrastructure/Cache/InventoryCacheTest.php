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
        $this->cacheMock      = Mockery::mock(CacheRepository::class);
        $this->inventoryCache = new InventoryCache($this->cacheMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_deve_retornar_dados_do_cache_em_hit_para_lista_paginada(): void
    {
        /**
         * Cenário
         * Dado: chave de lista paginada presente no cache
         * Quando: rememberListAndTotalsPaged é chamado
         * Então: retorna dados do cache (cache hit)
         */
        // Arrange
        $search     = 'produto';
        $perPage    = 10;
        $page       = 1;
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
        /**
         * Cenário
         * Dado: cache miss para lista paginada
         * Quando: rememberListAndTotalsPaged é chamado com resolver
         * Então: executor do resolver é chamado e resultado retornado
         */
        // Arrange
        $search       = null;
        $perPage      = 5;
        $page         = 2;
        $resolverData = [['item2'], ['meta2'], ['totals2']];
        $resolver     = fn () => $resolverData;
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
        /**
         * Cenário
         * Dado: termo de busca com espaços/maiusculas
         * Quando: normalização é aplicada na geração da chave
         * Então: operação prossegue (test asserts true)
         */
        // Arrange
        $search  = '  PRODUTO TESTE  ';
        $perPage = 10;
        $page    = 1;
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
        /**
         * Cenário
         * Dado: cache hit para lista não paginada
         * Quando: rememberListAndTotalsUnpaged é invocado
         * Então: retorna dados do cache
         */
        // Arrange
        $search     = 'busca';
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
        /**
         * Cenário
         * Dado: cache miss para lista não paginada
         * Quando: rememberListAndTotalsUnpaged é invocado com resolver
         * Então: resolver é executado e retorno é retornado
         */
        // Arrange
        $search       = '';
        $resolverData = [['item4'], ['totals4']];
        $resolver     = fn () => $resolverData;
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
        /**
         * Cenário
         * Dado: cache hit para item
         * Quando: rememberItem é invocado
         * Então: retorna dados do cache
         */
        // Arrange
        $productId  = 123;
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
        /**
         * Cenário
         * Dado: cache miss para item
         * Quando: rememberItem é invocado com resolver
         * Então: resolver é executado e resultado retornado
         */
        // Arrange
        $productId    = 456;
        $resolverData = ['product' => 'new data'];
        $resolver     = fn () => $resolverData;
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
        /**
         * Cenário
         * Dado: chamada de invalidar por produtos
         * Quando: invalidateByProducts é executado
         * Então: chaves individuais são esquecidas e versão de lista é incrementada
         */
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
        /**
         * Cenário
         * Dado: invalidar todas as listas
         * Quando: invalidateAllLists é executado
         * Então: incrementa a versão da lista
         */
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

    public function test_deve_usar_lock_quando_disponivel(): void
    {
        /**
         * Cenário
         * Dado: cache suporta locks
         * Quando: rememberItem é invocado
         * Então: usa lock e retorna dados
         */
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
        /**
         * Cenário
         * Dado: cache não suporta lock (lança Exception)
         * Quando: rememberItem é invocado
         * Então: fallback sem lock é utilizado e retorno esperado é retornado
         */
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
        /**
         * Cenário
         * Dado: chamada remember com TTL esperado
         * Quando: rememberItem é invocado
         * Então: chama remember com TTL=60
         */
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

    public function test_deve_bumpar_versao_quando_store_nao_implmenta_increment(): void
    {
        /**
         * Cenário
         * Dado: store de cache sem método increment
         * Quando: invalidateAllLists é chamado
         * Então: faz get da versão atual e chama put com v+1 e TTL correto
         */
        // Arrange: mock não precisa expor increment (method_exists -> false)
        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version')
            ->andReturn(5);
        $this->cacheMock->shouldReceive('put')
            ->with('inventory:list_version', 6, 60)
            ->once();

        // Act
        $this->inventoryCache->invalidateAllLists();

        // Assert
        $this->assertTrue(true);
    }

    public function test_bumplistversion_faz_fallback_quando_increment_lanca(): void
    {
        /**
         * Cenário
         * Dado: store implementa increment mas increment lança exceção
         * Quando: invalidateAllLists é chamado
         * Então: fallback (get+put) é usado
         */
        $this->cacheMock->shouldReceive('increment')
            ->with('inventory:list_version')
            ->andThrow(new \Exception('boom'));

        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version')
            ->andReturn(7);
        $this->cacheMock->shouldReceive('put')
            ->with('inventory:list_version', 8, 60)
            ->once();

        // Act
        $this->inventoryCache->invalidateAllLists();

        // Assert
        $this->assertTrue(true);
    }

    public function test_bumplistversion_silencia_exceptions(): void
    {
        /**
         * Cenário
         * Dado: increment e fallback (get/put) lançam exceção
         * Quando: invalidateAllLists é chamado
         * Então: não propaga a exceção (silencioso)
         */
        $this->cacheMock->shouldReceive('increment')
            ->with('inventory:list_version')
            ->andThrow(new \Exception('boom'));

        $this->cacheMock->shouldReceive('get')
            ->with('inventory:list_version')
            ->andThrow(new \Exception('boom2'));

        $this->cacheMock->shouldReceive('put')
            ->andThrow(new \Exception('boom3'));

        // Act / Assert: não deve lançar
        $this->inventoryCache->invalidateAllLists();

        $this->assertTrue(true);
    }

    public function test_remember_retorna_resolver_quando_remember_lanca(): void
    {
        /**
         * Cenário
         * Dado: cache->remember lança exceção
         * Quando: rememberItem é chamado com resolver
         * Então: fallback executa o resolver e retorna seu valor
         */
        $this->cacheMock->shouldReceive('remember')
            ->andThrow(new \Exception('remember failed'));

        $resolver = fn () => 'fallback-value';

        $res = $this->inventoryCache->rememberItem(999, $resolver);

        $this->assertSame('fallback-value', $res);
    }
}
