<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Inventory\UseCases;

use App\Application\Inventory\UseCases\RegisterStockEntry;
use App\Domain\Inventory\Services\InventoryLockService;
use App\Domain\Inventory\Services\StockPolicy;
use App\Support\Database\Transactions;
use Carbon\Carbon;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Testes unitários para o UseCase RegisterStockEntry.
 *
 * Estratégia:
 * - Injetamos closures (resolvers) para retornar "query builders" simulados
 *   para Product e Inventory; assim evitamos alias/overload mocks nos models.
 * - Usamos objetos anônimos que implementam save() quando precisamos que o
 *   model persista alterações.
 */
#[CoversClass(RegisterStockEntry::class)]
final class RegisterStockEntryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_rejeita_quantidade_nao_positiva(): void
    {
        /**
         * Cenário
         * Dado: o caso de uso `RegisterStockEntry` que registra entradas de estoque
         * Quando: `execute(productId, quantity, unitCost)` é invocado com quantity <= 0
         * Então: espera-se InvalidArgumentException com mensagem apropriada
         * Regras de Negócio Relevantes:
         *  - Quantidade deve ser positiva.
         * Observações:
         *  - Teste 100% unitário, usa mocks para Transactions, locks e policy.
         */
        $tx = Mockery::mock(Transactions::class);
        $lock = Mockery::mock(InventoryLockService::class);
        $policy = Mockery::mock(StockPolicy::class);

        $sut = new RegisterStockEntry($tx, $lock, $policy);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantidade deve ser positiva.');

        $sut->execute(1, 0, 10.0);
    }

    public static function providerForCreateCases(): array
    {
        return [
            'novo inventory com unitCost' => [
                42, // productId
                5,  // quantity
                3.0, // unitCost
            ],
            'inventory existente sem unitCost' => [
                7,
                5,
                null,
            ],
        ];
    }

    #[DataProvider('providerForCreateCases')]
    public function test_registra_entrada_conforme_casos(int $productId, int $quantity, ?float $unitCost): void
    {
        /**
         * Cenário
         * Dado: o caso de uso `RegisterStockEntry` que atualiza inventário e produtos
         * Quando: `execute(productId, quantity, unitCost)` é chamado em diferentes cenários (novo inventory, existente, com/sem unitCost)
         * Então: espera-se retorno contendo product_id, sku, name, quantity atualizado e preços como floats; last_updated definido
         * Regras de Negócio Relevantes:
         *  - Operação é executada dentro de Transactions::run e InventoryLockService::lock.
         * Observações:
         *  - Teste 100% unitário, usa resolvers que retornam query builders simulados.
         */
        $tx = Mockery::mock(Transactions::class);
        $tx->shouldReceive('run')->once()->andReturnUsing(fn ($cb) => $cb());

        $lock = Mockery::mock(InventoryLockService::class);
        $lock->shouldReceive('lock')->once()->andReturnUsing(fn ($id, $cb) => $cb());

        $policy = Mockery::mock(StockPolicy::class);
        $policy->shouldReceive('increase')->andReturnUsing(fn (int $cur, int $delta) => $cur + $delta);

        // Simula Product retornado pelo query
        $product = new class
        {
            public int $id = 0;

            public string $sku = 'SKU-0';

            public string $name = 'Produto';

            public float $cost_price = 1.0;

            public float $sale_price = 2.0;

            public function save(): bool
            {
                return true;
            }
        };

        $product->id = $productId;
        $product->sku = "SKU-{$productId}";
        $product->name = "Produto {$productId}";

        $productQuery = Mockery::mock();
        $productQuery->shouldReceive('lockForUpdate')->andReturnSelf();
        $productQuery->shouldReceive('findOrFail')->with($productId)->andReturn($product);

        // Simula Inventory query que retorna um inventário que pode ser null ou existente
        $invQuery = Mockery::mock();
        $invQuery->shouldReceive('where')->andReturnSelf();
        $invQuery->shouldReceive('lockForUpdate')->andReturnSelf();

        if ($unitCost === null) {
            $invObject = new class
            {
                public int $quantity = 10;

                public $last_updated = null;

                public function save(): bool
                {
                    return true;
                }
            };
        } else {
            $invObject = new class
            {
                public int $quantity = 0;

                public $last_updated = null;

                public function save(): bool
                {
                    return true;
                }
            };
        }

        $invQuery->shouldReceive('first')->andReturn($invObject);

        // Build SUT with resolvers that return our mocked query builders
        $sut = new RegisterStockEntry(
            $tx,
            $lock,
            $policy,
            fn () => $productQuery,
            fn () => $invQuery,
        );

        Carbon::setTestNow(Carbon::parse('2025-01-01T12:00:00Z'));

        // preserve initial quantity because SUT mutates the returned object in-place
        $initialInvQty = $invObject->quantity;

        // Act
        $res = $sut->execute($productId, $quantity, $unitCost);

        // Assert
        $this->assertSame($productId, $res['product_id']);
        $this->assertSame("SKU-{$productId}", $res['sku']);
        $this->assertSame("Produto {$productId}", $res['name']);
        $this->assertSame($initialInvQty + $quantity, $res['quantity']);
        $this->assertIsFloat($res['cost_price']);
        $this->assertIsFloat($res['sale_price']);
        $this->assertSame(Carbon::parse('2025-01-01T12:00:00Z')->toISOString(), $res['last_updated']);

        Carbon::setTestNow();
    }
}
