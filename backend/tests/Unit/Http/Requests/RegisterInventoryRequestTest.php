<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\RegisterInventoryRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RegisterInventoryRequest::class)]
final class RegisterInventoryRequestTest extends TestCase
{
    public function test_authorize_allows_by_default(): void
    {
        $req = new RegisterInventoryRequest;

        $this->assertTrue($req->authorize());
    }

    public function test_rules_define_product_quantity_and_unit_cost(): void
    {
        $req = new RegisterInventoryRequest;
        $rules = $req->rules();

        $this->assertArrayHasKey('product_id', $rules);
        $this->assertArrayHasKey('quantity', $rules);
        $this->assertArrayHasKey('unit_cost', $rules);
    }

    public function test_messages_contains_custom_messages(): void
    {
        $req = new RegisterInventoryRequest;
        $messages = $req->messages();

        $this->assertArrayHasKey('product_id.required', $messages);
        $this->assertArrayHasKey('product_id.exists', $messages);
        $this->assertArrayHasKey('quantity.required', $messages);
        $this->assertArrayHasKey('unit_cost.min', $messages);
    }
}
