<?php

declare(strict_types=1);

namespace App\Console;

use App\Application\Inventory\UseCases\CleanupOldInventory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // roda diariamente às 02:00
        $schedule->call(function (CleanupOldInventory $useCase) {
            $useCase->execute();
        })->dailyAt('02:00')->name('inventory:cleanup-old');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
