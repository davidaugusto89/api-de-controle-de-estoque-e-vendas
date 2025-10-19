<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Queries;

use App\Infrastructure\Persistence\Queries\SaleDetailsQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SaleDetailsQuery::class)]
final class SaleDetailsQueryTest extends TestCase
{
    public function test_by_id_returns_null_when_not_found(): void
    {
        /**
         * Cenário
         * Dado: nenhuma linha encontrada para id
         * Quando: byId é chamado
         * Então: retorna null
         */
        $q = new SaleDetailsQuery;
        $q->setDbResolver(fn () => new class
        {
            public function where()
            {
                return $this;
            }

            public function select()
            {
                return $this;
            }

            public function first()
            {
                return null;
            }
        });

        $this->assertNull($q->byId(999));
    }

    public function test_by_id_returns_sale_with_items(): void
    {
        /**
         * Cenário
         * Dado: sale e items simulados via resolver
         * Quando: byId é chamado
         * Então: retorna array com sale e lista de items
         */
        $sale = (object) [
            'id'           => 1,
            'status'       => 'finalized',
            'total_amount' => '200',
            'total_cost'   => '120',
            'total_profit' => '80',
            'created_at'   => '2025-10-19 00:00:00',
            'updated_at'   => '2025-10-19 00:00:00',
        ];

        $item = (object) ['product_id' => 5, 'sku' => 'X', 'name' => 'Prod X', 'quantity' => 2, 'unit_price' => 50, 'unit_cost' => 30];

        $resolver = fn () => new class($sale, $item)
        {
            private $sale;

            private $item;

            public function __construct($sale, $item)
            {
                $this->sale = $sale;
                $this->item = $item;
            }

            public function where()
            {
                return $this;
            }

            public function select()
            {
                return $this;
            }

            public function first()
            {
                return $this->sale;
            }

            public function join()
            {
                return $this;
            }

            public function orderBy()
            {
                return $this;
            }

            public function get()
            {
                return collect([$this->item]);
            }
        };

        $q = new SaleDetailsQuery;
        $q->setDbResolver($resolver);

        $res = $q->byId(1);

        $this->assertEquals(1, $res['id']);
        $this->assertCount(1, $res['items']);
        $this->assertEquals('Prod X', $res['items'][0]['name']);
    }
}
