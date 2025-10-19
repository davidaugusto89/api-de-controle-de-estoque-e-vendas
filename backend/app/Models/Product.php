<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Representa um produto no sistema.
 *
 * Propriedades gerenciadas pelo Eloquent e disponíveis no modelo.
 * A propriedade `profit` é um accessor que expõe o lucro unitário (sale_price - cost_price).
 *
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property float $cost_price
 * @property float $sale_price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read float $profit
 */
class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $guarded = ['id'];

    protected $fillable = [
        'sku',
        'name',
        'description',
        'cost_price',
        'sale_price',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'sale_price' => 'float',
    ];

    /**
     * Relação 1:1 com o inventário do produto.
     *
     * @return HasOne|\Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    /**
     * Relação 1:N com itens de venda associados ao produto.
     *
     * @return HasMany|\Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Calcula o lucro unitário do produto.
     *
     * Disponível como atributo acessor `$product->profit`.
     *
     * @return float Lucro unitário (sale_price - cost_price)
     */
    public function getProfitAttribute(): float
    {
        return (float) ($this->sale_price - $this->cost_price);
    }

    /**
     * Filtra os produtos por SKU.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSku($query, ?string $sku)
    {
        return $sku ? $query->where('sku', $sku) : $query;
    }
}
