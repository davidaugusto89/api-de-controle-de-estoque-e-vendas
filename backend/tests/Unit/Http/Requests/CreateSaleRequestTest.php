<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\CreateSaleRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CreateSaleRequest::class)]
final class CreateSaleRequestTest extends TestCase
{
    public function test_autorizar_por_padrao(): void
    {
        /**
         * Cenário
         * Dado: CreateSaleRequest
         * Quando: authorize() é chamado
         * Então: retorna true por padrão
         */
        // Arrange
        $sut = new CreateSaleRequest;

        // Act & Assert
        $this->assertTrue($sut->authorize());
    }

    public function test_rules_definem_items_e_campos_do_item(): void
    {
        /**
         * Cenário
         * Dado: CreateSaleRequest
         * Quando: rules() é consultado
         * Então: define regras para items e campos do item
         */
        // Arrange
        $sut = new CreateSaleRequest;

        // Act
        $rules = $sut->rules();

        // Assert
        $this->assertArrayHasKey('items', $rules);
        $this->assertArrayHasKey('items.*.product_id', $rules);
        $this->assertArrayHasKey('items.*.quantity', $rules);
        $this->assertArrayHasKey('items.*.unit_price', $rules);
    }

    public function test_messages_contem_mensagem_customizada_para_items_required(): void
    {
        /**
         * Cenário
         * Dado: CreateSaleRequest
         * Quando: messages() é consultado
         * Então: inclui mensagem customizada para items.required
         */
        // Arrange
        $sut = new CreateSaleRequest;

        // Act
        $messages = $sut->messages();

        // Assert
        $this->assertArrayHasKey('items.required', $messages);
        $this->assertSame('Informe ao menos um item.', $messages['items.required']);
    }
}
