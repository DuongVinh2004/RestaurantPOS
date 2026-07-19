<?php

declare(strict_types=1);

namespace App\Support\AuditTrail;

use RuntimeException;
use Throwable;

final class CriticalAuditPersistenceException extends RuntimeException
{
    public function __construct(
        private readonly string $auditEventName,
        string $reasonCode = 'critical_audit_persistence_failed',
        ?Throwable $previous = null,
    ) {
        parent::__construct($reasonCode.' for event '.$auditEventName, 0, $previous);
    }

    public function eventName(): string
    {
        return $this->auditEventName;
    }
}
