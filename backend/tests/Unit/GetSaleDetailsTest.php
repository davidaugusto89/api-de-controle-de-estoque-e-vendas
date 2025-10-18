<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Sales\UseCases\GetSaleDetails;
use App\Infrastructure\Persistence\Eloquent\SaleRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Cenário
 * Dado: um repositório de vendas (`SaleRepository`) que expõe `findWithItems($id)`
 * Quando: o caso de uso `GetSaleDetails::execute($id)` é executado com diferentes estados
 * Então: os dados da venda são mapeados para um array com fields (id, total_amount, total_cost, total_profit, status, created_at, items) ou uma exceção é lançada
 * Regras de Negócio Relevantes:
 *  - Se o repositório retornar `null`, deve ser lançado `ModelNotFoundException`.
 *  - Campos numéricos podem vir como strings e devem ser castados corretamente.
 *  - `created_at` quando presente deve expor `toISOString()`; quando ausente, resultado deve ser string vazia.
 * Observações:
 *  - Usa `Illuminate\Support\Collection` para itens.
 *  - O repositório usado nos testes é um mock (PHPUnit `createMock`).
 *  - Método do repositório: `findWithItems(int $id)`.
 *
 * @covers \App\Application\Sales\UseCases\GetSaleDetails
 */
final class GetSaleDetailsTest extends TestCase
{
    // Não há necessidade de fechar Mockery: usamos mocks do PHPUnit.

    /**
     * Testa fluxo de sucesso: repositório retorna venda com itens e os dados são mapeados corretamente.
     */
    public function test_retornar_detalhes_da_venda_quando_id_existir(): void
    {
        // Arrange
        $repo = $this->createMock(SaleRepository::class);

        $items = new Collection([
            (object) [
                'product_id' => 10,
                'quantity' => 2,
                'unit_price' => 25.5,
                'unit_cost' => 15.0,
            ],
        ]);

        // created_at precisa expor método toISOString() como no Eloquent/Carbon usado na aplicação.
        $createdAt = new class
        {
            public function toISOString(): string
            {
                return '2025-10-18T12:00:00+00:00';
            }
        };

        $sale = $this->makeSale([
            'id' => 123,
            'total_amount' => 51.0,
            'total_cost' => 30.0,
            'total_profit' => 21.0,
            'status' => 'paid',
            'created_at' => $createdAt,
            'items' => $items,
        ]);

        $repo->expects($this->once())
            ->method('findWithItems')
            ->with(123)
            ->willReturn($sale);

        $sut = new GetSaleDetails($repo);

        // Act
        $result = $sut->execute(123);

        // Assert
        $this->assertIsArray($result);
        $this->assertSame(123, $result['id']);
        $this->assertSame(51.0, $result['total_amount']);
        $this->assertSame(30.0, $result['total_cost']);
        $this->assertSame(21.0, $result['total_profit']);
        $this->assertSame('paid', $result['status']);
        $this->assertSame('2025-10-18T12:00:00+00:00', $result['created_at']);
        $this->assertIsArray($result['items']);
        $this->assertCount(1, $result['items']);
        $this->assertSame(10, $result['items'][0]['product_id']);
        $this->assertSame(2, $result['items'][0]['quantity']);
        $this->assertSame(25.5, $result['items'][0]['unit_price']);
        $this->assertSame(15.0, $result['items'][0]['unit_cost']);
    }

    /**
     * Testa que uma exceção ModelNotFoundException é lançada quando a venda não existe.
     */
    public function test_lancar_model_not_found_quando_venda_nao_existir(): void
    {
        // Arrange
        $repo = $this->createMock(SaleRepository::class);
        $repo->expects($this->once())
            ->method('findWithItems')
            ->with(999)
            ->willReturn(null);

        $sut = new GetSaleDetails($repo);

        $this->expectException(ModelNotFoundException::class);

        // Act
        $sut->execute(999);
    }

    /**
     * Testa comportamento quando itens possuem tipos inesperados (strings) — valida casts.
     */
    public function test_converter_campos_de_item_quando_valores_sao_strings(): void
    {
        // Arrange
        $repo = $this->createMock(SaleRepository::class);

        $items = new Collection([
            (object) [
                'product_id' => '7',
                'quantity' => '3',
                'unit_price' => '9.99',
                'unit_cost' => '5.00',
            ],
        ]);

        $sale = $this->makeSale([
            'id' => 321,
            'total_amount' => '29.97',
            'total_cost' => '15.00',
            'total_profit' => '14.97',
            'status' => 'pending',
            'created_at' => null,
            'items' => $items,
        ]);

        $repo->expects($this->once())
            ->method('findWithItems')
            ->with(321)
            ->willReturn($sale);

        $sut = new GetSaleDetails($repo);

        // Act
        $result = $sut->execute(321);

        // Assert
        $this->assertSame(321, $result['id']);
        $this->assertSame(29.97, $result['total_amount']);
        $this->assertSame(15.0, $result['total_cost']);
        $this->assertSame(14.97, $result['total_profit']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame('', $result['created_at']);
        $this->assertIsArray($result['items']);
        $this->assertSame(7, $result['items'][0]['product_id']);
        $this->assertSame(3, $result['items'][0]['quantity']);
        $this->assertSame(9.99, $result['items'][0]['unit_price']);
        $this->assertSame(5.0, $result['items'][0]['unit_cost']);
    }

    /**
     * Testa que o repositório pode lançar uma exceção genérica e que ela propaga.
     */
    public function test_propagar_excecao_do_repositorio(): void
    {
        // Arrange
        $repo = $this->createMock(SaleRepository::class);
        $repo->expects($this->once())
            ->method('findWithItems')
            ->with(555)
            ->will($this->throwException(new \RuntimeException('DB fail')));

        $sut = new GetSaleDetails($repo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB fail');

        // Act
        $sut->execute(555);
    }

    /**
     * Testa entrada inválida: ID negativo — comportamento esperado (repositório ainda chamado, mas com valor fornecido).
     */
    public function test_chamar_repositorio_com_id_negativo_quando_entrada_invalida(): void
    {
        // Arrange
        $repo = $this->createMock(SaleRepository::class);
        $repo->expects($this->once())
            ->method('findWithItems')
            ->with(-1)
            ->willReturn(null);

        $sut = new GetSaleDetails($repo);

        $this->expectException(ModelNotFoundException::class);

        // Act
        $sut->execute(-1);
    }

    /**
     * Helpers privados para construir objetos de domínio usados nos testes.
     */
    private function makeSale(array $overrides = []): object
    {
        $defaults = [
            'id' => 1,
            'total_amount' => 0.0,
            'total_cost' => 0.0,
            'total_profit' => 0.0,
            'status' => 'pending',
            'created_at' => null,
            'items' => new Collection([]),
        ];

        $data = array_merge($defaults, $overrides);

        return (object) [
            'id' => $data['id'],
            'total_amount' => $data['total_amount'],
            'total_cost' => $data['total_cost'],
            'total_profit' => $data['total_profit'],
            'status' => $data['status'],
            'created_at' => $data['created_at'],
            'items' => $data['items'],
        ];
    }
}
