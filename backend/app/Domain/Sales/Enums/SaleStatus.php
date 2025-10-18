<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

enum SaleStatus: string
{
    case QUEUED     = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case CANCELLED  = 'cancelled';
}
