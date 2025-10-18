<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Locks;

use App\Infrastructure\Locks\RedisLock;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Cenário: testes unitários para lock distribuído via Redis (classe RedisLock).
 *
 * Suposições e justificativas:
 * - Não usamos um servidor Redis real: todos os comportamentos externos (store/lock)
 *   são simulados via fakes determinísticos. Isso garante isolamento e velocidade.
 * - Um relógio fake (FakeClock) controla a passagem de tempo para testar TTL/expiração
 *   sem esperas reais.
 * - O lock expira com base em TTL (segundos) e a tentativa de bloqueio suporta
 *   espera ativa simulada (block com wait/sleepMs), implementada no fake.
 *
 * Limites:
 * - Os fakes simulam apenas a interface usada por RedisLock: store()->lock(...)->block(...)
 *   e ->release(). Não implementamos toda a API da cache do Laravel.
 * - Testes cobrem caminhos felizes, falha ao obter lock, expiração, reutilização e
 *   idempotência de release.
 */
final class RedisLockTest extends TestCase
{
    public function test_deve_adquirir_e_liberar_lock(): void
    {
        // Arrange
        $clock = new FakeClock(1_600_000_000);
        $store = new FakeStore($clock);
        $cache = $this->createMock(CacheFactory::class);
        $cache->method('store')->willReturn($store);

        $redisLock = new RedisLock($cache);

        $executed = false;

        // Act
        $result = $redisLock->run('key-1', 10, function () use (&$executed) {
            $executed = true;

            return 'ok';
        }, 0, 100);

        // Assert
        $this->assertTrue($executed, 'Callback should be executed');
        $this->assertSame('ok', $result);
        $this->assertFalse($store->isLocked('key-1'), 'Lock must be released after run');
    }

    public function test_nao_deve_permitir_lock_duplicado(): void
    {
        // Arrange
        $clock = new FakeClock(1_600_000_000);
        $store = new FakeStore($clock);
        $cache = $this->createMock(CacheFactory::class);
        $cache->method('store')->willReturn($store);

        // pre-acquire para simular lock já existente
        $store->preAcquire('key-dup', 30);

        $redisLock = new RedisLock($cache);

        // Act / Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Não foi possível obter lock: key-dup');

        $redisLock->run('key-dup', 30, function () {
            return 'should-not-run';
        }, 0, 100);

        $this->assertTrue($store->isLocked('key-dup'));
    }

    public function test_deve_expirar_automaticamente(): void
    {
        // Arrange
        $clock = new FakeClock(1_600_000_000);
        $store = new FakeStore($clock);
        $cache = $this->createMock(CacheFactory::class);
        $cache->method('store')->willReturn($store);

        // pre-acquire com TTL curto (1 segundo)
        $store->preAcquire('key-exp', 1);

        $redisLock = new RedisLock($cache);

        // Act: espera até expirar (block faz avanço de tempo simulado)
        $result = $redisLock->run('key-exp', 1, function () {
            return 'acquired-after-expiry';
        }, 3, 1000); // waitSeconds=3, sleepMs=1000 (1s)

        // Assert
        $this->assertSame('acquired-after-expiry', $result);
        $this->assertFalse($store->isLocked('key-exp'));
    }

    public function test_deve_liberar_idempotente(): void
    {
        // Arrange
        $clock = new FakeClock(1_600_000_000);
        $store = new FakeStore($clock);

        // Acquire directly via store->lock and call release duas vezes
        $lock = $store->lock('key-idem', 5);

        // Act
        $acquired = $lock->block(0, 100);
        $this->assertTrue($acquired);

        // First release
        $lock->release();

        // Second release (idempotent - must not throw)
        $lock->release();

        // Assert
        $this->assertFalse($store->isLocked('key-idem'));
    }

    public function test_reutilizacao_da_chave_apos_liberacao(): void
    {
        // Arrange
        $clock = new FakeClock(1_600_000_000);
        $store = new FakeStore($clock);
        $cache = $this->createMock(CacheFactory::class);
        $cache->method('store')->willReturn($store);

        $redisLock = new RedisLock($cache);

        // Act - first run
        $first = $redisLock->run('key-reuse', 10, function () {
            return 'first';
        }, 0, 100);

        // Act - second run (should be allowed since first released)
        $second = $redisLock->run('key-reuse', 10, function () {
            return 'second';
        }, 0, 100);

        // Assert
        $this->assertSame('first', $first);
        $this->assertSame('second', $second);
        $this->assertFalse($store->isLocked('key-reuse'));
    }

    public function test_callback_exception_releases_lock(): void
    {
        // Arrange
        $clock = new FakeClock(1_600_000_000);
        $store = new FakeStore($clock);
        $cache = $this->createMock(CacheFactory::class);
        $cache->method('store')->willReturn($store);

        $redisLock = new RedisLock($cache);

        // Act / Assert: callback lança exceção
        $this->expectException(RuntimeException::class);

        try {
            $redisLock->run('key-exc', 10, function () {
                throw new RuntimeException('callback fail');
            }, 0, 100);
        } finally {
            // Após exceção, lock deve estar liberado
            $this->assertFalse($store->isLocked('key-exc'));
        }
    }
}

// --- Fakes utilitários abaixo ---

/**
 * Relógio fake simples, em segundos.
 */
final class FakeClock
{
    private float $time;

    public function __construct(float $initial)
    {
        $this->time = $initial;
    }

    public function now(): float
    {
        return $this->time;
    }

    public function advanceMs(int $ms): void
    {
        $this->time += $ms / 1000;
    }
}

/**
 * FakeStore simula store()->lock(...). Mantém estado de locks por chave e TTL.
 */
final class FakeStore
{
    /** @var array<string,float> */
    private array $locks = [];

    public function __construct(private readonly FakeClock $clock) {}

    public function lock(string $key, int $ttlSeconds): FakeLock
    {
        return new FakeLock($key, $ttlSeconds, $this, $this->clock);
    }

    public function preAcquire(string $key, int $ttlSeconds): void
    {
        $this->locks[$key] = $this->clock->now() + $ttlSeconds;
    }

    public function isLocked(string $key): bool
    {
        if (! isset($this->locks[$key])) {
            return false;
        }

        if ($this->locks[$key] <= $this->clock->now()) {
            unset($this->locks[$key]);

            return false;
        }

        return true;
    }

    /**
     * Tenta adquirir o lock de forma atômica; retorna true se adquirido.
     */
    public function tryAcquire(string $key, int $ttlSeconds): bool
    {
        if ($this->isLocked($key)) {
            return false;
        }

        $this->locks[$key] = $this->clock->now() + $ttlSeconds;

        return true;
    }

    public function releaseKey(string $key): void
    {
        unset($this->locks[$key]);
    }
}

/**
 * FakeLock com comportamento de block(waitSeconds, sleepMs) e release idempotente.
 */
final class FakeLock
{
    private bool $acquired = false;

    public function __construct(
        private readonly string $key,
        private readonly int $ttlSeconds,
        private readonly FakeStore $store,
        private readonly FakeClock $clock
    ) {}

    public function block(int $waitSeconds, int $sleepMs): bool
    {
        $deadline = $this->clock->now() + $waitSeconds;

        while ($this->clock->now() <= $deadline) {
            if ($this->store->tryAcquire($this->key, $this->ttlSeconds)) {
                $this->acquired = true;

                return true;
            }

            // Avança o relógio de forma simulada (nenhum sleep real)
            $this->clock->advanceMs($sleepMs);
        }

        return false;
    }

    public function release(): void
    {
        // idempotente
        if ($this->acquired || $this->store->isLocked($this->key)) {
            $this->store->releaseKey($this->key);
            $this->acquired = false;
        }
    }
}
