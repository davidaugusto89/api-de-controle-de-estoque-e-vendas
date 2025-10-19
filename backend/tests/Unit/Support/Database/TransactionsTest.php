<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Database;

use App\Support\Database\Transactions;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\TestCase;

final class TransactionsTest extends TestCase
{
    protected function tearDown(): void
    {
        // Limpa o root do Facade para não vazar mocks entre testes
        DB::swap(null);
        Mockery::close();
        parent::tearDown();
    }

    public function test_run_executa_callback_e_retorna_valor(): void
    {
        $expected = 'ok-result';

        // Mock do DatabaseManager que o Transactions vai usar
        $manager = Mockery::mock(DatabaseManager::class);
        $manager->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($cb) {
                return $cb();
            });

        // Injeta o DatabaseManager mock diretamente no Transactions
        $tx = new Transactions($manager);

        $result = $tx->run(function () use ($expected) {
            return $expected;
        });

        $this->assertSame($expected, $result);
    }

    public function test_run_propagates_exceptions(): void
    {
        $manager = Mockery::mock(DatabaseManager::class);
        $manager->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($cb) {
                // O próprio callback lançará a exceção — deixamos propagar
                return $cb();
            });

        // Injeta o DatabaseManager mock diretamente no Transactions
        $tx = new Transactions($manager);

        $this->expectException(\RuntimeException::class);

        $tx->run(function () {
            throw new \RuntimeException('boom');
        });
    }
}
