<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

/**
 * Status de uma venda.
 *
 * QUEUED: Venda aguardando processamento.
 * PROCESSING: Venda em processamento.
 * COMPLETED: Venda concluida.
 * CANCELLED: Venda cancelada.
 */
enum SaleStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
