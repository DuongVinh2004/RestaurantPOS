<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CORS delivery contract tests.
 *
 * Verifies that the explicit config/cors.php policy is wired correctly
 * for the split frontend architecture (customer-web, staff-web).
 */
class CorsContractTest extends TestCase
{
    private const CUSTOMER_ALLOWED_ORIGIN = 'http://localhost:3000';

    private const CUSTOMER_LOOPBACK_ALLOWED_ORIGIN = 'http://127.0.0.1:3000';

    private const STAFF_ALLOWED_ORIGIN = 'http://localhost:5173';

    private const STAFF_LOOPBACK_ALLOWED_ORIGIN = 'http://127.0.0.1:5173';

    protected function setUp(): void
    {
        parent::setUp();

        // Mirror the split frontend architecture the repo supports officially.
        config(['cors.allowed_origins' => [
            self::CUSTOMER_ALLOWED_ORIGIN,
            self::CUSTOMER_LOOPBACK_ALLOWED_ORIGIN,
            self::STAFF_ALLOWED_ORIGIN,
            self::STAFF_LOOPBACK_ALLOWED_ORIGIN,
        ]]);
    }

    // ── Preflight (OPTIONS) ─────────────────────────────────

    public function test_preflight_returns_allowed_origin(): void
    {
        $response = $this->options('/api/v1/tables/available', [], [
            'HTTP_ORIGIN' => self::CUSTOMER_ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', self::CUSTOMER_ALLOWED_ORIGIN);
    }

    public function test_preflight_returns_allowed_origin_for_loopback_staff_dev_server(): void
    {
        $response = $this->options('/api/v1/auth/staff/login', [], [
            'HTTP_ORIGIN' => self::STAFF_LOOPBACK_ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', self::STAFF_LOOPBACK_ALLOWED_ORIGIN);
    }

    public function test_preflight_allows_custom_headers(): void
    {
        $customHeaders = [
            'X-Customer-Token',
            'X-Staff-Key',
            'X-Staff-CSRF',
            'X-Session-Id',
            'Idempotency-Key',
            'X-Idempotency-Key',
            'X-Request-Id',
        ];

        $response = $this->options('/api/v1/tables/available', [], [
            'HTTP_ORIGIN' => self::CUSTOMER_ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => implode(', ', $customHeaders),
        ]);

        $allowedHeaders = $response->headers->get('Access-Control-Allow-Headers', '');

        foreach ($customHeaders as $header) {
            $this->assertStringContainsStringIgnoringCase(
                $header,
                $allowedHeaders,
                "Expected '{$header}' in Access-Control-Allow-Headers"
            );
        }
    }

    public function test_preflight_allows_standard_methods(): void
    {
        $response = $this->options('/api/v1/tables/available', [], [
            'HTTP_ORIGIN' => self::CUSTOMER_ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'DELETE',
        ]);

        $allowedMethods = $response->headers->get('Access-Control-Allow-Methods', '');
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $this->assertStringContainsString($method, $allowedMethods);
        }
    }

    public function test_preflight_sets_max_age(): void
    {
        $response = $this->options('/api/v1/tables/available', [], [
            'HTTP_ORIGIN' => self::CUSTOMER_ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertEquals('7200', $response->headers->get('Access-Control-Max-Age'));
    }

    // ── Disallowed origin ───────────────────────────────────

    public function test_disallowed_origin_gets_no_allow_header(): void
    {
        $response = $this->options('/api/v1/tables/available', [], [
            'HTTP_ORIGIN' => 'https://evil.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertNull(
            $response->headers->get('Access-Control-Allow-Origin'),
            'Disallowed origin must not receive Access-Control-Allow-Origin'
        );
    }

    // ── Actual request ──────────────────────────────────────

    public function test_actual_request_exposes_request_id(): void
    {
        $response = $this->get('/api/v1/health', [
            'HTTP_ORIGIN' => self::CUSTOMER_ALLOWED_ORIGIN,
            'Accept' => 'application/json',
        ]);

        $exposed = $response->headers->get('Access-Control-Expose-Headers', '');
        $this->assertStringContainsStringIgnoringCase('X-Request-Id', $exposed);
    }

    // ── Credentials ─────────────────────────────────────────

    public function test_credentials_not_supported(): void
    {
        $response = $this->options('/api/v1/tables/available', [], [
            'HTTP_ORIGIN' => self::CUSTOMER_ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertNull(
            $response->headers->get('Access-Control-Allow-Credentials'),
            'supports_credentials must be false for header-based auth API'
        );
    }

    public function test_staff_browser_cookie_credentials_remain_exact_origin_only_when_enabled(): void
    {
        config(['cors.supports_credentials' => true]);

        $response = $this->options('/api/v1/auth/staff/refresh', [], [
            'HTTP_ORIGIN' => self::STAFF_ALLOWED_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type, X-Staff-CSRF',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', self::STAFF_ALLOWED_ORIGIN);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));

        $blocked = $this->options('/api/v1/auth/staff/refresh', [], [
            'HTTP_ORIGIN' => 'https://evil.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type, X-Staff-CSRF',
        ]);

        $this->assertNull($blocked->headers->get('Access-Control-Allow-Origin'));
    }
}
