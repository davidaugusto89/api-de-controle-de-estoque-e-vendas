<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Traits;

use App\Support\Traits\WithQueryOptimization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WithQueryOptimizationTest extends TestCase
{
    public function test_maybe_with_aplica_relacoes_quando_nao_vazio(): void
    {
        $qb = \Mockery::mock(Builder::class);
        $qb->shouldReceive('with')->with(['rel1', 'rel2'])->once()->andReturnSelf();

        $obj = new class
        {
            use WithQueryOptimization;

            public function callMaybeWith($qb, $rel)
            {
                return $this->maybeWith($qb, $rel);
            }
        };
        $res = $obj->callMaybeWith($qb, ['rel1', 'rel2']);

        $this->assertSame($qb, $res);
    }

    public function test_maybe_with_retorna_qb_quando_vazio(): void
    {
        $qb = \Mockery::mock(Builder::class);
        $qb->shouldReceive('with')->never();

        $obj = new class
        {
            use WithQueryOptimization;

            public function callMaybeWith($qb, $rel)
            {
                return $this->maybeWith($qb, $rel);
            }
        };
        $res = $obj->callMaybeWith($qb, []);

        $this->assertSame($qb, $res);
    }

    public function test_without_model_events_executa_callback_e_retorna_valor(): void
    {
        $obj = new class
        {
            use WithQueryOptimization;

            public function callWithoutModelEvents($cb)
            {
                return $this->withoutModelEvents($cb);
            }
        };

        $called = false;
        $ret = $obj->callWithoutModelEvents(function () use (&$called) {
            $called = true;

            return 123;
        });

        $this->assertTrue($called);
        $this->assertSame(123, $ret);
    }

    public function test_with_safe_sql_mode_restoura_e_retorna_valor_do_callback(): void
    {
        DB::shouldReceive('transaction')->once()->andReturnUsing(function ($cb, $attempts = 1) {
            return $cb();
        });

        DB::shouldReceive('selectOne')->with('SELECT @@sql_mode AS m')->once()->andReturn((object) ['m' => 'ORIG_MODE']);

        // first statement to set session, second to restore with parameter
        DB::shouldReceive('statement')->withArgs(function ($query) {
            return is_string($query) && str_starts_with($query, 'SET SESSION sql_mode');
        })->once()->andReturnTrue();

        DB::shouldReceive('statement')->withArgs(function ($query, $params) {
            return $query === 'SET SESSION sql_mode = ?' && is_array($params) && ($params[0] ?? null) === 'ORIG_MODE';
        })->once()->andReturnTrue();

        $obj = new class
        {
            use WithQueryOptimization;

            public function callWithSafeSqlMode($cb)
            {
                return $this->withSafeSqlMode($cb);
            }
        };

        $res = $obj->callWithSafeSqlMode(function () {
            return 'done';
        });

        $this->assertSame('done', $res);
    }
}
