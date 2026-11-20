<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Model;

use Magento\Framework\App\CacheInterface;

/**
 * Checks a signed request from mcp.magebooster.com.
 * X-MB-Sig = hex(hmac_sha256(secret, ts . "\n" . nonce . "\n" . rawBody))
 */
class Signature
{
    public const MAX_SKEW = 300;
    public const NONCE_TTL = 600;
    private const NONCE_PREFIX = 'mbmcp_nonce_';

    public function __construct(
        private CacheInterface $cache
    ) {
    }

    public static function sign(string $secret, string $ts, string $nonce, string $body): string
    {
        return hash_hmac('sha256', $ts . "\n" . $nonce . "\n" . $body, $secret);
    }

    /**
     * @throws ToolException with code unauthorized / stale_request / nonce_reused / bad_signature
     */
    public function verify(string $secret, string $ts, string $nonce, string $sig, string $body, ?int $now = null): void
    {
        $now ??= time();
        if (!preg_match('/^\d{1,12}$/', $ts) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce)
            || !preg_match('/^[a-f0-9]{64}$/i', $sig)) {
            throw new ToolException('Missing or malformed signature headers.', 'unauthorized', 401);
        }
        if (abs($now - (int)$ts) > self::MAX_SKEW) {
            throw new ToolException('Request timestamp is too old or in the future.', 'stale_request', 401);
        }
        if (!hash_equals(self::sign($secret, $ts, $nonce, $body), strtolower($sig))) {
            throw new ToolException('Bad signature.', 'bad_signature', 401);
        }
        // Only a correctly signed request can use up a nonce.
        $key = self::NONCE_PREFIX . hash('sha256', $nonce);
        if ($this->cache->load($key) !== false) {
            throw new ToolException('Nonce already used.', 'nonce_reused', 401);
        }
        $this->cache->save('1', $key, [], self::NONCE_TTL);
    }
}
