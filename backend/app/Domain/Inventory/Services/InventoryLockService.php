<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Infrastructure\Locks\RedisLock;
use Closure;

/**
 * Serviço para aquisição de locks por produto no contexto de operações de inventário.
 *
 * Responsabilidade:
 * - Fornecer primitives seguras para executar callbacks sob um lock associado a um produto
 *   (ou a um conjunto de produtos) evitando condições de corrida durante alterações
 *   concorrentes de inventário.
 *
 * Contratos principais:
 * - `lock(int $productId, Closure $callback, int $ttlSeconds = 10, int $waitSeconds = 5)`:
 *     Adquire um lock por `productId` e executa `$callback` enquanto o lock estiver mantido.
 *     `ttlSeconds` define por quanto tempo o lock expira; `waitSeconds` define quanto tempo
 *     aguardar para adquiri-lo.
 * - `lockMany(array $productIds, Closure $callback, int $ttlSeconds = 15, int $waitSeconds = 8)`:
 *     Adquire locks para múltiplos produtos em ordem determinística (sorted) para evitar deadlocks
 *     e só executa `$callback` após ter adquirido todos os locks solicitados.
 *
 * Observações de implementação:
 * - Internamente este serviço delega a implementação concreta do lock para
 *   {@see App\Infrastructure\Locks\RedisLock} (ou outro implementation fornecida),
 *   por isso assume que a implementação suporta `run($key, $ttl, $callback, $wait)`.
 * - `lockMany` garante ordenação dos IDs antes de adquirir locks para prevenir ciclos e deadlocks.
 * - Recomenda-se configurar TTLs e estratégias de retry/alert em produção para evitar liveness issues
 *   em casos de crash do processo que segurou o lock.
 */
final class InventoryLockService
{
    public function __construct(
        private readonly RedisLock $lock
    ) {}

    /**
     * Adquire um lock escopado ao produto e executa o callback fornecido.
     * Garante exclusão mútua para operações que alteram o inventário do produto.
     *
     * @param  int  $productId  Identificador do produto
     * @param  Closure(): mixed  $callback  Callback executado enquanto o lock está ativo
     * @param  int  $ttlSeconds  TTL do lock em segundos
     * @param  int  $waitSeconds  Segundos a aguardar para adquirir o lock
     * @return mixed Resultado retornado pelo callback
     */
    public function lock(int $productId, Closure $callback, int $ttlSeconds = 10, int $waitSeconds = 5): mixed
    {
        $key = $this->keyForProduct($productId);

        return $this->lock->run($key, $ttlSeconds, $callback, $waitSeconds);
    }

    /**
     * Adquire locks para múltiplos produtos em ordem determinística para evitar
     * deadlocks e, em seguida, executa o callback.
     *
     * @param  int[]  $productIds  Lista de IDs de produto
     * @param  Closure(): mixed  $callback  Callback executado após adquirir todos os locks
     * @param  int  $ttlSeconds  TTL do lock em segundos
     * @param  int  $waitSeconds  Segundos a aguardar por aquisição de cada lock
     * @return mixed Resultado retornado pelo callback
     */
    public function lockMany(array $productIds, Closure $callback, int $ttlSeconds = 15, int $waitSeconds = 8): mixed
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        sort($ids); // ordem fixa

        $keys = array_map(fn (int $id) => $this->keyForProduct($id), $ids);

        $runner = function (array $k, Closure $cb) use (&$runner, $ttlSeconds, $waitSeconds) {
            if (empty($k)) {
                return $cb();
            }
            $key = array_shift($k);

            return $this->lock->run($key, $ttlSeconds, fn () => $runner($k, $cb), $waitSeconds);
        };

        return $runner($keys, $callback);
    }

    private function keyForProduct(int $productId): string
    {
        return "lock:inventory:product:{$productId}";
    }
}
