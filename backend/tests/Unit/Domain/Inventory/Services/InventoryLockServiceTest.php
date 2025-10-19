<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Inventory\Services;

use App\Domain\Inventory\Services\InventoryLockService;
use App\Infrastructure\Locks\RedisLock;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Testes unitários para InventoryLockService
 *
 * Cenário e suposições:
 * - InventoryLockService delega a lógica de lock para uma implementação de baixo
 *   nível (`RedisLock`) através do método `run($key, $ttl, $callback, $wait)`.
 * - Aqui usamos mocks para o `RedisLock` para garantir isolamento completo e
 *   determinismo dos testes; não tentamos abrir conexões reais ao Redis.
 * - Validamos comportamento de aquisição única (`lock`) e múltipla (`lockMany`),
 *   incluindo ordenação determinística de IDs, propagação de exceções e
 *   encaminhamento dos parâmetros TTL / WAIT.
 */
/**
 * Cenário
 * Dado: uma implementação de lock de baixo nível (`RedisLock`) que expõe `lock(...)->block(...)/release()` e um serviço `InventoryLockService` que o utiliza
 * Quando: os métodos `lock(id, callback, ttl, wait)` e `lockMany(ids, callback)` são invocados
 * Então: deve adquirir locks nas chaves corretas, propagar exceções quando uma aquisição falhar, e liberar/ordenar locks conforme esperado
 * Regras de Negócio Relevantes:
 *  - Locks devem ser únicos por produto: chave `lock:inventory:product:{id}`.
 *  - `lockMany` deve ordenar ids e remover duplicatas antes de adquirir locks.
 *  - Em falha de aquisição, exceções do lock subjacente são propagadas.
 * Observações:
 *  - Os testes usam stores/locks fakes para evitar dependências externas (Redis).
 */
final class InventoryLockServiceTest extends TestCase
{
    public function test_deve_adquirir_lock_com_sucesso(): void
    {
        /**
         * Cenário
         * Dado: implementação RedisLock com store fake que fornece lock fake
         * Quando: InventoryLockService::lock(id, callback, ttl, wait) é chamado
         * Então: callback é executado e os parâmetros key/ttl/wait são encaminhados corretamente
         */
        // Arrange: construímos um RedisLock real com um CacheFactory falso que
        // retorna um store/lock fake para capturar key/ttl/wait e executar o callback.
        $captured = [];

        $fakeLock = new class($captured)
        {
            private array $captured;

            public function __construct(array &$captured)
            {
                $this->captured = &$captured;
            }

            public function block(int $waitSeconds, int $sleepMs): bool
            {
                $this->captured['wait']  = $waitSeconds;
                $this->captured['sleep'] = $sleepMs;

                return true;
            }

            public function release(): void
            {
                $this->captured['released'] = true;
            }
        };

        $fakeStore = new class($fakeLock, $captured)
        {
            private $lock;

            private array $captured;

            public function __construct($lock, array &$captured)
            {
                $this->lock     = $lock;
                $this->captured = &$captured;
            }

            public function lock(string $key, int $ttl)
            {
                $this->captured['key'] = $key;
                $this->captured['ttl'] = $ttl;

                return $this->lock;
            }
        };

        $cache = $this->createMock(CacheFactory::class);
        $cache->method('store')->willReturn($fakeStore);

        $redis = new RedisLock($cache);

        // Act
        $sut = new InventoryLockService($redis);

        // Act
        $result = $sut->lock(7, fn () => 'ok', 10, 5);

        // Assert
        $this->assertSame('ok', $result);
        $this->assertArrayHasKey('key', $captured);
        $this->assertSame('lock:inventory:product:7', $captured['key']);
        $this->assertSame(10, $captured['ttl']);
        $this->assertSame(5, $captured['wait']);
    }

    public function test_nao_deve_adquirir_lock_ja_ocupado(): void
    {
        /**
         * Cenário
         * Dado: lock ocupado (block retorna false)
         * Quando: InventoryLockService::lock for chamado
         * Então: RuntimeException informando impossibilidade de obter lock é lançada
         */
        // Arrange: simular falha de aquisição (block retorna false) -> exceção do RedisLock
        $fakeLock = new class
        {
            public function block(int $waitSeconds, int $sleepMs): bool
            {
                return false; // não consegue obter
            }

            public function release(): void
            {
                // nop
            }
        };

        $fakeStore = new class($fakeLock)
        {
            private $lock;

            public function __construct($lock)
            {
                $this->lock = $lock;
            }

            public function lock(string $key, int $ttl)
            {
                return $this->lock;
            }
        };

        $cache = $this->createMock(CacheFactory::class);
        $cache->method('store')->willReturn($fakeStore);

        $redis = new RedisLock($cache);
        // Arrange continued
        $sut = new InventoryLockService($redis);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Não foi possível obter lock: lock:inventory:product:1');

        // Act
        $sut->lock(1, fn () => 'never');
    }

    public function test_lock_many_deve_aquirir_multiplos_locks_em_ordem_deterministica(): void
    {
        /**
         * Cenário
         * Dado: vários ids com duplicatas e em ordem aleatória
         * Quando: lockMany(ids, callback) é invocado
         * Então: locks são adquiridos em ordem determinística (ordenados e deduplicados)
         */
        // Arrange: captura das chaves na ordem de execução
        $seen = [];

        // Arrange: construir fakeLock/store que registra cada chave pedida
        $seen = [];

        $fakeLock = new class($seen)
        {
            private $seen;

            public function __construct(array &$seen)
            {
                $this->seen = &$seen;
            }

            public function block(int $waitSeconds, int $sleepMs): bool
            {
                // nothing special, allow
                return true;
            }

            public function release(): void
            {
                // nop
            }
        };

        $fakeStore = new class($fakeLock, $seen)
        {
            private $lock;

            private $seen;

            public function __construct($lock, array &$seen)
            {
                $this->lock = $lock;
                $this->seen = &$seen;
            }

            public function lock(string $key, int $ttl)
            {
                $this->seen[] = $key;

                return $this->lock;
            }
        };

        $cache = $this->createMock(CacheFactory::class);
        $cache->method('store')->willReturn($fakeStore);

        $redis = new RedisLock($cache);
        $sut   = new InventoryLockService($redis);

        // Act: passar ids não ordenados e com duplicatas
        $result = $sut->lockMany([3, 1, 2, 2], fn () => 'done');

        // Assert
        $this->assertSame('done', $result);

        // espera-se ordem crescente única: 1,2,3
        $this->assertSame(['lock:inventory:product:1', 'lock:inventory:product:2', 'lock:inventory:product:3'], $seen);
    }

    public function test_lock_many_propagates_exception_when_one_lock_fails(): void
    {
        /**
         * Cenário
         * Dado: uma das aquisições de lock falha (lançando RuntimeException)
         * Quando: lockMany é chamado
         * Então: exceção é propagada e o erro não é silenciado
         */
        // Arrange
        // Arrange: fakeStore/lock que lança exceção quando a chave corresponde a product:2
        $fakeLock1 = new class
        {
            public function block(int $waitSeconds, int $sleepMs): bool
            {
                return true;
            }

            public function release(): void {}
        };

        $fakeLock2 = new class
        {
            public function block(int $waitSeconds, int $sleepMs): bool
            {
                throw new RuntimeException('falha ao adquirir 2');
            }

            public function release(): void {}
        };

        $fakeStore = new class($fakeLock1, $fakeLock2)
        {
            private $a;

            private $b;

            public function __construct($a, $b)
            {
                $this->a = $a;
                $this->b = $b;
            }

            public function lock(string $key, int $ttl)
            {
                if ($key === 'lock:inventory:product:2') {
                    return $this->b;
                }

                return $this->a;
            }
        };

        $cache = $this->createMock(CacheFactory::class);
        $cache->method('store')->willReturn($fakeStore);

        $redis = new RedisLock($cache);
        $sut   = new InventoryLockService($redis);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('falha ao adquirir 2');

        // Act: chamada deve propagar a exceção lançada pelo lock
        $sut->lockMany([1, 2, 3], fn () => 'irrelevant');
    }

    public function test_lock_encaminha_ttl_e_wait_personalizados(): void
    {
        /**
         * Cenário
         * Dado: parâmetros ttl e wait personalizados
         * Quando: lock(id, callback, ttl, wait) é invocado
         * Então: valores ttl e wait são encaminhados para o lock subjacente
         */
        // Arrange
        $captured = [];

        $fakeLock = new class($captured)
        {
            private $captured;

            public function __construct(array &$captured)
            {
                $this->captured = &$captured;
            }

            public function block(int $waitSeconds, int $sleepMs): bool
            {
                $this->captured['wait']  = $waitSeconds;
                $this->captured['sleep'] = $sleepMs;

                return true;
            }

            public function release(): void
            {
                $this->captured['released'] = true;
            }
        };

        $fakeStore = new class($fakeLock, $captured)
        {
            private $lock;

            private $captured;

            public function __construct($lock, array &$captured)
            {
                $this->lock     = $lock;
                $this->captured = &$captured;
            }

            public function lock(string $key, int $ttl)
            {
                $this->captured['key'] = $key;
                $this->captured['ttl'] = $ttl;

                return $this->lock;
            }
        };

        $cache = $this->createMock(CacheFactory::class);
        $cache->method('store')->willReturn($fakeStore);

        $redis = new RedisLock($cache);
        $sut   = new InventoryLockService($redis);

        // Act
        $sut->lock(9, fn () => 'x', 20, 30);

        // Assert
        $this->assertArrayHasKey('ttl', $captured);
        $this->assertArrayHasKey('wait', $captured);
        $this->assertSame(20, $captured['ttl']);
        $this->assertSame(30, $captured['wait']);
    }
}
