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

namespace MageBooster\Mcp\Controller\Connect;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use MageBooster\Mcp\Model\Config;
use MageBooster\Mcp\Model\Connect\Pending;

/**
 * GET /mbmcp/connect/approve?p&back&sig: mcp.magebooster.com sends the merchant here during
 * the Claude / ChatGPT sign-in. sig = hex(hmac_sha256(secret, p . "\n" . back)).
 * A valid request is parked and the merchant goes on to the admin page to approve it.
 */
class Approve implements HttpGetActionInterface
{
    public function __construct(
        private RequestInterface $request,
        private RawFactory $rawFactory,
        private RedirectFactory $redirectFactory,
        private Config $config,
        private Pending $pending,
        private BackendUrl $backendUrl
    ) {
    }

    public function execute()
    {
        $secret = $this->config->isEnabled() ? $this->config->getSecret() : '';
        if ($secret === '') {
            return $this->rawFactory->create()->setHttpResponseCode(404)->setContents('Not found');
        }
        $p = (string)$this->request->getParam('p');
        $back = (string)$this->request->getParam('back');
        $sig = strtolower((string)$this->request->getParam('sig'));
        if (!Pending::isValidId($p) || !str_starts_with($back, Pending::GATEWAY)
            || !hash_equals(hash_hmac('sha256', $p . "\n" . $back, $secret), $sig)) {
            return $this->rawFactory->create()->setHttpResponseCode(400)
                ->setContents('This connection link is not valid. Start again from Claude or ChatGPT.');
        }
        $this->pending->save($p, $back);
        // No secret key in the link: the admin page only shows the request, approving needs a form key.
        $this->backendUrl->turnOffSecretKey();
        $url = $this->backendUrl->getUrl('mbmcp/connect/approve', ['p' => $p]);
        return $this->redirectFactory->create()->setUrl($url);
    }
}
