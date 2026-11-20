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

use MageBooster\Mcp\Api\ToolInterface;
use MageBooster\Mcp\Model\Tool\Planned;

class ToolPool
{
    /** @var ToolInterface[] */
    private array $tools = [];

    /**
     * @param ToolInterface[] $tools
     */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            if (!$tool instanceof ToolInterface) {
                throw new \InvalidArgumentException(get_class($tool) . ' must implement ToolInterface');
            }
            $this->tools[$tool->getName()] = $tool;
        }
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /** Tools the given role may call, as MCP tool descriptors. Planned (not yet built) tools are left out. */
    public function describe(string $role): array
    {
        $list = [];
        foreach ($this->tools as $tool) {
            if ($tool instanceof Planned || !Role::allows($role, $tool->getMinRole())) {
                continue;
            }
            $list[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
                'minRole' => $tool->getMinRole(),
            ];
        }
        return $list;
    }
}
