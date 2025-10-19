<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Queries;

use App\Infrastructure\Persistence\Queries\InventoryQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InventoryQuery::class)]
final class InventoryQueryTest extends TestCase
{
    public function test_list_calls_base_query_and_returns_array_rows(): void
    {
        /**
         * Cenário
         * Dado: query builder fake que retorna linhas
         * Quando: list é chamado
         * Então: retorna iterable com arrays de linhas
         */
        $row = (object) ['product_id' => 1, 'sku' => 'X', 'name' => 'Prod', 'quantity' => 5];

        $get = new class($row)
        {
            private $row;

            public function __construct($row)
            {
                $this->row = $row;
            }

            public function get()
            {
                return collect([$this->row]);
            }

            public function limit($n)
            {
                return $this;
            }
        };

        $inv = new class($get)
        {
            private $get;

            public function __construct($get)
            {
                $this->get = $get;
            }

            public function join()
            {
                return $this;
            }

            public function select()
            {
                return $this;
            }

            public function orderBy()
            {
                return $this;
            }

            public function where()
            {
                return $this;
            }

            public function whereRaw()
            {
                return $this;
            }

            public function orWhereRaw()
            {
                return $this;
            }

            public function limit($n)
            {
                return $this;
            }

            public function get()
            {
                return $this->get->get();
            }
        };

        $q = new InventoryQuery;
        $q->setDbResolver(fn () => $inv);

        $res = $q->list(null, 10);

        $this->assertIsIterable($res);
        $this->assertSame(['product_id' => 1, 'sku' => 'X', 'name' => 'Prod', 'quantity' => 5], $res->first());
    }

    public function test_by_product_id_returns_array_or_null(): void
    {
        /**
         * Cenário
         * Dado: base query retornando primeira linha
         * Quando: byProductId é chamado
         * Então: retorna array com propriedades ou null
         */
        $row = (object) ['product_id' => 2, 'sku' => 'Y'];

        $inv = new class($row)
        {
            private $row;

            public function __construct($row)
            {
                $this->row = $row;
            }

            public function join()
            {
                return $this;
            }

            public function select()
            {
                return $this;
            }

            public function orderBy()
            {
                return $this;
            }

            public function where($col, $val)
            {
                return $this;
            }

            public function whereRaw()
            {
                return $this;
            }

            public function orWhereRaw()
            {
                return $this;
            }

            public function first()
            {
                return $this->row;
            }
        };

        $q = new InventoryQuery;
        $q->setDbResolver(fn () => $inv);

        $res = $q->byProductId(2);

        $this->assertSame(['product_id' => 2, 'sku' => 'Y'], $res);
    }

    public function test_totals_computes_projection(): void
    {
        /**
         * Cenário
         * Dado: projeção de totais via selectRaw
         * Quando: totals é invocado
         * Então: retorna total_cost, total_sale e projected_profit calculado
         */
        $tot = (object) ['total_cost' => 100.0, 'total_sale' => 150.0];

        $db = new class($tot)
        {
            private $tot;

            public function __construct($tot)
            {
                $this->tot = $tot;
            }

            public function join()
            {
                return $this;
            }

            public function where()
            {
                return $this;
            }

            public function selectRaw()
            {
                return $this;
            }

            public function first()
            {
                return $this->tot;
            }
        };

        // fake DB facade by setting resolver on the InventoryQuery's baseQuery path
        $q = new InventoryQuery;
        $q->setDbResolver(fn () => $db);

        $res = $q->totals(null);

        $this->assertSame(100.0, $res['total_cost']);
        $this->assertSame(150.0, $res['total_sale']);
        $this->assertSame(50.0, $res['projected_profit']);
    }
}
