<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class MetricsAuthorizationCoverageTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
    }

    #[Group('booking-ops')]
    public function test_metrics_endpoint_requires_ops_capability_for_non_admin_staff(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);

        $response = $this->withHeaders($this->staffAuthHeaders($staffId, 'metrics-staff'))
            ->get('/api/v1/metrics');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('required_capability', 'ops.view');
    }

    #[Group('booking-ops')]
    public function test_metrics_endpoint_allows_admin_actor_with_global_capability_scope(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);

        $response = $this->withHeaders($this->staffAuthHeaders($adminId, 'metrics-admin'))
            ->get('/api/v1/metrics');

        $response->assertOk();
        self::assertStringContainsString('text/plain', (string) $response->headers->get('content-type'));
        self::assertStringContainsString('# HELP http_requests_total', $response->getContent());
    }
}
