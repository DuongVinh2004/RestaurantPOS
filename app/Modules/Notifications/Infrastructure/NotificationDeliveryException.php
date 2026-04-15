<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure;

use RuntimeException;

class NotificationDeliveryException extends RuntimeException
{
    /**
     * @param  array<string,mixed>  $responsePayload
     */
    public function __construct(
        string $message,
        private readonly ?string $errorCode = null,
        private readonly array $responsePayload = [],
        private readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string,mixed>
     */
    public function responsePayload(): array
    {
        return $this->responsePayload;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
