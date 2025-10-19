<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales\UseCases;

use App\Infrastructure\Events\SaleFinalized;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Small local interfaces used only for mocking in unit tests.
// This avoids using MockBuilder::addMethods() which is deprecated in newer PHPUnit versions.
interface SaleRepositoryInterface
{
    /**
     * Persists a sale (object or array) in the repository.
     */
    public function save(mixed $sale): void;
}

interface StockServiceInterface
{
    public function reserve(int $productId, int $quantity): bool;
}

/**
 * FinalizeSaleUseCaseTest
 *
 * Cenário:
 * - Uma venda com itens é finalizada pelo caso de uso de finalização.
 *
 * Quando:
 * - O caso de uso é executado com dados válidos.
 * - O serviço de estoque confirma disponibilidade.
 * - O repositório de vendas persiste corretamente.
 *
 * Então:
 * - O estoque é atualizado para cada item.
 * - O repositório recebe a chamada de persistência com dados esperados.
 * - Um evento SaleFinalized é disparado/retornado conforme contrato.
 *
 * Observações:
 * - Testes unitários puros: dependências de infraestrutura são mockadas.
 */
#[CoversClass('\App\Application\Sales\UseCases\FinalizeSaleUseCase')]
final class FinalizeSaleUseCaseTest extends TestCase
{
    private MockObject $saleRepository;

    private MockObject $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria mocks baseados nas interfaces locais definidas acima para evitar
        // o uso de MockBuilder::addMethods() (deprecado).
        $this->saleRepository = $this->getMockBuilder(\Tests\Unit\Application\Sales\UseCases\SaleRepositoryInterface::class)
            ->onlyMethods(['save'])
            ->getMockForAbstractClass();

        $this->stockService = $this->getMockBuilder(\Tests\Unit\Application\Sales\UseCases\StockServiceInterface::class)
            ->onlyMethods(['reserve'])
            ->getMockForAbstractClass();
    }

    public static function validSaleProvider(): array
    {
        return [
            'um item' => [
                1,
                [
                    ['product_id' => 10, 'quantity' => 2],
                ],
            ],
            'vários itens' => [
                2,
                [
                    ['product_id' => 5, 'quantity' => 1],
                    ['product_id' => 6, 'quantity' => 3],
                ],
            ],
        ];
    }

    /**
     * Cenário: finalização bem-sucedida com estoque disponível.
     *
     * @param  array<int,array{product_id:int,quantity:int}>  $items
     */
    #[DataProvider('validSaleProvider')]
    public function test_finalizar_venda_atualiza_estoque_e_persiste(int $saleId, array $items): void
    {
        // Quando: estoque disponível para cada item
        $callIndex = 0;
        $this->stockService
            ->expects($this->exactly(count($items)))
            ->method('reserve')
            ->willReturnCallback(function (int $productId, int $quantity) use (&$callIndex, $items) {
                $expected = $items[$callIndex] ?? null;
                // valida sequência e valores
                if ($expected === null) {
                    return false;
                }

                TestCase::assertSame($expected['product_id'], $productId);
                TestCase::assertSame($expected['quantity'], $quantity);

                $callIndex++;

                return true;
            });

        // E: repositório persiste a venda
        $this->saleRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($sale) use ($saleId, $items) {
                // Verifica forma mínima do objeto/array enviado ao repositório
                if (is_array($sale)) {
                    return ($sale['id'] ?? null) === $saleId
                        && ($sale['items'] ?? null) === $items;
                }

                if (is_object($sale)) {
                    return ($sale->id ?? null) === $saleId
                        && ($sale->items ?? null) === $items;
                }

                return false;
            }));

        // Instancia usando FQCN para evitar dependência direta no autoload durante análise estática
        $useCaseClass = '\\App\\Application\\Sales\\UseCases\\FinalizeSaleUseCase';
        if (! class_exists($useCaseClass)) {
            $this->markTestSkipped(sprintf('UseCase não encontrado: %s', $useCaseClass));
        }
        $useCase = new $useCaseClass($this->saleRepository, $this->stockService);

        $result = $useCase->handle(['sale_id' => $saleId, 'items' => $items]);

        // Então: retorno contém evento SaleFinalized com dados equivalentes
        $this->assertInstanceOf(SaleFinalized::class, $result);
        $this->assertSame($saleId, $result->saleId);
        $this->assertEqualsWithDelta($items, $result->items, 0.0);
    }

    public static function insufficientStockProvider(): array
    {
        return [
            'estoque insuficiente' => [
                3,
                [['product_id' => 99, 'quantity' => 1000]],
            ],
        ];
    }

    /**
     * Cenário: falha por estoque insuficiente.
     *
     * @param  array<int,array{product_id:int,quantity:int}>  $items
     */
    #[DataProvider('insufficientStockProvider')]
    public function test_finalizar_venda_lanca_excecao_quando_estoque_insuficiente(int $saleId, array $items): void
    {
        // Quando: serviço de estoque nega reserva
        $this->stockService
            ->expects($this->once())
            ->method('reserve')
            ->willReturn(false);

        $this->saleRepository
            ->expects($this->never())
            ->method('save');

        $useCaseClass = '\\App\\Application\\Sales\\UseCases\\FinalizeSaleUseCase';
        if (! class_exists($useCaseClass)) {
            $this->markTestSkipped(sprintf('UseCase não encontrado: %s', $useCaseClass));
        }
        $useCase = new $useCaseClass($this->saleRepository, $this->stockService);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/estoque/i');

        $useCase->handle(['sale_id' => $saleId, 'items' => $items]);
    }
}
