<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales\UseCases;

use App\Infrastructure\Events\SaleFinalized;
use App\Infrastructure\Listeners\UpdateInventoryListener;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * Teste unitário para o listener que enfileira a atualização de estoque
 *
 * Cenário:
 * - Um evento SaleFinalized é publicado quando uma venda é finalizada.
 *
 * Quando:
 * - O listener `UpdateInventoryListener::handle` é executado.
 *
 * Então:
 * - Deve enfileirar um job do tipo UpdateInventoryJob com saleId e items corretos
 *   na fila preferida "inventory".
 * - Se o dispatch lançar uma exceção, deve registrar um erro útil e repassar a exceção.
 *
 * Observações:
 * - Testes unitários puros; usamos os fakes/Facades do framework para interceptar filas
 *   e Mockery para simular comportamento excepcional do Job estático.
 */
#[CoversClass(UpdateInventoryListener::class)]
final class UpdateInventoryListenerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_2_dispatch_job_na_fila_inventory(): void
    {
        // Arrange
        Queue::fake();

        $saleId = 123;
        $items  = [
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 1],
        ];

        $event    = new SaleFinalized($saleId, $items);
        $listener = new UpdateInventoryListener;

        // Act
        $listener->handle($event);

        // Assert: job enfileirado na fila 'inventory' com payload correto
        Queue::assertPushedOn(
            'inventory',
            \App\Infrastructure\Jobs\UpdateInventoryJob::class,
            static function ($job) use ($saleId, $items): bool {
                return isset($job->saleId, $job->items)
                    && $job->saleId === $saleId
                    && $job->items  === $items;
            }
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_3_logs_and_rethrows_exception_when_dispatch_fails(): void
    {
        // Arrange: não fakeamos a fila aqui porque queremos mockar o método estático
        $saleId = 999;
        $items  = [
            ['product_id' => 42, 'quantity' => 3],
        ];

        $event = new SaleFinalized($saleId, $items);

        // Mock do UpdateInventoryJob estático via alias — precisa rodar em processo separado
        $exception = new \RuntimeException('dispatch-failed');
        Mockery::mock('alias:App\\Infrastructure\\Jobs\\UpdateInventoryJob')
            ->shouldReceive('dispatch')
            ->with($saleId, $items)
            ->andThrow($exception);

        // Swap the underlying logger used by the Log facade with a Mockery mock
        // so we can assert the error call even when running in a separate process.
        $loggerMock = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $loggerMock->shouldReceive('info')->andReturnNull();
        $loggerMock->shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) use ($saleId, $items, $exception) {
                return $message === 'Falha no UpdateInventoryListener'
                    && isset($context['sale_id'], $context['items'], $context['error'])
                    && $context['sale_id'] === $saleId
                    && $context['items']   === $items
                    && str_contains((string) $context['error'], $exception->getMessage());
            });

        Log::swap($loggerMock);

        $listener = new UpdateInventoryListener;

        $this->expectException(\RuntimeException::class);

        // Act
        $listener->handle($event);
    }
}
