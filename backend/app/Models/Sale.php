<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'sales';

    protected $guarded = ['id'];

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

    /** @return HasMany<\App\Models\SaleItem> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Filtro por intervalo *temporal* (timestamp) em created_at */
    public function scopeBetweenDates($query, CarbonImmutable $from, CarbonImmutable $to)
    {
        return $query->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);
    }

    /** Filtro por intervalo *calendário* (date) na coluna indexada sale_date */
    public function scopeBetweenDays($query, CarbonImmutable $from, CarbonImmutable $to)
    {
        return $query->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()]);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    // Accessors abaixo são opcionais, pois os casts já cuidam. Mantidos por compat.
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? 0.0 : (float) $value
        );
    }

    protected function totalCost(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? 0.0 : (float) $value
        );
    }

    protected function totalProfit(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? 0.0 : (float) $value
        );
    }
}
