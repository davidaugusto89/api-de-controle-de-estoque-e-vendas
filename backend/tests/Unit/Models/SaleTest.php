<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SaleTest extends TestCase
{
    public function test_configuracao_do_modelo(): void
    {
        /**
         * Cenário
         * Dado: instância do model Sale
         * Quando: consultamos table, guarded, fillable e casts
         * Então: valores esperados estão presentes
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }

        $m = new Sale;

        $this->assertSame('sales', $m->getTable());
        $this->assertContains('id', $m->getGuarded());

        $fillable = $m->getFillable();
        $this->assertContains('total_amount', $fillable);
        $this->assertContains('total_cost', $fillable);
        $this->assertContains('total_profit', $fillable);
        $this->assertContains('status', $fillable);

        $casts = $m->getCasts();
        $this->assertArrayHasKey('total_amount', $casts);
        $this->assertArrayHasKey('sale_date', $casts);
    }

    public function test_relacao_items_retorna_hasmany(): void
    {
        /**
         * Cenário
         * Dado: método items no model Sale
         * Quando: inspecionamos return type e executamos com stub
         * Então: return type é HasMany e invocação retorna stub
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }

        $ref    = new \ReflectionMethod(Sale::class, 'items');
        $return = $ref->getReturnType();

        $this->assertNotNull($return);
        $this->assertSame(HasMany::class, $return->getName());

        $stub = new class extends HasMany
        {
            public function __construct() {}
        };

        $sub = new class($stub) extends Sale
        {
            private $stub;

            public function __construct($stub)
            {
                $this->stub = $stub;
            }

            public function hasMany($related, $foreignKey = null, $localKey = null)
            {
                return $this->stub;
            }
        };

        $this->assertSame($stub, $sub->items());
    }

    public function test_scopes_betweendates_e_betweendays_filtram_corretamente(): void
    {
        /**
         * Cenário
         * Dado: fazemos chamadas aos scopes com um builder fake
         * Quando: chamamos scopeBetweenDates e scopeBetweenDays
         * Então: delegam whereBetween com os parâmetros esperados
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }

        $called = [];

        $fakeBuilder = new class
        {
            public $calledRef;

            public function whereBetween($col, $vals)
            {
                $this->calledRef[] = func_get_args();

                return 'RESULT';
            }
        };
        $fakeBuilder->calledRef = &$called;

        $s = new Sale;

        $from = CarbonImmutable::parse('2025-01-01');
        $to   = CarbonImmutable::parse('2025-01-03');

        $res1 = $s->scopeBetweenDates($fakeBuilder, $from, $to);
        $this->assertSame('RESULT', $res1);
        $this->assertCount(1, $called);

        // clear and test betweenDays
        $called                 = [];
        $fakeBuilder->calledRef = &$called;

        $res2 = $s->scopeBetweenDays($fakeBuilder, $from, $to);
        $this->assertSame('RESULT', $res2);
        $this->assertCount(1, $called);
    }

    public function test_iscompleted_e_accessors_totais(): void
    {
        /**
         * Cenário
         * Dado: instância de Sale com status e totais nulos/definidos
         * Quando: chamamos isCompleted e acessamos total_amount/cost/profit
         * Então: comportamentos são os esperados
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }

        $s = new Sale;

        $s->status = Sale::STATUS_COMPLETED;
        $this->assertTrue($s->isCompleted());

        $s2 = new Sale;
        // when totals are null, accessors should return 0.0
        $this->assertSame(0.0, $s2->total_amount);
        $this->assertSame(0.0, $s2->total_cost);
        $this->assertSame(0.0, $s2->total_profit);
    }
}
