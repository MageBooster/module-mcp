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

use MageBooster\License\Api\LicenseInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config
{
    public const LICENSE_CODE = 'mcp';

    private const PATH = 'magebooster_mcp/general/';

    private ?bool $licensed = null;

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private EncryptorInterface $encryptor,
        private LicenseInterface $license
    ) {
    }

    /**
     * Valid serial, or still inside the 30-day grace period of MageBooster_License. Without it the agent
     * endpoint and the connect page answer "license required"; settings and the audit log are kept.
     */
    public function isLicensed(): bool
    {
        if ($this->licensed === null) {
            try {
                $this->licensed = $this->license->isActive(self::LICENSE_CODE);
            } catch (\Throwable $e) {
                $this->licensed = false;
            }
        }
        return $this->licensed;
    }

    public function licenseMessage(): string
    {
        return (string)__('The MageBooster AI connector needs a license. Enter your serial number in Stores > Configuration > MageBooster > Licenses.');
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
