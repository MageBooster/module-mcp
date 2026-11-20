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
 * Masks personal data. Used for tool results (customers, addresses) and for the audit log.
 */
class Masker
{
    /** Arg keys whose values are personal data */
    private const PII_KEYS = [
        'email', 'customer_email', 'phone', 'telephone', 'name', 'firstname', 'lastname', 'customer_name',
        'customer_firstname', 'customer_lastname', 'street', 'address', 'postcode', 'city', 'company', 'vat_id',
    ];

    public function email(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return $email;
        }
        if (!str_contains($email, '@')) {
            return $this->text($email);
        }
        [$local, $domain] = explode('@', $email, 2);
        $dot = strrpos($domain, '.');
        $host = $dot === false ? $domain : substr($domain, 0, $dot);
        $tld = $dot === false ? '' : substr($domain, $dot);
        return mb_substr($local, 0, 1) . '***@' . mb_substr($host, 0, 1) . '***' . $tld;
    }

    /** "Jane Doe" -> "J*** D***" */
    public function name(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return $name;
        }
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        return implode(' ', array_map(static fn($p) => mb_substr($p, 0, 1) . '***', $parts));
    }

    /** keeps the last two digits */
    public function phone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return $phone;
        }
        $digits = preg_replace('/\D/', '', $phone);
        return '***' . substr((string)$digits, -2);
    }

    public function text(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        return mb_substr($value, 0, 1) . '***';
    }

    /** Masks PII keys and any e-mail address found in string values, at any depth. */
    public function args(array $args): array
    {
        $out = [];
        foreach ($args as $key => $value) {
            $lower = is_string($key) ? strtolower($key) : $key;
            if (is_array($value)) {
                $out[$key] = $this->args($value);
            } elseif (is_string($value) && in_array($lower, self::PII_KEYS, true)) {
                $out[$key] = match (true) {
                    str_contains((string)$lower, 'email') => $this->email($value),
                    in_array($lower, ['phone', 'telephone'], true) => $this->phone($value),
                    default => $this->text($value),
                };
            } elseif (is_string($value)) {
                $out[$key] = preg_replace_callback(
                    '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
                    fn($m) => (string)$this->email($m[0]),
                    $value
                );
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
