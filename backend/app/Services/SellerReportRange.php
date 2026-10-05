<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class SellerReportRange
{
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to) {}

    public static function fromFilters(array $filters): self
    {
        $today = CarbonImmutable::instance(now())->setTimezone(self::TIMEZONE)->startOfDay();
        $to = isset($filters['to']) ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], self::TIMEZONE) : $today;
        $from = isset($filters['from']) ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], self::TIMEZONE) : $to->subDays(29);

        return new self($from, $to);
    }

    public function startUtc(): string
    {
        return $this->from->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    public function endUtc(): string
    {
        return $this->to->addDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'timezone' => self::TIMEZONE];
    }
}
