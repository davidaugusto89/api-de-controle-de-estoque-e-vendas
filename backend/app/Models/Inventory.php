<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa o inventário de um produto.
 *
 * A tabela `inventory` guarda a quantidade atual, versão e timestamp de
 * última atualização. A relação `product` aponta para o produto associado.
 *
 * @property int $id
 * @property int $product_id
 * @property int $quantity
 * @property int $version
 * @property \Illuminate\Support\Carbon|null $last_updated
 * @property-read Product $product
 */
class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $guarded = ['id'];

    protected $fillable = [
        'product_id',
        'quantity',
        'last_updated',
        'version',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'version' => 'integer',
        'last_updated' => 'datetime',
    ];

    /**
     * Relação N:1 com o produto associado.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Product, self>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Escopo para retornar apenas registros com quantidade positiva.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('quantity', '>', 0);
    }
}
