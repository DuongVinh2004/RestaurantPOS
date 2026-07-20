<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\AuditTrail\AuditDurabilityPolicy;
use App\Support\AuditTrail\AuditFailureReporter;
use App\Support\AuditTrail\AuditPayloadSanitizer;
use App\Support\AuditTrail\AuditTrailRecorder;
use App\Support\AuditTrail\CriticalAuditPersistenceException;
use App\Support\AuditTrail\LegacyAuditPayloadFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AuditEvent
{
    public static function info(string $event, array $data = []): void
    {
        self::log('info', $event, $data);
    }

    public static function warning(string $event, array $data = []): void
    {
        self::log('warning', $event, $data);
    }

    public static function error(string $event, array $data = []): void
    {
        self::log('error', $event, $data);
    }

    public static function log(string $level, string $event, array $data = []): void
    {
        $policy = app(AuditDurabilityPolicy::class);
        $critical = $policy->isCritical($event, null);
        $action = null;

        try {
            $structuredAudit = is_array($data['_audit'] ?? null) ? (array) $data['_audit'] : null;
            unset($data['_audit']);

            if ($structuredAudit === null) {
                $structuredAudit = app(LegacyAuditPayloadFactory::class)->make($event, $data);
            }

            $critical = $critical || $policy->isCritical($event, $structuredAudit);
            $action = is_scalar($structuredAudit['action'] ?? null)
                ? trim((string) $structuredAudit['action'])
                : null;

            if ($critical && DB::transactionLevel() < 1) {
                throw new CriticalAuditPersistenceException($event, 'critical_audit_requires_active_transaction');
            }

            $requestContext = self::requestContext();
            $envelope = app(AuditPayloadSanitizer::class)->sanitize([
                'structured' => $structuredAudit,
                'log_context' => array_merge([
                    'request_id' => $requestContext['request_id'],
                ], $data),
                'request_context' => $requestContext,
            ]);

            $sanitizedStructured = is_array($envelope['structured'] ?? null)
                ? (array) $envelope['structured']
                : null;
            $sanitizedLogContext = is_array($envelope['log_context'] ?? null)
                ? (array) $envelope['log_context']
                : [];
            $sanitizedRequestContext = is_array($envelope['request_context'] ?? null)
                ? (array) $envelope['request_context']
                : [];

            $recorded = $sanitizedStructured !== null
                && app(AuditTrailRecorder::class)->record(
                    $event,
                    $sanitizedStructured,
                    $sanitizedLogContext,
                    $level,
                    $sanitizedRequestContext,
                );

            if ($critical && ! $recorded) {
                throw new CriticalAuditPersistenceException($event, 'critical_audit_evidence_missing');
            }

            Log::channel('audit')->log($level, $event, $sanitizedLogContext);
        } catch (Throwable $exception) {
            app(AuditFailureReporter::class)->report($event, $action, $critical, $exception);

            if ($critical) {
                throw $exception instanceof CriticalAuditPersistenceException
                    ? $exception
                    : new CriticalAuditPersistenceException($event, previous: $exception);
            }
        }
    }

    /**
     * @return array{request_id:?string,method:?string,path:?string,ip:?string,user_agent:?string}
     */
    private static function requestContext(): array
    {
        if (! app()->bound('request')) {
            return [
                'request_id' => null,
                'method' => null,
                'path' => null,
                'ip' => null,
                'user_agent' => null,
            ];
        }

        $request = request();

        return [
            'request_id' => self::stringOrNull($request->attributes->get('request_id')),
            'method' => self::stringOrNull($request->getMethod()),
            'path' => self::stringOrNull($request->path()),
            'ip' => self::stringOrNull($request->ip()),
            'user_agent' => self::stringOrNull($request->userAgent()),
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
