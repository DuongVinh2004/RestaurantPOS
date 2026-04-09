<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Services\NotificationOutboxService;
use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use App\Services\RuntimeSettingService;
use App\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffAuditTrailHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();

        $this->app->instance(NotificationOutboxService::class, $this->mockNotificationOutbox());
        $this->app->instance(ReservationLockService::class, $this->mockReservationLocks());
        $this->app->instance(RuntimeSettingService::class, $this->mockRuntimeSettings());
        $this->app->instance(RestaurantTableStateService::class, new RestaurantTableStateService());
        $this->app->instance(TableTimeConflictService::class, new TableTimeConflictService());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_staff_can_filter_audit_trail_by_reservation_action_and_actor(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('audit-trail-checkin', $this->staffAuthHeaders($staffId, 'staff-audit-trail'));

        $tableId = $this->createRestaurantTable(['status' => 'Available']);
        $start = $this->nowUtc()->copy()->addMinutes(5);
        $reservationId = $this->createReservation([
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'status' => 'Confirmed',
        ]);
        $this->attachReservationTable($reservationId, $tableId);

        $this->withHeaders($headers)->postJson("/api/v1/staff/reservations/{$reservationId}/check-in", [
            'table_ids' => [$tableId],
            'checked_in_at' => $start->toIso8601String(),
            'row_version' => 1,
        ])->assertOk();

        $response = $this->getJson(
            '/api/v1/staff/audit-trail?reservation_id='.$reservationId.'&action=reservation.checked_in&actor_user_id='.$staffId,
            $headers
        );

        $response->assertOk()
            ->assertJsonPath('meta.action', 'staff_audit_trail_index')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.action', 'reservation.checked_in')
            ->assertJsonPath('data.0.primary_subject.type', 'reservation')
            ->assertJsonPath('data.0.primary_subject.id', (string) $reservationId)
            ->assertJsonPath('data.0.actor.user_id', $staffId)
            ->assertJsonPath('data.0.actor.type', 'staff_user')
            ->assertJsonPath('data.0.summary.table_count', 1);

        $subjects = collect($response->json('data.0.subjects', []));
        $tableSubject = $subjects->first(fn (array $subject): bool => ($subject['type'] ?? null) === 'restaurant_table' && ($subject['id'] ?? null) === (string) $tableId);

        self::assertIsArray($tableSubject);
        self::assertSame('table', $tableSubject['role'] ?? null);
    }
}
