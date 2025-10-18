<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Helpers;

use App\Support\Helpers\Idempotency;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class IdempotencyTest extends TestCase
{
    public function test_chave_da_requisicao_usao_header_quando_presente(): void
    {
        $req = Request::create('/api/v1/sales', 'POST', [], [], [], [
            'HTTP_Idempotency-Key' => 'abc-123',
        ]);

        $key = Idempotency::keyFromRequest($req);

        $this->assertStringStartsWith('idem:', $key);
        $this->assertSame('idem:'.hash('sha256', 'abc-123'), $key);
    }

    public function test_chave_da_requisicao_sem_header_hash_method_path_query_e_body(): void
    {
        $req = Request::create('/api/v1/products/5?verbose=1', 'PUT', ['name' => 'Product A', 'qty' => 10]);

        $key = Idempotency::keyFromRequest($req);

        $this->assertStringStartsWith('idem:', $key);

        $payload = [
            'm' => $req->getMethod(),
            'u' => $req->getPathInfo(),
            'q' => $req->query(),
            'b' => $req->all(),
        ];

        $expected = 'idem:'.hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        $this->assertSame($expected, $key);
    }
}
