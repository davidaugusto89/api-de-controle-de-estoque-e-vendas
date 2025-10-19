<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\SaleItemRepository;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SaleItemRepository::class)]
final class SaleItemRepositoryTest extends TestCase
{
    public function test_criar_usa_query_resolver_e_retorna_sale_item(): void
    {
        /**
         * Cenário
         * Dado: query resolver que implementa create
         * Quando: create é chamado no repositório
         * Então: retorna instância de SaleItem esperada
         */
        $expected             = new SaleItem;
        $expected->id         = 123;
        $expected->sale_id    = 10;
        $expected->product_id = 5;
        $expected->quantity   = 2;
        $expected->unit_price = 9.99;
        $expected->unit_cost  = 4.5;

        $qb = new class($expected)
        {
            private $expected;

            public function __construct($expected)
            {
                $this->expected = $expected;
            }

            public function create(array $data)
            {
                return $this->expected;
            }
        };

        $repo = new SaleItemRepository;
        $repo->setQueryResolver(fn () => $qb);

        $item = $repo->create([
            'sale_id'    => 10,
            'product_id' => 5,
            'quantity'   => 2,
            'unit_price' => 9.99,
            'unit_cost'  => 4.5,
        ]);

        $this->assertSame($expected, $item);
        $this->assertEquals(123, $item->id);
    }

    public function test_encontrar_por_id_retorna_sale_item_ou_null(): void
    {
        /**
         * Cenário
         * Dado: query resolver que implementa find
         * Quando: findById é chamado
         * Então: retorna o SaleItem ou null
         */
        $expected     = new SaleItem;
        $expected->id = 7;

        $qb = new class($expected)
        {
            private $expected;

            public function __construct($expected)
            {
                $this->expected = $expected;
            }

            public function find($id)
            {
                return $this->expected;
            }
        };

        $repo = new SaleItemRepository;
        $repo->setQueryResolver(fn () => $qb);

        $result = $repo->findById(7);

        $this->assertSame($expected, $result);
    }

    public function test_encontrar_por_sale_id_retorna_collection(): void
    {
        /**
         * Cenário
         * Dado: query resolver que suporta where->get
         * Quando: findBySaleId é chamado
         * Então: retorna Collection com os itens
         */
        $item     = new SaleItem;
        $item->id = 1;

        $collection = new Collection([$item]);

        $where = new class($collection)
        {
            private $collection;

            public function __construct($collection)
            {
                $this->collection = $collection;
            }

            public function get()
            {
                return $this->collection;
            }
        };

        $qb = new class($where)
        {
            private $where;

            public function __construct($where)
            {
                $this->where = $where;
            }

            public function where($col, $val)
            {
                return $this->where;
            }
        };

        $repo = new SaleItemRepository;
        $repo->setQueryResolver(fn () => $qb);

        $result = $repo->findBySaleId(42);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame($item, $result->first());
    }

    public function test_atualizar_retorna_bool(): void
    {
        /**
         * Cenário
         * Dado: query builder que retorna 1 em update
         * Quando: update é chamado no repositório
         * Então: retorna true
         */
        $qb = new class
        {
            public function whereKey($id)
            {
                return $this;
            }

            public function update(array $data)
            {
                return 1;
            }
        };

        $repo = new SaleItemRepository;
        $repo->setQueryResolver(fn () => $qb);

        $ok = $repo->update(5, ['quantity' => 3]);

        $this->assertTrue($ok);
    }

    public function test_deletar_chama_delete_no_model_ou_retorna_null(): void
    {
        /**
         * Cenário
         * Dado: modelo com método delete disponível
         * Quando: delete é chamado no repositório
         * Então: chama delete e retorna true quando presente
         */
        $model     = new SaleItem;
        $model->id = 9;

        $called  = false;
        $deleter = new class($model, $called)
        {
            private $model;

            private $called;

            public function __construct($model, &$called)
            {
                $this->model  = $model;
                $this->called = &$called;
            }

            public function find($id)
            {
                return $this->model;
            }
        };

        // attach delete method dynamically
        $model->delete = function () use (&$called) {
            $called = true;

            return true;
        };

        // Workaround: in PHP objects, adding method like above won't be callable via $model->delete(), so we'll wrap
        $wrapper = new class($deleter, $model, $called)
        {
            private $deleter;

            private $model;

            private $called;

            public function __construct($deleter, $model, &$called)
            {
                $this->deleter = $deleter;
                $this->model   = $model;
                $this->called  = &$called;
            }

            public function find($id)
            {
                return $this->model;
            }
        };

        // provide a model that is an instance of SaleItem and exposes delete() without hitting Eloquent
        $modelWithDelete = new class extends SaleItem
        {
            public $id;

            public function delete()
            {
                return true;
            }
        };

        $modelWithDelete->id = 9;

        $deleter = new class($modelWithDelete)
        {
            private $model;

            public function __construct($model)
            {
                $this->model = $model;
            }

            public function find($id)
            {
                return $this->model;
            }
        };

        $repo = new SaleItemRepository;
        $repo->setQueryResolver(fn () => $deleter);

        $res = $repo->delete(9);

        $this->assertTrue($res);
    }

    public function test_encontrar_por_vendas_retorna_collection(): void
    {
        /**
         * Cenário
         * Dado: query builder com whereIn->get retornando collection
         * Quando: findBySales é chamado
         * Então: retorna Collection com os itens encontrados
         */
        $item     = new SaleItem;
        $item->id = 2;

        $collection = new Collection([$item]);

        $whereIn = new class($collection)
        {
            private $collection;

            public function __construct($collection)
            {
                $this->collection = $collection;
            }

            public function get()
            {
                return $this->collection;
            }
        };

        $qb = new class($whereIn)
        {
            private $whereIn;

            public function __construct($whereIn)
            {
                $this->whereIn = $whereIn;
            }

            public function whereIn($col, $vals)
            {
                return $this->whereIn;
            }
        };

        $repo = new SaleItemRepository;
        $repo->setQueryResolver(fn () => $qb);

        $res = $repo->findBySales([1, 2, 3]);

        $this->assertInstanceOf(Collection::class, $res);
        $this->assertSame($item, $res->first());
    }
}
