<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Http\Middleware\AuditRequestMiddleware;
use App\Support\AuditEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class AuditSinkSanitizationTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    private string $auditLogPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('audit.hash_key', 'b14-test-audit-hmac-key');

        $this->auditLogPath = storage_path('logs/b14-audit-sink-'.bin2hex(random_bytes(6)).'.log');
        config()->set('logging.channels.audit', [
            'driver' => 'single',
            'path' => $this->auditLogPath,
            'level' => 'debug',
            'replace_placeholders' => true,
        ]);
        Log::forgetChannel('audit');
    }

    protected function tearDown(): void
    {
        Log::forgetChannel('audit');

        if (is_file($this->auditLogPath)) {
            unlink($this->auditLogPath);
        }

        parent::tearDown();
    }

    public function test_audit_event_sanitizes_once_before_file_and_database_sinks(): void
    {
        $rawSessionId = 'guest-session-b14-secret-001';
        $rawGuestName = 'B14 Raw Guest Name';
        $rawGuestPhone = '+84901234567';
        $rawGuestEmail = 'b14.raw.guest@example.test';
        $expectedSessionHash = 'hmac-sha256:'.hash_hmac('sha256', $rawSessionId, 'b14-test-audit-hmac-key');

        AuditEvent::info('customer.waiting_list.created', [
            'customer_session_id' => $rawSessionId,
            'guest_name' => $rawGuestName,
            'guest_phone' => $rawGuestPhone,
            'guest_email' => $rawGuestEmail,
            'nested' => [
                'access_token' => 'b14-raw-access-token',
                'message' => 'Contact '.$rawGuestEmail.' for follow-up.',
            ],
            '_audit' => [
                'action' => 'waiting_list.created',
                'entity_type' => 'waiting_list',
                'entity_id' => '97001',
                'summary' => [
                    'guest_name' => $rawGuestName,
                    'guest_phone' => $rawGuestPhone,
                    'guest_email' => $rawGuestEmail,
                    'customer_session_id' => $rawSessionId,
                ],
                'actor' => [
                    'type' => 'customer_session',
                    'key' => $rawSessionId,
                ],
            ],
        ]);

        $filePayload = (string) file_get_contents($this->auditLogPath);
        $row = DB::table('audit_logs')
            ->where('action', 'waiting_list.created')
            ->where('entity_id', '97001')
            ->orderByDesc('audit_id')
            ->first();

        self::assertNotNull($row);
        $databasePayload = json_encode($row, JSON_THROW_ON_ERROR);

        foreach ([$filePayload, $databasePayload] as $sinkPayload) {
            self::assertStringNotContainsString($rawSessionId, $sinkPayload);
            self::assertStringNotContainsString($rawGuestName, $sinkPayload);
            self::assertStringNotContainsString($rawGuestPhone, $sinkPayload);
            self::assertStringNotContainsString($rawGuestEmail, $sinkPayload);
            self::assertStringNotContainsString('b14-raw-access-token', $sinkPayload);
            self::assertStringContainsString($expectedSessionHash, $sinkPayload);
        }

        self::assertSame($expectedSessionHash, (string) $row->actor_key);
    }

    public function test_http_audit_sink_uses_the_same_sanitizer_for_query_body_and_ip(): void
    {
        $rawSessionId = 'http-session-b14-secret-002';
        $rawEmail = 'http.audit.raw@example.test';
        $rawPhone = '+84987654321';
        $rawToken = 'b14-http-bearer-token';
        $rawIp = '203.0.113.42';
        $expectedSessionHash = 'hmac-sha256:'.hash_hmac('sha256', $rawSessionId, 'b14-test-audit-hmac-key');

        $request = Request::create(
            '/api/v1/customer/reservations?email='.urlencode($rawEmail),
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'REMOTE_ADDR' => $rawIp,
            ],
            json_encode([
                'session_id' => $rawSessionId,
                'guest_email' => $rawEmail,
                'guest_phone' => $rawPhone,
                'authorization' => 'Bearer '.$rawToken,
            ], JSON_THROW_ON_ERROR),
        );

        app(AuditRequestMiddleware::class)->handle($request, static fn () => response()->json(['ok' => true]));

        $sinkPayload = (string) file_get_contents($this->auditLogPath);

        self::assertStringNotContainsString($rawSessionId, $sinkPayload);
        self::assertStringNotContainsString($rawEmail, $sinkPayload);
        self::assertStringNotContainsString($rawPhone, $sinkPayload);
        self::assertStringNotContainsString($rawToken, $sinkPayload);
        self::assertStringNotContainsString($rawIp, $sinkPayload);
        self::assertStringContainsString($expectedSessionHash, $sinkPayload);
    }
}
