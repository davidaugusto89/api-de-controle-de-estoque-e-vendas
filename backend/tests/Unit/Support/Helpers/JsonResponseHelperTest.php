<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Helpers;

use App\Support\Helpers\JsonResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Tests\TestCase;

final class JsonResponseHelperTest extends TestCase
{
    public function test_ok_retorna_response_json_estruturado(): void
    {
        /**
         * Cenário
         * Dado: payload válido
         * Quando: JsonResponseHelper::ok é invocado
         * Então: retorna JsonResponse com estrutura esperada (status, data, meta)
         */
        $data = ['id' => 1, 'name' => 'Produto'];
        $res = JsonResponseHelper::ok($data, 201, ['page' => 1]);

        $this->assertInstanceOf(JsonResponse::class, $res);
        $this->assertSame(201, $res->getStatusCode());

        $payload = $res->getData(true);
        $this->assertSame('success', Arr::get($payload, 'status'));
        $this->assertSame($data, Arr::get($payload, 'data'));
        $this->assertSame(['page' => 1], Arr::get($payload, 'meta'));
    }

    public function test_error_retorna_erro_estruturado_e_request_id(): void
    {
        /**
         * Cenário
         * Dado: chamada a JsonResponseHelper::error com detalhes
         * Quando: error é invocado
         * Então: retorna JsonResponse com status 422 e payload de erro estruturado
         */
        $res = JsonResponseHelper::error('ERR_01', 'Bad input', 422, 'req-1', ['field' => 'qty']);

        $this->assertInstanceOf(JsonResponse::class, $res);
        $this->assertSame(422, $res->getStatusCode());

        $payload = $res->getData(true);
        $this->assertSame('error', $payload['status']);
        $this->assertArrayHasKey('error', $payload);
        $this->assertSame('ERR_01', $payload['error']['code']);
        $this->assertSame('Bad input', $payload['error']['message']);
        $this->assertSame(['field' => 'qty'], $payload['error']['details']);
        $this->assertSame('req-1', $payload['request_id']);
    }
}
