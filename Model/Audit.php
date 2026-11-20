<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Model;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Writes one mbmcp_audit row per call. Args are stored with personal data masked.
 * A failing audit write never breaks the call; it goes to the Magento log instead.
 */
class Audit
{
    public const OK = 'ok';
    public const DENIED = 'denied';
    public const ERROR = 'error';

    public function __construct(
        private ResourceConnection $resource,
        private Masker $masker,
        private LoggerInterface $logger
    ) {
    }

    public function write(
        string $status,
        string $tool,
        string $role,
        array $args = [],
        ?string $requestId = null,
        ?string $client = null,
        int $items = 0,
        int $ms = 0,
        ?string $message = null
    ): void {
        try {
            $json = $args ? (string)json_encode($this->masker->args($args), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $this->resource->getConnection()->insert($this->resource->getTableName('mbmcp_audit'), [
                'request_id' => $this->clip($requestId, 64),
                'client' => $this->clip($client, 64),
                'tool' => (string)$this->clip($tool !== '' ? $tool : '-', 64),
                'role' => (string)$this->clip($role !== '' ? $role : '-', 16),
                'args' => $json !== null ? mb_substr($json, 0, 4000) : null,
                'status' => $status,
                'items' => max(0, $items),
                'ms' => max(0, $ms),
                'message' => $this->clip($message, 255),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('MageBooster_Mcp audit write failed: ' . $e->getMessage());
        }
    }

    private function clip(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = preg_replace('/[^\P{C}]/u', '', $value) ?? '';
        return mb_substr($value, 0, $length);
    }
}
