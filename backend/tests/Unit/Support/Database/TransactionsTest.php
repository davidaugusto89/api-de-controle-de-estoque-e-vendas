<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Database;

use App\Support\Database\Transactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

final class TransactionsTest extends TestCase
{
    protected function tearDown(): void
    {
        // Ensure facade state cleared
        DB::swap(null);
        parent::tearDown();
    }

    public function test_run_executa_callback_e_retorna_valor(): void
    {
        $expected = 'ok-result';

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($cb) use ($expected) {
                return $cb();
            });

        $tx = new Transactions();

        $result = $tx->run(function () use ($expected) {
            return $expected;
        });

        $this->assertSame($expected, $result);
    }

    public function test_run_propagates_exceptions(): void
    {
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($cb) {
                return $cb();
            });

        $tx = new Transactions();

        $this->expectException(\RuntimeException::class);

        $tx->run(function () {
            throw new \RuntimeException('boom');
        });
    }
}
