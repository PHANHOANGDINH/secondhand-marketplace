<?php

namespace App\Enums;

enum ShopOrderStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case PROCESSING = 'PROCESSING';
    case SHIPPING = 'SHIPPING';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
