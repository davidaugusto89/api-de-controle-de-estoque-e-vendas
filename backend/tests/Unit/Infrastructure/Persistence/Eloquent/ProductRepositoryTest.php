<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\ProductRepository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ProductRepository::class)]
final class ProductRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_find_por_sku_retorna_produto_ou_null(): void
    {
        /**
         * Cenário
         * Dado: query builder mockado que retorna um produto para SKU
         * Quando: findBySku é chamado
         * Então: retorna a instância do produto
         */
        $repo = new ProductRepository;

        $qb           = Mockery::mock();
        $product      = new \App\Models\Product;
        $product->id  = 1;
        $product->sku = 'SKU-1';

        $qb->shouldReceive('where')->with('sku', 'SKU-1')->once()->andReturnSelf();
        $qb->shouldReceive('first')->once()->andReturn($product);

        $repo->setQueryResolver(fn () => $qb);

        $res = $repo->findBySku('SKU-1');

        $this->assertSame($product, $res);
    }

    public function test_find_por_id_retorna_produto_ou_null(): void
    {
        /**
         * Cenário
         * Dado: query builder mockado que retorna um produto por id
         * Quando: findById é chamado
         * Então: retorna a instância do produto
         */
        $repo = new ProductRepository;

        $qb           = Mockery::mock();
        $product      = new \App\Models\Product;
        $product->id  = 2;
        $product->sku = 'SKU-2';

        $qb->shouldReceive('find')->with(2)->once()->andReturn($product);

        $repo->setQueryResolver(fn () => $qb);

        $res = $repo->findById(2);

        $this->assertSame($product, $res);
    }
}
