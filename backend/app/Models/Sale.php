<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Representa uma venda realizada no sistema.
 *
 * A tabela `sales` armazena informações agregadas sobre cada venda,
 * incluindo valores totais e status. A relação `items` aponta para
 * os itens individuais vendidos nesta venda.
 *
 * @property int $id
 * @property float $total_amount
 * @property float $total_cost
 * @property float $total_profit
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $sale_date
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SaleItem> $items
 */
class Sale extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'sales';

    protected $guarded = ['id', 'sale_date'];

    protected $fillable = [
        'total_amount',
        'total_cost',
        'total_profit',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'sale_date' => 'date',
    ];

    /**
     * Relação 1:N com os itens/linhas desta venda.
     *
     * @return HasMany<\App\Models\SaleItem, self>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Filtro por intervalo *calendário* (date) na coluna indexada created_at
     *
     * @param [type] $query
     * @return void
     */
    public function scopeBetweenDates($query, CarbonImmutable $from, CarbonImmutable $to)
    {
        return $query->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);
    }

    /**
     * Filtro por intervalo *data* (date) na coluna indexada sale_date
     *
     * @param [type] $query
     * @return void
     */
    public function scopeBetweenDays($query, CarbonImmutable $from, CarbonImmutable $to)
    {
        return $query->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * Verifica se a venda está completa.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Atributo acessor para total_amount garantindo float.
     */
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? 0.0 : (float) $value
        );
    }

    /**
     * Atributo acessor para total_cost garantindo float.
     */
    protected function totalCost(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? 0.0 : (float) $value
        );
    }

    /**
     * Atributo acessor para total_profit garantindo float.
     */
    protected function totalProfit(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? 0.0 : (float) $value
        );
    }
}
