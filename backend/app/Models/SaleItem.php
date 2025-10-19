<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa um item/linha de uma venda.
 *
 * Campos persistidos: sale_id, product_id, quantity, unit_price, unit_cost.
 * O atributo `total` é um accessor derivado (quantity * unit_price).
 *
 * @property int $id
 * @property int $sale_id
 * @property int $product_id
 * @property int $quantity
 * @property float $unit_price
 * @property float $unit_cost
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Sale $sale
 * @property-read Product $product
 * @property-read float $total Valor total da linha (quantity * unit_price)
 */
class SaleItem extends Model
{
    use HasFactory;

    protected $table = 'sale_items';

    protected $guarded = ['id'];

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'unit_cost',
    ];

    protected $casts = [
        'sale_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'float',
        'unit_cost' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relação N:1 com Sale (cabeçalho).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Sale, \App\Models\SaleItem>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Relação N:1 com Product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Product, \App\Models\SaleItem>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calcula o valor total da linha (quantity * unit_price).
     *
     * @return float Total da linha
     */
    public function getTotalAttribute(): float
    {
        return (float) ($this->quantity * $this->unit_price);
    }
}
