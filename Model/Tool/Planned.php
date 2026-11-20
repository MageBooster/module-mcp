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

use MageBooster\Mcp\Api\ToolInterface;
use MageBooster\Mcp\Model\Role;
use MageBooster\Mcp\Model\ToolException;

/**
 * Placeholder for a write tool that is announced but not built in this version.
 * It keeps the role check real (a read connection is denied before anything else)
 * and is hidden from tools/list.
 */
class Planned implements ToolInterface
{
    public function __construct(
        private string $name,
        private string $minRole = Role::EDITOR,
        private string $description = 'Not available in this version.'
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getInputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function getMinRole(): string
    {
        return $this->minRole;
    }

    public function execute(array $args): array
    {
        throw new ToolException('This tool is not available in this version of the module.', 'not_available', 501);
    }
}
