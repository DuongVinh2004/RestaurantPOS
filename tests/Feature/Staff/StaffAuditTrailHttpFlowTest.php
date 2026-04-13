<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Services\NotificationOutboxService;
use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use App\Services\RuntimeSettingService;
use App\Services\TableTimeConflictService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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

    public function test_staff_can_filter_audit_trail_by_branch_request_and_search(): void
    {
        $staffId = $this->createUser([
            'role_name' => 'Staff',
            'full_name' => 'Branch Investigator',
        ]);
        $headers = $this->withIdempotencyKey('audit-trail-branch-search', $this->staffAuthHeaders($staffId, 'staff-audit-trail'));

        $branchTwoId = $this->createBranch([
            'branch_code' => 'BR2',
            'branch_name' => 'Branch Two',
        ]);
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchTwoId,
            'status' => 'Open',
        ]);

        $branchOneReservationId = $this->createReservation([
            'branch_id' => 1,
        ]);
        $branchTwoReservationId = $this->createReservation([
            'branch_id' => $branchTwoId,
        ]);
        $branchTwoPaymentId = $this->createPayment([
            'reservation_id' => $branchTwoReservationId,
            'branch_id' => $branchTwoId,
        ]);

        $this->recordAuditLog([
            'actor_user_id' => $staffId,
            'actor_type' => 'staff_user',
            'actor_key' => 'staff_api_key:branch-investigator',
            'entity_type' => 'reservation',
            'entity_id' => (string) $branchOneReservationId,
            'action' => 'reservation.checked_in',
            'request_id' => 'req-branch-one',
            'summary_json' => [
                'guest_count' => 2,
            ],
            'meta_json' => [
                'request' => [
                    'branch_id' => 1,
                    'method' => 'POST',
                    'path' => "/api/v1/staff/reservations/{$branchOneReservationId}/check-in",
                ],
            ],
        ]);

        $auditId = $this->recordAuditLog([
            'actor_user_id' => $staffId,
            'actor_type' => 'staff_user',
            'actor_key' => 'staff_api_key:branch-investigator',
            'entity_type' => 'payment',
            'entity_id' => (string) $branchTwoPaymentId,
            'action' => 'payment.refunded',
            'request_id' => 'req-branch-two',
            'summary_json' => [
                'refund_amount' => '100000.00',
                'currency' => 'VND',
            ],
            'meta_json' => [
                'request' => [
                    'branch_id' => $branchTwoId,
                    'method' => 'POST',
                    'path' => "/api/v1/staff/reservations/{$branchTwoReservationId}/refund",
                ],
            ],
        ], [
            [
                'type' => 'reservation',
                'id' => (string) $branchTwoReservationId,
                'role' => 'reservation',
            ],
        ]);

        $response = $this->getJson(
            "/api/v1/staff/audit-trail?branch_id={$branchTwoId}&request_id=req-branch-two&q=refund",
            $headers
        );

        $response->assertOk()
            ->assertJsonPath('meta.action', 'staff_audit_trail_index')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.audit_id', $auditId)
            ->assertJsonPath('data.0.action', 'payment.refunded')
            ->assertJsonPath('data.0.primary_subject.type', 'payment')
            ->assertJsonPath('data.0.primary_subject.id', (string) $branchTwoPaymentId)
            ->assertJsonPath('data.0.actor.user.full_name', 'Branch Investigator')
            ->assertJsonPath('data.0.request.request_id', 'req-branch-two')
            ->assertJsonPath('data.0.request.branch_id', $branchTwoId);
    }

    public function test_audit_trail_defaults_to_actor_operational_branch_scope_and_rejects_inaccessible_branch_filters(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('audit-trail-scope-default', $this->staffAuthHeaders($staffId, 'staff-audit-trail'));

        $branchTwoId = $this->createBranch([
            'branch_code' => 'BR2SCOPE',
            'branch_name' => 'Branch Scope Two',
        ]);

        $branchOneReservationId = $this->createReservation([
            'branch_id' => 1,
        ]);
        $branchTwoReservationId = $this->createReservation([
            'branch_id' => $branchTwoId,
        ]);

        $branchOneAuditId = $this->recordAuditLog([
            'actor_user_id' => $staffId,
            'actor_type' => 'staff_user',
            'entity_type' => 'reservation',
            'entity_id' => (string) $branchOneReservationId,
            'action' => 'reservation.updated',
            'request_id' => 'req-branch-scope-one',
            'meta_json' => [
                'request' => [
                    'branch_id' => 1,
                    'method' => 'PATCH',
                    'path' => "/api/v1/staff/reservations/{$branchOneReservationId}",
                ],
            ],
        ]);

        $this->recordAuditLog([
            'actor_user_id' => $staffId,
            'actor_type' => 'staff_user',
            'entity_type' => 'reservation',
            'entity_id' => (string) $branchTwoReservationId,
            'action' => 'reservation.updated',
            'request_id' => 'req-branch-scope-two',
            'meta_json' => [
                'request' => [
                    'branch_id' => $branchTwoId,
                    'method' => 'PATCH',
                    'path' => "/api/v1/staff/reservations/{$branchTwoReservationId}",
                ],
            ],
        ]);

        $this->getJson('/api/v1/staff/audit-trail?action=reservation.updated', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.audit_id', $branchOneAuditId)
            ->assertJsonPath('data.0.request.branch_id', 1);

        $this->getJson("/api/v1/staff/audit-trail?branch_id={$branchTwoId}", $headers)
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_staff_branch_filter_matches_branch_owned_entities_without_embedded_branch_meta(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $headers = $this->withIdempotencyKey('audit-trail-branch-owned', $this->staffAuthHeaders($staffId, 'staff-audit-trail'));

        $branchTwoId = $this->createBranch([
            'branch_code' => 'BR3',
            'branch_name' => 'Branch Three',
        ]);
        $this->createCashierShift([
            'cashier_user_id' => $staffId,
            'branch_id' => $branchTwoId,
            'status' => 'Open',
        ]);

        $branchOneReservationId = $this->createReservation([
            'branch_id' => 1,
        ]);
        $branchTwoReservationId = $this->createReservation([
            'branch_id' => $branchTwoId,
        ]);
        $branchOnePaymentId = $this->createPayment([
            'reservation_id' => $branchOneReservationId,
            'branch_id' => 1,
        ]);
        $branchTwoPaymentId = $this->createPayment([
            'reservation_id' => $branchTwoReservationId,
            'branch_id' => $branchTwoId,
        ]);

        $this->recordAuditLog([
            'actor_user_id' => $staffId,
            'actor_type' => 'staff_user',
            'entity_type' => 'payment',
            'entity_id' => (string) $branchOnePaymentId,
            'action' => 'payment.refunded',
            'request_id' => 'req-payment-branch-one',
            'meta_json' => [
                'request' => [
                    'method' => 'POST',
                    'path' => "/api/v1/staff/payments/{$branchOnePaymentId}/refund",
                ],
            ],
        ]);

        $auditId = $this->recordAuditLog([
            'actor_user_id' => $staffId,
            'actor_type' => 'staff_user',
            'entity_type' => 'payment',
            'entity_id' => (string) $branchTwoPaymentId,
            'action' => 'payment.refunded',
            'request_id' => 'req-payment-branch-two',
            'meta_json' => [
                'request' => [
                    'method' => 'POST',
                    'path' => "/api/v1/staff/payments/{$branchTwoPaymentId}/refund",
                ],
            ],
        ]);

        $response = $this->getJson(
            "/api/v1/staff/audit-trail?branch_id={$branchTwoId}&action=payment.refunded",
            $headers
        );

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.audit_id', $auditId)
            ->assertJsonPath('data.0.primary_subject.id', (string) $branchTwoPaymentId)
            ->assertJsonPath('data.0.request.branch_id', null);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  list<array{type:string,id:string,role?:string|null}>  $subjects
     */
    private function recordAuditLog(array $overrides = [], array $subjects = []): int
    {
        $payload = array_merge([
            'actor_user_id' => null,
            'actor_type' => 'system',
            'actor_key' => null,
            'entity_type' => 'reservation',
            'entity_id' => '1',
            'action' => 'reservation.updated',
            'before_json' => null,
            'after_json' => null,
            'summary_json' => null,
            'meta_json' => null,
            'request_id' => null,
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => $this->nowUtc(),
        ], $overrides);

        foreach (['before_json', 'after_json', 'summary_json', 'meta_json'] as $jsonColumn) {
            if (is_array($payload[$jsonColumn])) {
                $payload[$jsonColumn] = json_encode($payload[$jsonColumn], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $auditId = (int) DB::table('audit_logs')->insertGetId($payload);

        foreach ($subjects as $subject) {
            DB::table('audit_log_subjects')->insert([
                'audit_id' => $auditId,
                'subject_type' => $subject['type'],
                'subject_id' => $subject['id'],
                'subject_role' => $subject['role'] ?? null,
            ]);
        }

        return $auditId;
    }
}
