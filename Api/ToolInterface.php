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

namespace MageBooster\Mcp\Api;

/**
 * One tool the AI can call. Tools are registered in etc/di.xml (MageBooster\Mcp\Model\ToolPool).
 */
interface ToolInterface
{
    /** snake_case name used in tools/call */
    public function getName(): string;

    public function getDescription(): string;

    /** JSON Schema (as a PHP array) of the args object */
    public function getInputSchema(): array;

    /** Lowest role that may call it: read, editor or full */
    public function getMinRole(): string;

    /**
     * @param array $args already decoded JSON object
     * @return array JSON-ready result; a list under 'items' is counted in the audit log
     * @throws \MageBooster\Mcp\Model\ToolException for errors the AI should see
     */
    public function execute(array $args): array;
}
