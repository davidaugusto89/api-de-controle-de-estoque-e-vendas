<?php

declare(strict_types=1);

use App\Application\Inventory\UseCases\CleanupOldInventory;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduler
|--------------------------------------------------------------------------
| Aqui você pode registrar comandos Artisan customizados
| e tarefas agendadas (cron jobs).
|
*/

/**
 * Limpa o inventário de itens antigos e órfãos.
 */
Schedule::call(function () {
    app(CleanupOldInventory::class)->handle();
})
    ->dailyAt('02:00')
    ->name('cleanup-old-inventory')
    ->onOneServer();
