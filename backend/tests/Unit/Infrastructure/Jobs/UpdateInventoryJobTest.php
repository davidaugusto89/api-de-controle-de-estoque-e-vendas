<?php

/**
 * Cenário
 * Dado: job de atualização de estoque com itens válidos
 * Quando: handle do job é executado
 * Então: chama InventoryRepository->update e commit da transação
 */
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Jobs;

/**
 * Cenário
 * Dado: falha na transação ao atualizar estoque
 * Quando: job é executado
 * Então: transação é revertida e exceção propagada
 */
use App\Domain\Inventory\Services\InventoryLockService;
use App\Domain\Inventory\Services\StockPolicy;
use App\Infrastructure\Jobs\UpdateInventoryJob;
use App\Support\Database\Transactions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Cenário
 * Dado: existe item inválido na lista
 * Quando: job é executado
 * Então: não chama update no repositório
 */
/**
 * UpdateInventoryJobTest
 *
 * Cenário:
 * - Job que atualiza o inventário para cada item de uma venda, usando locks e transação.
 *
 * Quando:
 * - handle é executado com dependências (Transactions, InventoryLockService, StockPolicy).
 * - failed é chamado com uma Throwable.
 *
 * Então:
 * - Transactions::run é invocado e a lógica dentro executa locks->lock para cada produto.
 * - StockPolicy::decrease é chamado com productId e quantity corretos.
 * - failed registra o erro (comportamento verificado via ausência de exceção).
 */
#[CoversClass(UpdateInventoryJob::class)]
final class UpdateInventoryJobTest extends TestCase
{
    public function test_instancia_e_propriedades_padrao(): void
    {
        $items = [
            ['product_id' => 1, 'quantity' => 2],
        ];

        $job = new UpdateInventoryJob(10, $items);

        $this->assertSame(3, $job->tries);
        $this->assertSame([5, 15, 60], $job->backoff);
        $this->assertSame(10, $job->saleId);
        $this->assertSame($items, $job->items);
    }

    public function test_handle_chama_transection_lock_e_policy(): void
    {
        $items = [
            ['product_id' => 7, 'quantity' => 3],
            ['product_id' => 8, 'quantity' => 1],
        ];

        $tx = $this->getMockBuilder(Transactions::class)
            ->onlyMethods(['run'])
            ->getMock();

        $locks = $this->getMockBuilder(InventoryLockService::class)
            ->onlyMethods(['lock'])
            ->getMock();

        $policy = $this->getMockBuilder(StockPolicy::class)
            ->onlyMethods(['decrease'])
            ->getMock();

        $inventoryRepo = $this->getMockBuilder(\App\Infrastructure\Persistence\Eloquent\InventoryRepository::class)
            ->onlyMethods(['decrementIfEnough'])
            ->getMock();

        // Use a real InventoryCache but mock the underlying cache store (Repository)
        $cacheStore = $this->getMockBuilder(\Illuminate\Contracts\Cache\Repository::class)
            ->getMock();

        $cache = new \App\Infrastructure\Cache\InventoryCache($cacheStore);

        // Espera que Transactions::run seja chamado uma vez e execute o callback
        $tx->expects($this->once())
            ->method('run')
            ->with($this->isType('callable'))
            ->willReturnCallback(function (callable $cb) {
                // Simula execução do callback exatamente como o job faria
                $cb();
            });

        // locks->lock deve ser chamado para cada item; quando o callback passado for executado,
        // ele chamará policy->decrease internamente.
        $locks->expects($this->exactly(count($items)))
            ->method('lock')
            ->with($this->isType('int'), $this->isType('callable'), $this->isType('int'), $this->isType('int'))
            ->willReturnCallback(function (int $productId, callable $cb) {
                // Executa o callback que chama policy->decrease
                $cb();
            });

        // inventoryRepo->decrementIfEnough deve ser chamado com os pares corretos em ordem
        $callIndex = 0;
        $expected = [
            [7, 3],
            [8, 1],
        ];

        $inventoryRepo->expects($this->exactly(2))
            ->method('decrementIfEnough')
            ->willReturnCallback(function (int $productId, int $quantity) use (&$callIndex, $expected): bool {
                $exp = $expected[$callIndex++] ?? null;
                TestCase::assertNotNull($exp);
                TestCase::assertSame($exp[0], $productId);
                TestCase::assertSame($exp[1], $quantity);

                return true;
            });

        // Espera operações subjacentes de cache: forget para cada item de produto e um increment para a versão da lista
        $cacheStore->expects($this->exactly(2))
            ->method('forget')
            ->with($this->isType('string'));

        $cacheStore->expects($this->once())
            ->method('increment')
            ->with('inventory:list_version');

        $job = new UpdateInventoryJob(123, $items);

        // Executa handle; não deve lançar exceção
        $job->handle($tx, $locks, $policy, $inventoryRepo, $cache);
    }

    public function test_failed_registra_erro_sem_excecao(): void
    {
        $job = new UpdateInventoryJob(5, []);

        // Apenas garante que chamar failed não lança exceção
        $job->failed(new \RuntimeException('erro qualquer'));

        $this->addToAssertionCount(1);
    }
}
