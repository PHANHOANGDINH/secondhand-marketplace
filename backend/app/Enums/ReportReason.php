<?php

namespace App\Enums;

enum ReportReason: string
{
    case Scam = 'SCAM';
    case Counterfeit = 'COUNTERFEIT';
    case Misleading = 'MISLEADING';
    case ProhibitedContent = 'PROHIBITED_CONTENT';
    case Spam = 'SPAM';
    case Harassment = 'HARASSMENT';
    case InappropriateContent = 'INAPPROPRIATE_CONTENT';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Scam => 'Có dấu hiệu lừa đảo',
            self::Counterfeit => 'Nghi hàng giả / hàng nhái',
            self::Misleading => 'Thông tin sai lệch',
            self::ProhibitedContent => 'Nội dung / sản phẩm bị cấm',
            self::Spam => 'Spam / quảng cáo rác',
            self::Harassment => 'Quấy rối',
            self::InappropriateContent => 'Nội dung không phù hợp',
            self::Other => 'Lý do khác',
        };
    }
}
