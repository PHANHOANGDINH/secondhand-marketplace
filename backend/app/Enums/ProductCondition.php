<?php

namespace App\Enums;

enum ProductCondition: string
{
    case LIKE_NEW = 'LIKE_NEW';
    case GOOD = 'GOOD';
    case FAIR = 'FAIR';
    case POOR = 'POOR';
}
