<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Model;

/**
 * Access levels, lowest first. The effective role of a call is the lower of what the
 * connection asks for and what the merchant allows in admin (max_role).
 */
class Role
{
    public const READ = 'read';
    public const EDITOR = 'editor';
    public const FULL = 'full';

    public const ORDER = [self::READ => 1, self::EDITOR => 2, self::FULL => 3];

    public static function isValid(string $role): bool
    {
        return isset(self::ORDER[$role]);
    }

    /** Unknown roles count as nothing, so they never pass a check. */
    public static function rank(string $role): int
    {
        return self::ORDER[$role] ?? 0;
    }

    public static function lowest(string $a, string $b): string
    {
        return self::rank($a) <= self::rank($b) ? $a : $b;
    }

    public static function allows(string $have, string $need): bool
    {
        return self::rank($have) > 0 && self::rank($have) >= self::rank($need);
    }
}
