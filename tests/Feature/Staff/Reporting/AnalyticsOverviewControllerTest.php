<?php

namespace Tests\Feature\Staff\Reporting;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AnalyticsOverviewControllerTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_staff_can_view_analytics_overview(): void
    {
        $userId = $this->createUser(['role_name' => 'Admin']);

        $response = $this->withHeaders($this->staffAuthHeaders($userId))
            ->getJson('/api/v1/staff/reporting/analytics-overview?date_from=2026-05-01&date_to=2026-05-31');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'overview' => [
                    'total_reservations',
                    'cancelled_count',
                    'no_show_count',
                    'total_revenue',
                ],
                'payment_summary',
                'revenue_heatmap',
                'top_items',
            ],
        ]);
    }

    public function test_customer_cannot_view_analytics(): void
    {
        $response = $this->getJson('/api/v1/staff/reporting/analytics-overview');
        // Without staff key, it should reject
        $response->assertStatus(401);
    }

    public function test_analytics_rejects_large_date_range(): void
    {
        $userId = $this->createUser(['role_name' => 'Admin']);

        $response = $this->withHeaders($this->staffAuthHeaders($userId))
            ->getJson('/api/v1/staff/reporting/analytics-overview?date_from=2026-01-01&date_to=2026-05-31');

        $this->assertNotEquals(200, $response->getStatusCode());
    }
}
