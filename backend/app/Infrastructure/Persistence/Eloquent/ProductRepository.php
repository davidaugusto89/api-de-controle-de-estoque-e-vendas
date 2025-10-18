<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Models\Product;

/**
 * Repositório Eloquent para Product.
 */
class ProductRepository
{
    public function findBySku(string $sku): ?Product
    {
        /** @var Product|null $product */
        $product = Product::query()->where('sku', $sku)->first();

        return $product;
    }

    public function findById(int $id): ?Product
    {
        /** @var Product|null $product */
        $product = Product::query()->find($id);

        return $product;
    }
}
