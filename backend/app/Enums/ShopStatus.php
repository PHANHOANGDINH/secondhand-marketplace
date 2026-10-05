<?php

namespace App\Enums;

enum ShopStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case REJECTED = 'REJECTED';
}
