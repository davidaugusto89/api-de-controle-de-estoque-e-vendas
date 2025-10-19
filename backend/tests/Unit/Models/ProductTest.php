<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
final class ProductTest extends TestCase
{
    public function test_configuracao_do_modelo(): void
    {
        /**
         * Cenário
         * Dado: instância do model Product
         * Quando: consultamos configuração (table, guarded, fillable, casts)
         * Então: valores esperados estão presentes
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }
        $m = new Product;

        $this->assertSame('products', $m->getTable());
        $this->assertContains('id', $m->getGuarded());

        $fillable = $m->getFillable();
        $this->assertContains('sku', $fillable);
        $this->assertContains('name', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('cost_price', $fillable);
        $this->assertContains('sale_price', $fillable);

        $casts = $m->getCasts();
        $this->assertArrayHasKey('cost_price', $casts);
        $this->assertArrayHasKey('sale_price', $casts);
    }

    public function test_accessor_profit_calcula_sale_minus_cost(): void
    {
        /**
         * Cenário
         * Dado: product com sale_price e cost_price
         * Quando: acessamos $product->profit
         * Então: retorno é sale_price - cost_price
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }
        $p = new Product;
        $p->sale_price = 25.5;
        $p->cost_price = 10.25;

        $this->assertSame(15.25, $p->profit);
    }

    public function test_relacao_inventory_retorna_hasone(): void
    {
        /**
         * Cenário
         * Dado: método inventory definido no model
         * Quando: inspecionamos o return type e executamos com stub
         * Então: return type é HasOne e invocação retorna stub
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }
        $ref = new \ReflectionMethod(Product::class, 'inventory');
        $return = $ref->getReturnType();

        $this->assertNotNull($return);
        $this->assertSame(HasOne::class, $return->getName());

        // execute the line by overriding belongsTo/hasOne to return a stub
        $stub = new class extends HasOne
        {
            public function __construct() {}
        };

        $sub = new class($stub) extends Product
        {
            private $stub;

            public function __construct($stub)
            {
                $this->stub = $stub;
            }

            public function hasOne($related, $foreignKey = null, $localKey = null)
            {
                return $this->stub;
            }
        };

        $this->assertSame($stub, $sub->inventory());
    }

    public function test_relacao_sale_items_retorna_hasmany(): void
    {
        /**
         * Cenário
         * Dado: método saleItems definido no model
         * Quando: inspecionamos o return type e executamos com stub
         * Então: return type é HasMany e invocação retorna stub
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }
        $ref = new \ReflectionMethod(Product::class, 'saleItems');
        $return = $ref->getReturnType();

        $this->assertNotNull($return);
        $this->assertSame(HasMany::class, $return->getName());

        $stub = new class extends HasMany
        {
            public function __construct() {}
        };

        $sub = new class($stub) extends Product
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

        $this->assertSame($stub, $sub->saleItems());
    }

    public function test_scope_sku_filtra_por_sku(): void
    {
        /**
         * Cenário
         * Dado: scopeSku aplicado ao query builder
         * Quando: chamamos scopeSku com um SKU
         * Então: delega where com os parâmetros corretos
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }
        $called = [];

        $fakeBuilder = new class
        {
            public $calledRef;

            public function where($col, $op = null, $val = null)
            {
                $this->calledRef[] = func_get_args();

                return 'RESULT';
            }
        };
        $fakeBuilder->calledRef = &$called;

        $p = new Product;
        $res = $p->scopeSku($fakeBuilder, 'ABC123');

        $this->assertSame('RESULT', $res);
        $this->assertCount(1, $called);
        $this->assertSame(['sku', 'ABC123'], $called[0] ?? $called[0]);
    }
}
