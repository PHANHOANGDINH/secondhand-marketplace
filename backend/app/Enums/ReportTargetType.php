<?php

namespace App\Enums;

enum ReportTargetType: string
{
    case Product = 'PRODUCT';
    case Shop = 'SHOP';
    case VideoComment = 'VIDEO_COMMENT';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Sản phẩm',
            self::Shop => 'Gian hàng',
            self::VideoComment => 'Bình luận video',
        };
    }
}
