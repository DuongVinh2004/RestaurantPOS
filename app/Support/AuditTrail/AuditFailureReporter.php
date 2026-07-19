<?php

declare(strict_types=1);

namespace App\Support\AuditTrail;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AuditFailureReporter
{
    public function report(string $eventName, ?string $action, bool $critical, Throwable $exception): void
    {
        try {
            $requestId = app()->bound('request')
                ? request()->attributes->get('request_id')
                : null;

            Log::channel((string) config('audit.failure_alert_channel', 'stderr'))->log(
                $critical ? 'critical' : 'warning',
                $critical ? 'critical_audit_persistence_failed' : 'best_effort_audit_failed',
                [
                    'event' => $eventName,
                    'action' => $action,
                    'exception_type' => $exception::class,
                    'request_id' => is_scalar($requestId) ? (string) $requestId : null,
                    'transaction_level' => DB::transactionLevel(),
                ],
            );
        } catch (Throwable) {
            // A reporting sink must never hide the original audit persistence failure.
        }
    }
}
