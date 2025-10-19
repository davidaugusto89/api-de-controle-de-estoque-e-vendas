<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\RegisterInventoryRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RegisterInventoryRequest::class)]
final class RegisterInventoryRequestTest extends TestCase
{
    public function test_autorizar_permite_por_padrao(): void
    {
        /**
         * Cenário
         * Dado: instância de RegisterInventoryRequest
         * Quando: authorize() é chamado
         * Então: deve retornar true por padrão
         */
        $req = new RegisterInventoryRequest;

        $this->assertTrue($req->authorize());
    }

    public function test_regras_definem_produto_quantidade_e_custo_unitario(): void
    {
        /**
         * Cenário
         * Dado: RegisterInventoryRequest
         * Quando: rules() é consultado
         * Então: contém chaves product_id, quantity e unit_cost
         */
        $req   = new RegisterInventoryRequest;
        $rules = $req->rules();

        $this->assertArrayHasKey('product_id', $rules);
        $this->assertArrayHasKey('quantity', $rules);
        $this->assertArrayHasKey('unit_cost', $rules);
    }

    public function test_messages_contem_mensagens_customizadas(): void
    {
        /**
         * Cenário
         * Dado: RegisterInventoryRequest
         * Quando: messages() é consultado
         * Então: contém mensagens customizadas para product_id e quantity/unit_cost
         */
        $req      = new RegisterInventoryRequest;
        $messages = $req->messages();

        $this->assertArrayHasKey('product_id.required', $messages);
        $this->assertArrayHasKey('product_id.exists', $messages);
        $this->assertArrayHasKey('quantity.required', $messages);
        $this->assertArrayHasKey('unit_cost.min', $messages);
    }
}
