<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Jobs;

use App\Application\Sales\UseCases\FinalizeSale;
use App\Infrastructure\Jobs\FinalizeSaleJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * FinalizeSaleJobTest
 *
 * Cenário:
 * - Job que delega finalização da venda ao use case FinalizeSale e é enfileirável.
 *
 * Quando:
 * - O Job é construído com um saleId válido.
 * - O método handle é executado com o use case injetado.
 * - Um saleId inválido é passado ao construtor.
 *
 * Então:
 * - O Job implementa ShouldQueue e armazena o saleId.
 * - O método handle chama FinalizeSale::execute com o saleId correto.
 * - Um TypeError é lançado para saleId com tipo incorreto (strict_types=1).
 *
 * Observações:
 * - Testes puramente unitários; FinalizeSale é mockado.
 */
#[CoversClass(FinalizeSaleJob::class)]
final class FinalizeSaleJobTest extends TestCase
{
    public function test_job_implementa_should_queue_e_armazenar_saleid(): void
    {
        /**
         * Cenário
         * Dado: Job instanciado com saleId inteiro
         * Quando: instanciado
         * Então: implementa ShouldQueue e armazena saleId corretamente
         */
        $job = new FinalizeSaleJob(42);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(42, $job->saleId);
    }

    public function test_handle_delega_para_o_use_case_finalize(): void
    {
        /**
         * Cenário
         * Dado: um FinalizeSale mockado
         * Quando: handle do job é executado com o use case injetado
         * Então: FinalizeSale::execute é chamado com o saleId correto
         */
        $saleId = 99;

        /** @var FinalizeSale&MockObject $finalize */
        $finalize = $this->getMockBuilder(FinalizeSale::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute'])
            ->getMock();

        $finalize->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($saleId));

        $job = new FinalizeSaleJob($saleId);

        $job->handle($finalize);
    }

    public function test_lancar_type_error_quando_saleid_nao_inteiro(): void
    {
        /**
         * Cenário
         * Dado: valor não inteiro passado para saleId
         * Quando: construtor é invocado com tipo incorreto
         * Então: TypeError é lançado em runtime (strict_types=1)
         */
        $this->expectException(\TypeError::class);

        // Evita detecção estática do literal para que o TypeError ocorra em runtime
        $bad = (static function () {
            return 'x';
        })();

        /** @var mixed $bad */
        call_user_func(function () use ($bad) {
            new FinalizeSaleJob($bad);
        });
    }
}
