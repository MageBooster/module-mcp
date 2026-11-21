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

namespace MageBooster\Mcp\Controller\Agent;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use MageBooster\Mcp\Model\Audit;
use MageBooster\Mcp\Model\Config;
use MageBooster\Mcp\Model\Role;
use MageBooster\Mcp\Model\Signature;
use MageBooster\Mcp\Model\ToolException;
use MageBooster\Mcp\Model\ToolPool;
use Psr\Log\LoggerInterface;

/**
 * POST /mbmcp/agent/call: the only entry point. Called by mcp.magebooster.com with a signed JSON body:
 * {"request_id","client","role","method":"tools/list"|"tools/call","tool","args"}
 * Answers {"ok":true,"result":...} or {"ok":false,"error":{"code","message"}}.
 */
class Call implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const MAX_BODY = 65536;

    public function __construct(
        private RequestInterface $request,
        private RawFactory $rawFactory,
        private Config $config,
        private Signature $signature,
        private ToolPool $toolPool,
        private Audit $audit,
        private LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $started = microtime(true);
        if (!$this->config->isEnabled()) {
            // Looks like any unknown URL: nothing tells a scanner the module is here.
            return $this->answer(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Not found']]);
        }
        $secret = $this->config->getSecret();
        if ($secret === '') {
            return $this->fail(503, 'not_configured', 'The store has no connection key yet.');
        }

        $type = (string)$this->request->getHeader('Content-Type');
        if (stripos($type, 'application/json') !== 0) {
            return $this->fail(415, 'bad_request', 'Send the body as application/json.');
        }
        $body = (string)$this->request->getContent();
        if (strlen($body) > self::MAX_BODY) {
            return $this->fail(413, 'too_large', 'Request body is too large.');
        }
        try {
            $this->signature->verify(
                $secret,
                (string)$this->request->getHeader('X-MB-Ts'),
                (string)$this->request->getHeader('X-MB-Nonce'),
                (string)$this->request->getHeader('X-MB-Sig'),
                $body
            );
        } catch (ToolException $e) {
            // Body is not trusted here: log only that someone was refused.
            $this->audit->write(Audit::DENIED, 'auth', '-', [], null, null, 0, $this->ms($started), $e->getErrorCode());
            return $this->fail($e->getHttpCode(), $e->getErrorCode(), $e->getMessage());
        }
        if (!$this->config->isLicensed()) {
            $this->audit->write(Audit::DENIED, 'license', '-', [], null, null, 0, $this->ms($started), 'license_required');
            return $this->fail(403, 'license_required', $this->config->licenseMessage());
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $this->fail(400, 'bad_request', 'Body must be a JSON object.');
        }
        $requestId = is_string($data['request_id'] ?? null) ? $data['request_id'] : null;
        $client = is_string($data['client'] ?? null) ? $data['client'] : null;
        $asked = is_string($data['role'] ?? null) ? $data['role'] : Role::READ;
        $method = (string)($data['method'] ?? '');
        $toolName = is_string($data['tool'] ?? null) ? $data['tool'] : '';
        $args = $data['args'] ?? [];
        if (!is_array($args)) {
            $args = [];
        }
        if (!Role::isValid($asked)) {
            return $this->fail(400, 'bad_request', 'Unknown role.');
        }
        $role = Role::lowest($asked, $this->config->getMaxRole());
        $note = $role !== $asked ? 'role lowered from ' . $asked : null;

        if ($method === 'tools/list') {
            $tools = $this->toolPool->describe($role);
            $this->audit->write(Audit::OK, 'tools/list', $role, [], $requestId, $client, count($tools), $this->ms($started), $note);
            return $this->answer(200, ['ok' => true, 'result' => ['role' => $role, 'tools' => $tools]]);
        }
        if ($method !== 'tools/call') {
            return $this->fail(400, 'bad_request', 'Unknown method.');
        }

        $tool = $this->toolPool->get($toolName);
        if ($tool === null) {
            $this->audit->write(Audit::ERROR, $toolName, $role, $args, $requestId, $client, 0, $this->ms($started), 'unknown_tool');
            return $this->fail(404, 'unknown_tool', 'No such tool.');
        }
        if (!Role::allows($role, $tool->getMinRole())) {
            $this->audit->write(Audit::DENIED, $toolName, $role, $args, $requestId, $client, 0, $this->ms($started), 'role_denied: needs ' . $tool->getMinRole() . ($note ? ', ' . $note : ''));
            return $this->fail(403, 'role_denied', sprintf('This tool needs the %s role; this connection has %s.', $tool->getMinRole(), $role));
        }

        try {
            $result = $tool->execute($args);
        } catch (ToolException $e) {
            $this->audit->write(Audit::ERROR, $toolName, $role, $args, $requestId, $client, 0, $this->ms($started), $e->getErrorCode() . ': ' . $e->getMessage());
            return $this->fail($e->getHttpCode(), $e->getErrorCode(), $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('MageBooster_Mcp ' . $toolName . ' failed: ' . $e->getMessage(), ['exception' => $e]);
            $this->audit->write(Audit::ERROR, $toolName, $role, $args, $requestId, $client, 0, $this->ms($started), 'internal: ' . get_class($e));
            return $this->fail(500, 'internal', 'The store could not run this tool. The error was logged.');
        }
        $items = isset($result['items']) && is_array($result['items']) ? count($result['items']) : 0;
        $this->audit->write(Audit::OK, $toolName, $role, $args, $requestId, $client, $items, $this->ms($started), $note);
        return $this->answer(200, ['ok' => true, 'result' => $result]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /** Signed requests replace the form key. */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function fail(int $http, string $code, string $message): Raw
    {
        return $this->answer($http, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
    }

    /** Raw result + json_encode so empty JSON objects stay {} (the Json result turns them into []). */
    private function answer(int $http, array $data): Raw
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $http = 500;
            $json = '{"ok":false,"error":{"code":"internal","message":"Result could not be encoded."}}';
        }
        $result = $this->rawFactory->create();
        $result->setHttpResponseCode($http);
        $result->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $result->setHeader('Cache-Control', 'no-store', true);
        $result->setHeader('X-Robots-Tag', 'noindex', true);
        return $result->setContents($json);
    }

    private function ms(float $started): int
    {
        return (int)round((microtime(true) - $started) * 1000);
    }
}
