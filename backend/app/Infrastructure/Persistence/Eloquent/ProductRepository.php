<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Models\Product;

/**
 * Repositório Eloquent para Product.
 */
class ProductRepository
{
    /** @var (callable():mixed)|null */
    private $queryResolver;

    /**
     * Injeta um resolver opcional para Product::query() (facilita testes).
     *
     * @param  callable():mixed|null  $resolver
     */
    public function setQueryResolver(?callable $resolver): void
    {
        $this->queryResolver = $resolver;
    }

    /**
     * Busca um produto pelo SKU.
     */
    public function findBySku(string $sku): ?Product
    {
        /** @var Product|null $product */
        $qb = $this->queryResolver ? ($this->queryResolver)() : Product::query();
        $product = $qb->where('sku', $sku)->first();

        return $product;
    }

    /**
     * Busca um produto pelo ID.
     */
    public function findById(int $id): ?Product
    {
        /** @var Product|null $product */
        $qb = $this->queryResolver ? ($this->queryResolver)() : Product::query();
        $product = $qb->find($id);

        return $product;
    }
}
