<?php

namespace App\Support;

use App\Support\AuditTrail\LegacyAuditPayloadFactory;
use App\Support\AuditTrail\AuditTrailRecorder;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditEvent
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
        try {
            $structuredAudit = is_array($data['_audit'] ?? null) ? (array) $data['_audit'] : null;
            unset($data['_audit']);

            if ($structuredAudit === null) {
                $structuredAudit = app(LegacyAuditPayloadFactory::class)->make($event, $data);
            }

            $requestId = null;

            // request() có thể không tồn tại trong scheduler/cli
            if (app()->bound('request')) {
                $req = request();
                $requestId = $req?->attributes?->get('request_id');
            }

            $context = array_merge([
                'request_id' => $requestId ?: null,
            ], $data);

            Log::channel('audit')->log($level, $event, $context);

            if ($structuredAudit !== null) {
                app(AuditTrailRecorder::class)->record($event, $structuredAudit, $context, $level);
            }
        } catch (Throwable $e) {
            Log::warning('audit_event_failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
