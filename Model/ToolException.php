<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Model;

/**
 * Error whose message is safe to send back to the AI (bad arguments, not found, ...).
 * Anything else thrown by a tool is answered with a generic message.
 */
class ToolException extends \RuntimeException
{
    public function __construct(
        string $message,
        private string $errorCode = 'bad_request',
        private int $httpCode = 400
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
