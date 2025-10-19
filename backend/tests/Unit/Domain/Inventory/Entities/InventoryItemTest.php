<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Inventory\Entities;

use App\Domain\Inventory\Entities\InventoryItem;
use App\Domain\Inventory\Services\StockPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Testes unitários para App\Domain\Inventory\Entities\InventoryItem
 *
 * Os testes estão em português e cobrem todos os cenários relevantes:
 * - Normalização inicial via StockPolicy
 * - Incremento e decremento válidos
 * - Decremento maior que o disponível (exceção)
 * - Propagação de validações da StockPolicy (delta inválido, current negativo)
 * - Respeito ao limite máximo por produto quando uma StockPolicy customizada é usada
 */
final class InventoryItemTest extends TestCase
{
    public function test_construtor_normaliza_quantidade_inicial(): void
    {
        /**
         * Cenário
         * Dado: construtor de InventoryItem com quantidade inicial
         * Quando: instanciado com quantity=10
         * Então: quantity() retorna valor normalizado (10)
         */
        $policy = new StockPolicy(1_000_000);

        $item = new InventoryItem(productId: 123, quantity: 10, policy: $policy);

        $this->assertSame(10, $item->quantity());
    }

    public function test_incrementa_com_sucesso(): void
    {
        /**
         * Cenário
         * Dado: InventoryItem com quantity 5
         * Quando: increment(3) é chamado
         * Então: quantity() == 8
         */
        $item = new InventoryItem(productId: 1, quantity: 5);

        $item->increment(3);

        $this->assertSame(8, $item->quantity());
    }

    public function test_decrementa_com_sucesso(): void
    {
        /**
         * Cenário
         * Dado: InventoryItem com quantity 5
         * Quando: decrement(2) é chamado
         * Então: quantity() == 3
         */
        $item = new InventoryItem(productId: 2, quantity: 5);

        $item->decrement(2);

        $this->assertSame(3, $item->quantity());
    }

    public function test_decremento_maior_que_disponivel_lanca_runtime_exception(): void
    {
        /**
         * Cenário
         * Dado: InventoryItem com quantity 2
         * Quando: decrement(3) é chamado
         * Então: RuntimeException 'Estoque insuficiente para a operação.' é lançada
         */
        $item = new InventoryItem(productId: 3, quantity: 2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Estoque insuficiente para a operação.');

        $item->decrement(3);
    }

    public function test_incremento_com_delta_invalido_propagacao_invalid_argument(): void
    {
        /**
         * Cenário
         * Dado: chamada increment com delta inválido (0)
         * Quando: increment(0) é invocado
         * Então: InvalidArgumentException 'Delta deve ser positivo.' é propagada
         */
        $item = new InventoryItem(productId: 4, quantity: 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Delta deve ser positivo.');

        // delta <= 0 deve propagar a exceção da StockPolicy
        $item->increment(0);
    }

    public function test_construtor_com_current_negativo_lanca_invalid_argument(): void
    {
        /**
         * Cenário
         * Dado: quantity inicial negativo
         * Quando: instanciar InventoryItem com quantity -1
         * Então: InvalidArgumentException 'Quantidade atual não pode ser negativa.' é lançada
         */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantidade atual não pode ser negativa.');

        // Ao construir, o ajuste inicial chama StockPolicy::adjust -> normalize (normalização aplicável)
        new InventoryItem(productId: 5, quantity: -1);
    }

    public function test_respeita_maximo_por_produto_via_policy_customizada(): void
    {
        /**
         * Cenário
         * Dado: policy com maxPerProduct = 5 e item com quantity 5
         * Quando: increment(1) é chamado
         * Então: RuntimeException 'Quantidade máxima por produto excedida.' é lançada
         */
        // Definimos maxPerProduct baixo para forçar a exceção no incremento
        $policy = new StockPolicy(5);
        $item = new InventoryItem(productId: 6, quantity: 5, policy: $policy);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Quantidade máxima por produto excedida.');

        $item->increment(1);
    }
}
