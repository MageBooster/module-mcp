<?php
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
