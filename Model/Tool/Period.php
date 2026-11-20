<?php
/*
 * Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.
 * Commercial licence, see LICENSE.txt. https://magebooster.com
 * Licensed per serial number for one production installation (one store domain); staging and
 * development copies of that store are allowed. Serial numbers and downloads are tied to the customer.
 * You may modify this code for your own licensed store; keep this notice.
 * Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing it publicly,
 * removing licence checks or notices, sharing serial numbers. Unauthorised use: the serial number is
 * revoked without refund, updates and support stop, damages and legal action may follow.
 * Estonian law applies; court: Harju County Court (Harju Maakohus).
 */
declare(strict_types=1);

namespace MageBooster\Mcp\Model\Tool;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use MageBooster\Mcp\Model\ToolException;

/**
 * Turns from/to dates in the store timezone into a UTC range for created_at filters.
 */
class Period
{
    public function __construct(
        private TimezoneInterface $timezone
    ) {
    }

    public function zone(): \DateTimeZone
    {
        return new \DateTimeZone($this->timezone->getConfigTimezone());
    }

    /**
     * @return array{from:\DateTimeImmutable,to:\DateTimeImmutable,utc_from:string,utc_to:string,days:int}
     */
    public function resolve(array $args, int $defaultDays, int $maxDays): array
    {
        $zone = $this->zone();
        $today = new \DateTimeImmutable('today', $zone);
        $to = Args::date($args, 'to');
        $from = Args::date($args, 'from');
        $to = $to ? new \DateTimeImmutable($to->format('Y-m-d'), $zone) : $today;
        $from = $from ? new \DateTimeImmutable($from->format('Y-m-d'), $zone) : $to->modify('-' . ($defaultDays - 1) . ' days');
        if ($from > $to) {
            throw new ToolException('from must not be after to.');
        }
        $days = (int)$from->diff($to)->days + 1;
        if ($days > $maxDays) {
            throw new ToolException(sprintf('The period can be at most %d days.', $maxDays));
        }
        $utc = new \DateTimeZone('UTC');
        return [
            'from' => $from,
            'to' => $to,
            'utc_from' => $from->setTimezone($utc)->format('Y-m-d H:i:s'),
            'utc_to' => $to->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
            'days' => $days,
        ];
    }

    public function local(?string $utc): ?string
    {
        if (!$utc) {
            return null;
        }
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone($this->zone())->format('Y-m-d H:i:s');
    }
}
