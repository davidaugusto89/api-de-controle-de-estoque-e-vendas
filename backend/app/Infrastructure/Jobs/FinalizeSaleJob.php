<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Application\Sales\UseCases\FinalizeSale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job para finalizar uma venda.
 */
final class FinalizeSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $saleId)
    {
        $this->onQueue('sales');
    }

    public function handle(FinalizeSale $finalize): void
    {
        $finalize->execute($this->saleId);
    }
}
