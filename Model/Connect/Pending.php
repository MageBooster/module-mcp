<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Model\Connect;

use Magento\Framework\App\CacheInterface;

/**
 * A connection request from mcp.magebooster.com waiting for an admin to approve it.
 * Kept 10 minutes in cache, same as the gateway keeps its side.
 */
class Pending
{
    public const TTL = 600;
    public const GATEWAY = 'https://mcp.magebooster.com/';
    private const PREFIX = 'mbmcp_pending_';

    public function __construct(
        private CacheInterface $cache
    ) {
    }

    public static function isValidId(string $p): bool
    {
        return (bool)preg_match('/^[a-f0-9-]{36}$/', $p);
    }

    public function save(string $p, string $back): void
    {
        $this->cache->save((string)json_encode(['back' => $back, 'at' => time()]), $this->key($p), [], self::TTL);
    }

    /** @return array{back: string, at: int}|null */
    public function load(string $p): ?array
    {
        if (!self::isValidId($p)) {
            return null;
        }
        $data = json_decode((string)$this->cache->load($this->key($p)), true);
        return is_array($data) && is_string($data['back'] ?? null) ? $data : null;
    }

    public function remove(string $p): void
    {
        $this->cache->remove($this->key($p));
    }

    private function key(string $p): string
    {
        return self::PREFIX . hash('sha256', $p);
    }
}
