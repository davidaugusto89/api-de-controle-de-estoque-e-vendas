<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\SaleRepository;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SaleRepository::class)]
final class SaleRepositoryTest extends TestCase
{
    public function test_find_por_id_retorna_venda(): void
    {
        /**
         * Cenário
         * Dado: query resolver simulado que retorna uma venda
         * Quando: findById é chamado
         * Então: retorna a instância da venda
         */
        if (class_exists('\Mockery')) {
            \Mockery::close();
        }
        $sale     = new Sale;
        $sale->id = 11;

        $qb = new class($sale)
        {
            private $sale;

            public function __construct($sale)
            {
                $this->sale = $sale;
            }

            public function find($id)
            {
                return $this->sale;
            }
        };

        $repo = new SaleRepository;
        $repo->setQueryResolver(fn () => $qb);

        $res = $repo->findById(11);

        $this->assertSame($sale, $res);
    }

    public function test_find_com_itens_retorna_venda_com_itens(): void
    {
        /**
         * Cenário
         * Dado: query resolvers que simulam 'with' e 'find'
         * Quando: findWithItems é chamado
         * Então: retorna a venda com itens associados
         */
        if (class_exists('\Mockery')) {
            \Mockery::close();
        }
        $sale     = new Sale;
        $sale->id = 12;

        $with = new class($sale)
        {
            private $sale;

            public function __construct($sale)
            {
                $this->sale = $sale;
            }

            public function find($id)
            {
                return $this->sale;
            }
        };

        $qb = new class($with)
        {
            private $with;

            public function __construct($with)
            {
                $this->with = $with;
            }

            public function with($rel)
            {
                return $this->with;
            }
        };

        $repo = new SaleRepository;
        $repo->setQueryResolver(fn () => $qb);

        $res = $repo->findWithItems(12);

        $this->assertSame($sale, $res);
    }

    public function test_criar_retorna_venda(): void
    {
        /**
         * Cenário
         * Dado: query resolver simula create
         * Quando: create é chamado no repositório
         * Então: retorna a instância criada
         */
        if (class_exists('\Mockery')) {
            \Mockery::close();
        }
        $sale     = new Sale;
        $sale->id = 20;

        $qb = new class($sale)
        {
            private $sale;

            public function __construct($sale)
            {
                $this->sale = $sale;
            }

            public function create(array $data)
            {
                return $this->sale;
            }
        };

        $repo = new SaleRepository;
        $repo->setQueryResolver(fn () => $qb);

        $res = $repo->create([
            'total_amount' => 100.0,
            'total_cost'   => 60.0,
            'total_profit' => 40.0,
            'status'       => 'finalized',
        ]);

        $this->assertSame($sale, $res);
    }

    public function test_atualizar_retorna_bool(): void
    {
        /**
         * Cenário
         * Dado: update no query builder retorna 1
         * Quando: update é chamado no repositório
         * Então: retorna true
         */
        if (class_exists('\Mockery')) {
            \Mockery::close();
        }
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

        $repo = new SaleRepository;
        $repo->setQueryResolver(fn () => $qb);

        $ok = $repo->update(5, ['status' => 'canceled']);

        $this->assertTrue($ok);
    }

    public function test_deletar_retorna_bool_quando_encontrado(): void
    {
        /**
         * Cenário
         * Dado: modelo existe e delete retorna true
         * Quando: delete é chamado no repositório
         * Então: retorna true
         */
        if (class_exists('\Mockery')) {
            \Mockery::close();
        }
        $model = new class extends Sale
        {
            public $id;

            public function delete()
            {
                return true;
            }
        };

        $model->id = 33;

        $deleter = new class($model)
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

        $repo = new SaleRepository;
        $repo->setQueryResolver(fn () => $deleter);

        $res = $repo->delete(33);

        $this->assertTrue($res);
    }

    public function test_encontrar_varios_retorna_collection(): void
    {
        /**
         * Cenário
         * Dado: query builder simula whereIn->get retornando collection
         * Quando: findMany é chamado
         * Então: retorna Collection com os modelos
         */
        if (class_exists('\Mockery')) {
            \Mockery::close();
        }
        $s     = new Sale;
        $s->id = 44;

        $collection = new Collection([$s]);

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

        $repo = new SaleRepository;
        $repo->setQueryResolver(fn () => $qb);

        $res = $repo->findMany([1, 2]);

        $this->assertInstanceOf(Collection::class, $res);
        $this->assertSame($s, $res->first());
    }
}
