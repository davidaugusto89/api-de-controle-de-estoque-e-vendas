<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiExceptionFormatter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApiExceptionFormatterTest extends TestCase
{
    public function test_validation_exception_formats_response()
    {
        $request = Request::create('/test');

        $validator = new class {
            public function errors()
            {
                return ['field' => ['error']];
            }
        };

        $e = new ValidationException($validator, response()->json([]));

        $res = ApiExceptionFormatter::from($e, $request);

        $this->assertSame(422, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('ValidationError', $payload['error']['code']);
        $this->assertArrayHasKey('errors', $payload['error']['details']);
    }

    public function test_authentication_and_authorization_and_not_found_exceptions()
    {
        $request = Request::create('/');

        $e1 = new AuthenticationException();
        $r1 = ApiExceptionFormatter::from($e1, $request);
        $this->assertSame(401, $r1->getStatusCode());

        $e2 = new AuthorizationException();
        $r2 = ApiExceptionFormatter::from($e2, $request);
        $this->assertSame(403, $r2->getStatusCode());

        $e3 = new ModelNotFoundException();
        $r3 = ApiExceptionFormatter::from($e3, $request);
        $this->assertSame(404, $r3->getStatusCode());

        $e4 = new NotFoundHttpException();
        $r4 = ApiExceptionFormatter::from($e4, $request);
        $this->assertSame(404, $r4->getStatusCode());
    }

    public function test_method_not_allowed_and_throttle_include_headers()
    {
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

    public function test_method_not_allowed_when_allow_is_array()
    {
        $request = Request::create('/');

        $e = new MethodNotAllowedHttpException(['GET']);
        // força o header Allow como array para simular formatos diferentes
        $e->setHeaders(['Allow' => ['GET', 'POST']]);

        $res = ApiExceptionFormatter::from($e, $request);
        $this->assertSame(405, $res->getStatusCode());
        $this->assertTrue($res->headers->has('Allow'));
        $this->assertSame('GET, POST', $res->headers->get('Allow'));
    }

    public function test_method_not_allowed_when_allow_missing()
    {
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

    public function test_http_exception_maintains_status_and_debug_when_enabled()
    {
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

    public function test_query_exception_and_fallback_have_500_and_debug_off_by_default()
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

    public function test_query_and_generic_exception_include_debug_when_enabled()
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

    public function test_throttle_requests_preserves_multiple_headers_and_array_values()
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
        // when headers provided as arrays, Laravel normalizes to strings when getting header
        $this->assertEquals('60', $r2->headers->get('Retry-After'));
        $this->assertEquals('100', $r2->headers->get('X-RateLimit-Limit'));
    }

    public function test_throttle_includes_debug_when_enabled()
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

    public function test_request_id_is_used_when_header_present()
    {
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_REQUEST_ID' => 'myid'] );
        $ex = new \Exception('x');
        $r = ApiExceptionFormatter::from($ex, $request);
        $payload = $r->getData(true);
        $this->assertSame('myid', $payload['meta']['request_id']);
    }
}
