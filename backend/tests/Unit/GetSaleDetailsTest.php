<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Sales\UseCases\GetSaleDetails;
use App\Infrastructure\Persistence\Eloquent\SaleRepository;
use App\Models\Sale;
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
    private function makeSale(array $overrides = []): \App\Models\Sale
{
    $defaults = [
        'id'           => 1,
        'total_amount' => 0.0,
        'total_cost'   => 0.0,
        'total_profit' => 0.0,
        'status'       => 'pending',
        'created_at'   => null,
        'items'        => new \Illuminate\Support\Collection([]),
    ];

    $data = array_replace($defaults, $overrides);

    // Stub concreto que é um Sale "real", sem PHPUnit MockObject:
    $sale = new class extends \App\Models\Sale {
        // Tornamos os campos usados pelo caso de uso públicos e simples.
        public $id;
        public $total_amount;
        public $total_cost;
        public $total_profit;
        public $status;
        public $created_at;
        public $items;
    };

    $sale->id           = $data['id'];
    $sale->total_amount = $data['total_amount'];
    $sale->total_cost   = $data['total_cost'];
    $sale->total_profit = $data['total_profit'];
    $sale->status       = $data['status'];
    $sale->created_at   = $data['created_at'];
    $sale->items        = $data['items'];

    return $sale;
}

}
