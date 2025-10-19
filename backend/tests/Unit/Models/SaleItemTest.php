<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SaleItemTest extends TestCase
{
    public function test_configuracao_do_modelo(): void
    {
        /**
         * Cenário
         * Dado: instância do model SaleItem
         * Quando: consultamos table, guarded, fillable e casts
         * Então: valores esperados estão presentes
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }

        $m = new SaleItem;

        $this->assertSame('sale_items', $m->getTable());
        $this->assertContains('id', $m->getGuarded());

        $fillable = $m->getFillable();
        $this->assertContains('sale_id', $fillable);
        $this->assertContains('product_id', $fillable);
        $this->assertContains('quantity', $fillable);
        $this->assertContains('unit_price', $fillable);
        $this->assertContains('unit_cost', $fillable);

        $casts = $m->getCasts();
        $this->assertArrayHasKey('quantity', $casts);
        $this->assertArrayHasKey('unit_price', $casts);
    }

    public function test_relacoes_sale_e_product_sao_belongsto(): void
    {
        /**
         * Cenário
         * Dado: métodos sale e product no model SaleItem
         * Quando: inspecionamos return type e executamos com stub
         * Então: return type é BelongsTo e invocação retorna stub
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }

        $ref1   = new \ReflectionMethod(SaleItem::class, 'sale');
        $return = $ref1->getReturnType();

        $this->assertNotNull($return);
        $this->assertSame(BelongsTo::class, $return->getName());

        $stub = new class extends BelongsTo
        {
            public function __construct() {}
        };

        $sub = new class($stub) extends SaleItem
        {
            private $stub;

            public function __construct($stub)
            {
                $this->stub = $stub;
            }

            public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
            {
                return $this->stub;
            }
        };

        $this->assertSame($stub, $sub->sale());
        $this->assertSame($stub, $sub->product());
    }

    public function test_accessor_total_calcula_quantidade_vezes_unit_price(): void
    {
        /**
         * Cenário
         * Dado: SaleItem com quantity e unit_price
         * Quando: acessamos o atributo total
         * Então: retorna quantity * unit_price
         */
        if (class_exists('Mockery')) {
            \Mockery::close();
        }

        $it = new SaleItem;

        $it->quantity   = 3;
        $it->unit_price = 12.5;

        $this->assertSame(37.5, $it->total);
    }
}
