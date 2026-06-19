<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class StaffOrderAuditTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');
        Cache::store('redis')->getStore()->flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_staff_create_on_spot_order_writes_structured_audit_subjects(): void
    {
        [$staffId, $tableId, $reservationId, $menuItemId] = $this->createAuditableOrderContext('55000');

        $order = $this->makeTableOrderService()->createOnSpotOrder(
            tableId: $tableId,
            reservationId: $reservationId,
            items: [['menu_item_id' => $menuItemId, 'qty' => 2, 'note' => 'audit line']],
            staffUserId: $staffId,
            idempotencyKey: 'order-audit-create-secret',
            notes: 'audit order',
            expectedRowVersion: 1,
        );

        $audit = $this->latestAudit('order.created', (int) $order->order_id);
        $subjects = $this->auditSubjects((int) $audit->audit_id);

        self::assertTrue($this->hasSubject($subjects, 'reservation_order', (int) $order->order_id, 'primary'));
        self::assertTrue($this->hasSubject($subjects, 'reservation', $reservationId, 'reservation'));
        self::assertTrue($this->hasSubject($subjects, 'branch', 1, 'branch'));
        self::assertTrue($this->hasSubject($subjects, 'restaurant_table', $tableId, 'table'));
        self::assertTrue($this->hasSubject($subjects, 'menu_item', $menuItemId, 'menu_item'));
        self::assertTrue(
            $subjects->contains(fn ($subject): bool => $subject->subject_type === 'reservation_order_item'
                && $subject->subject_role === 'order_item')
        );
    }

    public function test_staff_add_items_writes_structured_audit_subjects(): void
    {
        [$staffId, $tableId, $reservationId, $menuItemId] = $this->createAuditableOrderContext('42000');
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);

        $order = $this->makeTableOrderService()->addItems(
            orderId: $orderId,
            items: [['menu_item_id' => $menuItemId, 'qty' => 3, 'note' => 'audit add']],
            staffUserId: $staffId,
            idempotencyKey: 'order-audit-add-secret',
            expectedRowVersion: 1,
        );

        $audit = $this->latestAudit('order.items_added', (int) $order->order_id);
        $subjects = $this->auditSubjects((int) $audit->audit_id);

        self::assertTrue($this->hasSubject($subjects, 'reservation_order', $orderId, 'primary'));
        self::assertTrue($this->hasSubject($subjects, 'reservation', $reservationId, 'reservation'));
        self::assertTrue($this->hasSubject($subjects, 'branch', 1, 'branch'));
        self::assertTrue($this->hasSubject($subjects, 'restaurant_table', $tableId, 'table'));
        self::assertTrue($this->hasSubject($subjects, 'menu_item', $menuItemId, 'menu_item'));
        self::assertTrue(
            $subjects->contains(fn ($subject): bool => $subject->subject_type === 'reservation_order_item'
                && $subject->subject_role === 'order_item')
        );
    }

    public function test_audit_payload_contains_money_actor_branch_context(): void
    {
        [$staffId, $tableId, $reservationId, $menuItemId] = $this->createAuditableOrderContext('12345');

        $order = $this->makeTableOrderService()->createOnSpotOrder(
            tableId: $tableId,
            reservationId: $reservationId,
            items: [['menu_item_id' => $menuItemId, 'qty' => 2]],
            staffUserId: $staffId,
            idempotencyKey: 'order-audit-money-secret',
            notes: '',
            expectedRowVersion: 1,
        );

        $audit = $this->latestAudit('order.created', (int) $order->order_id);
        $summary = $this->decodeJsonColumn($audit->summary_json);
        $after = $this->decodeJsonColumn($audit->after_json);

        self::assertSame($staffId, (int) $audit->actor_user_id);
        self::assertSame($staffId, $summary['actor_user_id'] ?? null);
        self::assertSame(1, $summary['branch_id'] ?? null);
        self::assertSame($reservationId, $summary['reservation_id'] ?? null);
        self::assertSame([$tableId], $summary['table_ids'] ?? null);
        self::assertSame((int) $order->order_id, $summary['order_id'] ?? null);
        self::assertSame((int) $order->row_version, $summary['order_row_version'] ?? null);
        self::assertSame('VND', $summary['currency'] ?? null);
        self::assertSame($menuItemId, $summary['items'][0]['menu_item_id'] ?? null);
        self::assertSame(2, $summary['items'][0]['quantity'] ?? null);
        self::assertSame('12345', $summary['items'][0]['unit_price'] ?? null);
        self::assertSame('24690', $summary['items'][0]['line_total'] ?? null);
        self::assertSame($summary, $after);
    }

    public function test_audit_payload_does_not_store_raw_idempotency_key_or_secret(): void
    {
        [$staffId, $tableId, $reservationId, $menuItemId] = $this->createAuditableOrderContext('99000');
        $rawKey = 'raw-idempotency-secret-order-audit-123';

        $order = $this->makeTableOrderService()->createOnSpotOrder(
            tableId: $tableId,
            reservationId: $reservationId,
            items: [['menu_item_id' => $menuItemId, 'qty' => 1]],
            staffUserId: $staffId,
            idempotencyKey: $rawKey,
            notes: '',
            expectedRowVersion: 1,
        );

        $audit = $this->latestAudit('order.created', (int) $order->order_id);
        $json = json_encode([
            'summary' => $this->decodeJsonColumn($audit->summary_json),
            'after' => $this->decodeJsonColumn($audit->after_json),
            'meta' => $this->decodeJsonColumn($audit->meta_json),
        ], JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString($rawKey, $json);
        self::assertStringNotContainsString('raw-idempotency-secret', $json);
        self::assertStringContainsString(hash('sha256', $rawKey), $json);
        self::assertTrue((bool) data_get($this->decodeJsonColumn($audit->summary_json), 'request_key_present'));
    }

    public function test_idempotent_replay_does_not_duplicate_actual_order_mutation_audit(): void
    {
        [$staffId, $tableId, $reservationId, $menuItemId] = $this->createAuditableOrderContext('25000');
        $service = $this->makeTableOrderService();
        $payload = [['menu_item_id' => $menuItemId, 'qty' => 1]];

        $first = $service->createOnSpotOrder(
            tableId: $tableId,
            reservationId: $reservationId,
            items: $payload,
            staffUserId: $staffId,
            idempotencyKey: 'order-audit-replay-key',
            notes: 'replay audit',
            expectedRowVersion: 1,
        );
        $second = $service->createOnSpotOrder(
            tableId: $tableId,
            reservationId: $reservationId,
            items: $payload,
            staffUserId: $staffId,
            idempotencyKey: 'order-audit-replay-key',
            notes: 'replay audit',
            expectedRowVersion: 1,
        );

        self::assertSame((int) $first->order_id, (int) $second->order_id);
        self::assertSame(
            1,
            (int) DB::table('audit_logs')
                ->where('action', 'order.created')
                ->where('entity_type', 'reservation_order')
                ->where('entity_id', (string) $first->order_id)
                ->count()
        );
    }

    /**
     * @return array{int,int,int,int}
     */
    private function createAuditableOrderContext(string $price): array
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation([
            'status' => 'Reserved',
            'branch_id' => 1,
            'bill_currency' => 'VND',
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        $menuItemId = $this->createMenuItem();
        $this->createMenuItemPrice([
            'item_id' => $menuItemId,
            'price' => $price,
            'currency' => 'VND',
        ]);

        return [$staffId, $tableId, $reservationId, $menuItemId];
    }

    private function latestAudit(string $action, int $orderId): object
    {
        $audit = DB::table('audit_logs')
            ->where('action', $action)
            ->where('entity_type', 'reservation_order')
            ->where('entity_id', (string) $orderId)
            ->orderByDesc('audit_id')
            ->first();

        self::assertNotNull($audit, 'Expected structured order audit row.');

        return $audit;
    }

    private function auditSubjects(int $auditId)
    {
        return DB::table('audit_log_subjects')
            ->where('audit_id', $auditId)
            ->get(['subject_type', 'subject_id', 'subject_role']);
    }

    private function hasSubject($subjects, string $type, int $id, string $role): bool
    {
        return $subjects->contains(
            fn ($subject): bool => $subject->subject_type === $type
                && $subject->subject_id === (string) $id
                && $subject->subject_role === $role
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonColumn(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }
}
