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
        $req = new RegisterInventoryRequest;

        $this->assertTrue($req->authorize());
    }

    public function test_regras_definem_produto_quantidade_e_custo_unitario(): void
    {
        $req = new RegisterInventoryRequest;
        $rules = $req->rules();

        $this->assertArrayHasKey('product_id', $rules);
        $this->assertArrayHasKey('quantity', $rules);
        $this->assertArrayHasKey('unit_cost', $rules);
    }

    public function test_messages_contem_mensagens_customizadas(): void
    {
        $req = new RegisterInventoryRequest;
        $messages = $req->messages();

        $this->assertArrayHasKey('product_id.required', $messages);
        $this->assertArrayHasKey('product_id.exists', $messages);
        $this->assertArrayHasKey('quantity.required', $messages);
        $this->assertArrayHasKey('unit_cost.min', $messages);
    }
}
