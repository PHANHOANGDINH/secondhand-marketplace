<?php

namespace App\Enums;

enum AuctionStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case LIVE = 'LIVE';
    case ENDED = 'ENDED';
    case CANCELLED = 'CANCELLED';
}
