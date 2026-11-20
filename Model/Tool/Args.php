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

use MageBooster\Mcp\Model\ToolException;

/**
 * Small, strict readers for tool arguments.
 */
class Args
{
    public static function int(array $args, string $key, int $default, int $min, int $max): int
    {
        if (!isset($args[$key]) || $args[$key] === '') {
            return $default;
        }
        if (!is_numeric($args[$key])) {
            throw new ToolException(sprintf('%s must be a number.', $key));
        }
        return max($min, min($max, (int)$args[$key]));
    }

    public static function str(array $args, string $key, int $maxLength = 255): ?string
    {
        if (!isset($args[$key]) || $args[$key] === '') {
            return null;
        }
        if (!is_scalar($args[$key])) {
            throw new ToolException(sprintf('%s must be a string.', $key));
        }
        return mb_substr(trim((string)$args[$key]), 0, $maxLength);
    }

    public static function oneOf(array $args, string $key, array $allowed, ?string $default = null): ?string
    {
        $value = self::str($args, $key, 64);
        if ($value === null) {
            return $default;
        }
        if (!in_array($value, $allowed, true)) {
            throw new ToolException(sprintf('%s must be one of: %s.', $key, implode(', ', $allowed)));
        }
        return $value;
    }

    /** YYYY-MM-DD (store timezone) */
    public static function date(array $args, string $key): ?\DateTimeImmutable
    {
        $value = self::str($args, $key, 10);
        if ($value === null) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new ToolException(sprintf('%s must be a date like 2026-10-28.', $key));
        }
        return $date;
    }

    public static function paging(int $page, int $pageSize, int $total): array
    {
        return ['page' => $page, 'page_size' => $pageSize, 'total' => $total, 'pages' => (int)ceil($total / max(1, $pageSize))];
    }
}
