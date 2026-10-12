<?php

namespace App\Enums;

enum ProductVideoStatus: string
{
    case Processing = 'PROCESSING';
    case Ready = 'READY';
    case Hidden = 'HIDDEN';
    case Failed = 'FAILED';
}
