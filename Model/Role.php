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
