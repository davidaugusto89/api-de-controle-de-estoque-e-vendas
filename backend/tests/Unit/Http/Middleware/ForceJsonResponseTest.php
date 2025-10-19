<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\TestCase;

final class ForceJsonResponseTest extends TestCase
{
    public function test_caminho_horizon_retorna_resposta_original_intacta(): void
    {
        /**
         * Cenário
         * Dado: rota /horizon
         * Quando: middleware ForceJsonResponse processa a requisição
         * Então: resposta original do tipo Symfony Response é preservada intacta
         */
        $request = Request::create('/horizon', 'GET');

        $next = function ($req) {
            return new SymfonyResponse('OK-H', 200, ['X-Orig' => '1']);
        };

        $mw = new ForceJsonResponse;

        $res = $mw->handle($request, $next);

        $this->assertInstanceOf(SymfonyResponse::class, $res);
        $this->assertSame('OK-H', $res->getContent());
        $this->assertSame('1', $res->headers->get('X-Orig'));
    }

    public function test_caminho_coringa_horizon_retorna_resposta_original_intacta(): void
    {
        /**
         * Cenário
         * Dado: rota com prefixo /horizon (coringa)
         * Quando: middleware executa
         * Então: resposta original e headers/status são preservados
         */
        $request = Request::create('/horizon/queues', 'GET');

        $next = function ($req) {
            return new SymfonyResponse('OK-H2', 201, ['X-Orig' => '2']);
        };

        $mw = new ForceJsonResponse;

        $res = $mw->handle($request, $next);

        $this->assertInstanceOf(SymfonyResponse::class, $res);
        $this->assertSame('OK-H2', $res->getContent());
        $this->assertSame(201, $res->getStatusCode());
        $this->assertSame('2', $res->headers->get('X-Orig'));
    }

    public function test_define_header_accept_quando_cliente_nao_espera_json_e_next_recebe(): void
    {
        /**
         * Cenário
         * Dado: cliente não espera JSON (expectsJson() == false)
         * Quando: middleware executa
         * Então: header Accept é definido para 'application/json' antes de chamar next
         */
        $request = Request::create('/api/test', 'GET');
        // ensure request does not expect json
        $this->assertFalse($request->expectsJson());

        $capturedAccept = null;
        $next = function ($req) use (&$capturedAccept) {
            $capturedAccept = $req->headers->get('Accept');

            return new SymfonyResponse('plain', 200);
        };

        $mw = new ForceJsonResponse;
        $mw->handle($request, $next);

        $this->assertSame('application/json', $capturedAccept);
    }

    public function test_preserva_accept_quando_cliente_espera_json(): void
    {
        /**
         * Cenário
         * Dado: cliente já define Accept: application/json
         * Quando: middleware executa
         * Então: header Accept é preservado
         */
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        $this->assertTrue($request->expectsJson());

        $capturedAccept = null;
        $next = function ($req) use (&$capturedAccept) {
            $capturedAccept = $req->headers->get('Accept');

            return new SymfonyResponse('plain', 200);
        };

        $mw = new ForceJsonResponse;
        $mw->handle($request, $next);

        $this->assertSame('application/json', $capturedAccept);
    }

    public function test_converte_string_json_para_json_response_preservando_headers_e_status(): void
    {
        /**
         * Cenário
         * Dado: next retorna string JSON
         * Quando: middleware handle converte
         * Então: resposta é JsonResponse com mesmo status e headers preserveados
         */
        $request = Request::create('/api/x', 'GET');

        $payload = ['a' => 1, 'b' => 'c'];
        $json = json_encode($payload);

        $next = function ($req) use ($json) {
            return new SymfonyResponse($json, 202, ['X-Custom' => 'v']);
        };

        $mw = new ForceJsonResponse;
        $res = $mw->handle($request, $next);

        $this->assertInstanceOf(JsonResponse::class, $res);
        $this->assertSame(202, $res->getStatusCode());
        $this->assertSame('v', $res->headers->get('X-Custom'));

        $this->assertSame($payload, json_decode($res->getContent(), true));
        $this->assertSame('application/json', $res->headers->get('Content-Type'));
    }

    public function test_converte_texto_para_mensagem_json_padrao(): void
    {
        /**
         * Cenário
         * Dado: next retorna texto simples
         * Quando: middleware processa
         * Então: string é embalada em objeto JSON com chave 'message'
         */
        $request = Request::create('/api/y', 'GET');

        $next = function ($req) {
            return new SymfonyResponse('just a text', 418, ['X-T' => 'ok']);
        };

        $mw = new ForceJsonResponse;
        $res = $mw->handle($request, $next);

        $this->assertInstanceOf(JsonResponse::class, $res);
        $this->assertSame(418, $res->getStatusCode());
        $this->assertSame(['message' => 'just a text'], json_decode($res->getContent(), true));
        $this->assertSame('ok', $res->headers->get('X-T'));
        $this->assertSame('application/json', $res->headers->get('Content-Type'));
    }

    public function test_nao_reembrulha_se_ja_json_mas_define_content_type(): void
    {
        /**
         * Cenário
         * Dado: next já retorna JsonResponse
         * Quando: middleware executa
         * Então: não reembrulha, apenas garante Content-Type e preserva headers/status
         */
        $request = Request::create('/api/z', 'GET');

        $next = function ($req) {
            return response()->json(['ok' => true], 201, ['X-J' => '1']);
        };

        $mw = new ForceJsonResponse;
        $res = $mw->handle($request, $next);

        $this->assertInstanceOf(JsonResponse::class, $res);
        $this->assertSame(201, $res->getStatusCode());
        $this->assertSame(['ok' => true], json_decode($res->getContent(), true));
        $this->assertSame('1', $res->headers->get('X-J'));
        $this->assertSame('application/json', $res->headers->get('Content-Type'));
    }
}
