<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Events;

use App\Infrastructure\Events\SaleFinalized;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * SaleFinalizedTest
 *
 * Cenário:
 * - Evento de domínio que representa a finalização de uma venda.
 *
 * Quando:
 * - A instância é criada com id de venda e lista de itens.
 * - Valores inválidos (tipos incorretos) são fornecidos.
 *
 * Então:
 * - As propriedades públicas readonly são preenchidas corretamente.
 * - Um TypeError é lançado quando tipos inválidos são passados (strict_types=1).
 *
 * Observações:
 * - Testes unitários puros; nenhuma dependência externa é necessária.
 */
#[CoversClass(SaleFinalized::class)]
final class SaleFinalizedTest extends TestCase
{
    public function test_criar_evento_com_valores_validos_popula_propriedades(): void
    {
        /**
         * Cenário
         * Dado: valores válidos para evento
         * Quando: instância é criada
         * Então: propriedades readonly são populadas corretamente
         */
        $saleId = 123;
        $items = [
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 5],
        ];

        $event = new SaleFinalized($saleId, $items);

        $this->assertSame($saleId, $event->saleId);
        $this->assertSame($items, $event->items);
    }

    public function test_lancar_type_error_quando_saleid_nao_inteiro(): void
    {
        /**
         * Cenário
         * Dado: saleId com tipo incorreto
         * Quando: construtor é invocado
         * Então: TypeError é lançado
         */
        $this->expectException(\TypeError::class);

        // Esconde o valor do analisador estático para que o TypeError ocorra apenas em runtime
        $badSaleId = (static function () {
            return 'nao-inteiro';
        })();

        /** @var mixed $badSaleId */
        call_user_func(function () use ($badSaleId) {
            new SaleFinalized($badSaleId, []);
        });
    }

    public function test_lancar_type_error_quando_items_nao_array(): void
    {
        /**
         * Cenário
         * Dado: items com tipo incorreto
         * Quando: construtor é invocado
         * Então: TypeError é lançado
         */
        $this->expectException(\TypeError::class);

        // Evita análise estática do literal de tipo incorreto
        $badItems = (static function () {
            return 'nao-array';
        })();

        /** @var mixed $badItems */
        call_user_func(function () use ($badItems) {
            new SaleFinalized(1, $badItems);
        });
    }
}
