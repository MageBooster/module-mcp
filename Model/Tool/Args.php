<?php
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
