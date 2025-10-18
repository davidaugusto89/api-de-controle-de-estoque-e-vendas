<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Mapeia eventos -> listeners.
 */
final class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \App\Infrastructure\Events\SaleFinalized::class => [
            \App\Infrastructure\Listeners\UpdateInventoryListener::class,
        ],
    ];
}
