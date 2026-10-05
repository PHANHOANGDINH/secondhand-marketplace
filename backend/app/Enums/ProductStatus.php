<?php

namespace App\Enums;

enum ProductStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case SOLD = 'SOLD';
    case HIDDEN = 'HIDDEN';
    case REJECTED = 'REJECTED';
}
