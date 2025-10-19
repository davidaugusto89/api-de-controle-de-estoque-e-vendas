<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiExceptionFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ApiExceptionFormatterTest extends TestCase
{
    public function test_excecao_de_validacao_formata_resposta()
    {
        /**
         * Cenário
         * Dado: uma ValidationException construída a partir de um validator real
         * Quando: ApiExceptionFormatter::from(ex, request) é chamado
         * Então: responde com status 422 e payload contendo código 'ValidationError' e detalhes de errors
         * Observações:
         *  - usamos Validator facade para garantir compatibilidade com a ValidationException real.
         */
        $request = Request::create('/test');

        // Criar um validator real via Validator facade para garantir compatibilidade
        $validator = \Illuminate\Support\Facades\Validator::make(['field' => ''], ['field' => 'required']);
        $e = new ValidationException($validator);

        $res = ApiExceptionFormatter::from($e, $request);

        $this->assertSame(422, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('ValidationError', $payload['error']['code']);
        $this->assertArrayHasKey('errors', $payload['error']['details']);
    }

    public function test_autenticacao_autorizacao_e_nao_encontrado_sao_tratados()
    {
        /**
         * Cenário
         * Dado: exceções de autenticação, autorização e model not found
         * Quando: ApiExceptionFormatter::from for chamado para cada exceção
         * Então: responde com os status HTTP apropriados (401, 403, 404)
         */
        $request = Request::create('/');

        $e1 = new AuthenticationException;
        $r1 = ApiExceptionFormatter::from($e1, $request);
        $this->assertSame(401, $r1->getStatusCode());

        $e2 = new AuthorizationException;
        $r2 = ApiExceptionFormatter::from($e2, $request);
        $this->assertSame(403, $r2->getStatusCode());

        $e3 = new ModelNotFoundException;
        $r3 = ApiExceptionFormatter::from($e3, $request);
        $this->assertSame(404, $r3->getStatusCode());

        $e4 = new NotFoundHttpException;
        $r4 = ApiExceptionFormatter::from($e4, $request);
        $this->assertSame(404, $r4->getStatusCode());
    }

    public function test_metodo_nao_permitido_e_limite_de_requisicoes_incluem_headers()
    {
        /**
         * Cenário
         * Dado: MethodNotAllowedHttpException e ThrottleRequestsException
         * Quando: ApiExceptionFormatter::from é invocado
         * Então: resposta contém status corretos (405, 429) e headers relevantes (Allow, Retry-After)
         */
        $request = Request::create('/');

        $e = new MethodNotAllowedHttpException(['GET', 'POST']);
        $res = ApiExceptionFormatter::from($e, $request);
        $this->assertSame(405, $res->getStatusCode());
        // header Allow deve existir e conter GET, POST
        $this->assertTrue($res->headers->has('Allow'));
        $this->assertStringContainsString('GET', $res->headers->get('Allow'));
        $this->assertStringContainsString('POST', $res->headers->get('Allow'));

        $throttle = new ThrottleRequestsException('Too many', null, ['Retry-After' => 60]);
        $r2 = ApiExceptionFormatter::from($throttle, $request);
        $this->assertSame(429, $r2->getStatusCode());
        $this->assertTrue($r2->headers->has('Retry-After'));
        $this->assertEquals(60, (int) $r2->headers->get('Retry-After'));
    }

    public function test_metodo_nao_permitido_quando_allow_eh_array()
    {
        /**
         * Cenário
         * Dado: MethodNotAllowedHttpException com headers Allow como array
         * Quando: ApiExceptionFormatter::from é chamado
         * Então: header Allow na resposta deve ser "GET, POST" (string)
         */
        $request = Request::create('/');

        $e = new MethodNotAllowedHttpException(['GET']);
        // força o header Allow como array para simular formatos diferentes
        $e->setHeaders(['Allow' => ['GET', 'POST']]);

        $res = ApiExceptionFormatter::from($e, $request);
        $this->assertSame(405, $res->getStatusCode());
        $this->assertTrue($res->headers->has('Allow'));
        $this->assertSame('GET, POST', $res->headers->get('Allow'));
    }

    public function test_metodo_nao_permitido_quando_allow_ausente()
    {
        /**
         * Cenário
         * Dado: MethodNotAllowedHttpException sem header Allow
         * Quando: ApiExceptionFormatter::from é chamado
         * Então: resposta contém header Allow com string vazia
         */
        $request = Request::create('/');

        $e = new MethodNotAllowedHttpException(['GET']);
        // remove o header Allow
        $e->setHeaders([]);

        $res = ApiExceptionFormatter::from($e, $request);
        $this->assertSame(405, $res->getStatusCode());
        // quando ausente, ApiExceptionFormatter usa implode com array vazio -> string vazia
        $this->assertTrue($res->headers->has('Allow'));
        $this->assertSame('', $res->headers->get('Allow'));
    }

    public function test_http_exception_preserva_status_e_debug_quando_habilitado()
    {
        /**
         * Cenário
         * Dado: HttpException e app.debug = true
         * Quando: ApiExceptionFormatter::from é chamado
         * Então: payload inclui campo 'debug' com informações da exceção e headers são preservados
         */
        $request = Request::create('/');

        // Force debug on
        config(['app.debug' => true]);

        $e = new HttpException(418, 'I am a teapot', null, ['X-Test' => '1']);
        $res = ApiExceptionFormatter::from($e, $request);
        $this->assertSame(418, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('HttpException', $payload['error']['code']);
        $this->assertArrayHasKey('debug', $payload['error']);
        $this->assertSame('I am a teapot', $payload['error']['message']);
        // headers do exception devem ser aplicados na resposta
        $this->assertTrue($res->headers->has('X-Test'));
        // debug payload deve conter chaves esperadas
        $debug = $payload['error']['debug'];
        $this->assertArrayHasKey('exception', $debug);
        $this->assertArrayHasKey('file', $debug);
        $this->assertArrayHasKey('line', $debug);
        $this->assertArrayHasKey('trace', $debug);
        $this->assertIsArray($debug['trace']);

        // restore debug off
        config(['app.debug' => false]);
    }

    public function test_query_exception_e_fallback_tem_500_e_debug_desligado_por_padrao()
    {
        $request = Request::create('/');

        // QueryException constructor: (connectionName, sql, bindings, previous)
        $qe = new QueryException('testing', 'select 1', [], new \PDOException('pdo error'));
        $r1 = ApiExceptionFormatter::from($qe, $request);
        $this->assertSame(500, $r1->getStatusCode());
        $payload = $r1->getData(true);
        $this->assertSame('DatabaseError', $payload['error']['code']);

        $ex = new \Exception('boom');
        $r2 = ApiExceptionFormatter::from($ex, $request);
        $this->assertSame(500, $r2->getStatusCode());
        $payload2 = $r2->getData(true);
        $this->assertSame('InternalServerError', $payload2['error']['code']);
    }

    public function test_query_e_excecao_generica_incluem_debug_quando_habilitado()
    {
        $request = Request::create('/');
        config(['app.debug' => true]);

        $qe = new QueryException('testing', 'select 1', [], new \PDOException('pdo error'));
        $r1 = ApiExceptionFormatter::from($qe, $request);
        $this->assertSame(500, $r1->getStatusCode());
        $p1 = $r1->getData(true);
        $this->assertSame('DatabaseError', $p1['error']['code']);
        $this->assertArrayHasKey('debug', $p1['error']);

        // generic exception
        $ex = new \Exception('boom2');
        $r2 = ApiExceptionFormatter::from($ex, $request);
        $p2 = $r2->getData(true);
        $this->assertSame('InternalServerError', $p2['error']['code']);
        $this->assertArrayHasKey('debug', $p2['error']);

        // debug.trace deve ser array
        $this->assertIsArray($p2['error']['debug']['trace']);

        config(['app.debug' => false]);
    }

    public function test_throttle_preserva_headers_multiplos_e_valores_array()
    {
        $request = Request::create('/');

        // headers como strings
        $headers = [
            'Retry-After' => '60',
            'X-RateLimit-Limit' => '100',
            'X-RateLimit-Remaining' => '0',
        ];

        $throttle = new ThrottleRequestsException('Too many', null, $headers);
        $res = ApiExceptionFormatter::from($throttle, $request);

        $this->assertSame(429, $res->getStatusCode());
        $this->assertEquals('60', $res->headers->get('Retry-After'));
        $this->assertEquals('100', $res->headers->get('X-RateLimit-Limit'));
        $this->assertEquals('0', $res->headers->get('X-RateLimit-Remaining'));

        // headers como arrays
        $headers2 = [
            'Retry-After' => ['60'],
            'X-RateLimit-Limit' => ['100'],
        ];

        $throttle2 = new ThrottleRequestsException('Too many', null, $headers2);
        $r2 = ApiExceptionFormatter::from($throttle2, $request);

        $this->assertSame(429, $r2->getStatusCode());
        // quando headers fornecidos como arrays, o Laravel normaliza para strings ao obter o header
        $this->assertEquals('60', $r2->headers->get('Retry-After'));
        $this->assertEquals('100', $r2->headers->get('X-RateLimit-Limit'));
    }

    public function test_throttle_inclui_debug_quando_habilitado()
    {
        $request = Request::create('/');
        config(['app.debug' => true]);

        $throttle = new ThrottleRequestsException('Too many', new \Exception('prev'), ['Retry-After' => 10]);
        $res = ApiExceptionFormatter::from($throttle, $request);
        $this->assertSame(429, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertArrayHasKey('debug', $payload['error']);
        $this->assertArrayHasKey('exception', $payload['error']['debug']);
        $this->assertIsArray($payload['error']['debug']['trace']);

        config(['app.debug' => false]);
    }

    public function test_request_id_e_usado_quando_header_presente()
    {
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_REQUEST_ID' => 'myid']);
        $ex = new \Exception('x');
        $r = ApiExceptionFormatter::from($ex, $request);
        $payload = $r->getData(true);
        $this->assertSame('myid', $payload['meta']['request_id']);
    }
}
