<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Inventory\UseCases;

use App\Application\Inventory\UseCases\CleanupOldInventory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CleanupOldInventory::class)]
final class CleanupOldInventoryTest extends TestCase
{
    /**
     * Cenário
     * Dado: operações de limpeza que usam o facade DB para deletar/atualizar e Cache para invalidar tags
     * Quando: `CleanupOldInventory::handle(bool $normalize)` é executado
     * Então: retorna um array com chaves `removed_orphans`, `removed_stale` e `normalized`, e invalida caches
     * Regras de Negócio Relevantes:
     *  - A operação é executada dentro de uma transação (DB::transaction).
     *  - Se `normalize` for false, nenhuma atualização (update) deve ocorrer.
     * Observações:
     *  - Usamos mocks no facade DB para simular consultas encadeadas.
     *  - Espera-se que as tags `inventory` e `products` sejam flushadas no cache.
     */
    public function test_retorna_zeros_quando_nao_ha_alteracoes(): void
    {
        // Arrange
        // Quando não há órfãos, nem stale, nem negativos
        DB::shouldReceive('transaction')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        // Simular deletes/updates retornando 0
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('whereNotExists')->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(0);
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(0);

        $this->expectCacheInvalidation();

        $useCase = new CleanupOldInventory;

        // Act
        $res = $useCase->handle(true);

        // Assert
        $this->assertIsArray($res);
        $this->assertSame(0, $res['removed_orphans']);
        $this->assertSame(0, $res['removed_stale']);
        $this->assertSame(0, $res['normalized']);
    }

    public function test_remove_orfaos_e_stale_e_normaliza(): void
    {
        // Arrange
        // Forçar execução da transação
        DB::shouldReceive('transaction')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        // Primeiro call: tabela inventory -> whereNotExists -> delete (orphan)
        $mockQuery = $this->makeMockQuery(deleteReturn: 3, updateReturn: 5);

        // Para simular comportamento encadeado do facade DB::table(...)
        DB::shouldReceive('table')->andReturnUsing(function ($table) use ($mockQuery) {
            return $mockQuery;
        });

        $this->expectCacheInvalidation();

        $useCase = new CleanupOldInventory;

        // Act
        $res = $useCase->handle(true);

        // Assert
        $this->assertSame(3, $res['removed_orphans']);
        $this->assertSame(3, $res['removed_stale']); // same mock returns 3 for delete on stale
        $this->assertSame(5, $res['normalized']);
    }

    public function test_nao_normalizar_quando_flag_false(): void
    {
        // Arrange
        DB::shouldReceive('transaction')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        $mockQuery = $this->makeMockQuery(deleteReturn: 1, updateReturn: null);

        DB::shouldReceive('table')->andReturnUsing(function ($table) use ($mockQuery) {
            return $mockQuery;
        });

        $this->expectCacheInvalidation();

        $useCase = new CleanupOldInventory;

        // Act
        $res = $useCase->handle(false);

        // Assert
        $this->assertSame(1, $res['removed_orphans']);
        $this->assertSame(1, $res['removed_stale']);
        $this->assertSame(0, $res['normalized']);
    }

    /**
     * Constrói um mock de query encadeada para DB::table(...)
     *
     * @param  int|null  $deleteReturn  valor retornado por delete()
     * @param  int|null  $updateReturn  valor retornado por update(), null significa que update não está presente
     */
    private function makeMockQuery(?int $deleteReturn = 0, ?int $updateReturn = 0): object
    {
        return new class($deleteReturn, $updateReturn)
        {
            private $deleteReturn;

            private $updateReturn;

            public function __construct($deleteReturn, $updateReturn)
            {
                $this->deleteReturn = $deleteReturn;
                $this->updateReturn = $updateReturn;
            }

            public function whereNotExists($cb)
            {
                return $this;
            }

            public function delete()
            {
                return $this->deleteReturn;
            }

            public function where($col, $op, $val = null)
            {
                return $this;
            }

            public function update($arr)
            {
                return $this->updateReturn ?? 0;
            }
        };
    }

    private function expectCacheInvalidation(): void
    {
        // Esperar que o cache seja invalidado (flush das tags inventory e products)
        Cache::shouldReceive('tags')->with(['inventory'])->once()->andReturnSelf();
        Cache::shouldReceive('flush')->once();
        Cache::shouldReceive('tags')->with(['products'])->once()->andReturnSelf();
        Cache::shouldReceive('flush')->once();
    }
}
