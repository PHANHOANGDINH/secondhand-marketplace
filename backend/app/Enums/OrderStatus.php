<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case PARTIALLY_COMPLETED = 'PARTIALLY_COMPLETED';
    case CANCELLED = 'CANCELLED';
}
