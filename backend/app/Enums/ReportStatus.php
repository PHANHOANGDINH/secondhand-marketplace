<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Pending = 'PENDING';
    case InReview = 'IN_REVIEW';
    case Resolved = 'RESOLVED';
    case Dismissed = 'DISMISSED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xử lý',
            self::InReview => 'Đang xem xét',
            self::Resolved => 'Đã xử lý',
            self::Dismissed => 'Không vi phạm',
        };
    }
}
