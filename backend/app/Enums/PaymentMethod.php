<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case COD = 'COD';
    case ZALOPAY = 'ZALOPAY';
    case VNPAY = 'VNPAY';
}
