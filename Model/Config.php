<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config
{
    private const PATH = 'magebooster_mcp/general/';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enabled');
    }

    public function getMaxRole(): string
    {
        $role = (string)$this->scopeConfig->getValue(self::PATH . 'max_role');
        return Role::isValid($role) ? $role : Role::READ;
    }

    /** Connection key; saved encrypted by the admin field and by config:set. */
    public function getSecret(): string
    {
        $value = trim((string)$this->scopeConfig->getValue(self::PATH . 'secret'));
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d+:\d+:/', $value)) {
            $value = trim((string)$this->encryptor->decrypt($value));
        }
        return $value;
    }
}
