<?php

declare(strict_types=1);

namespace Tests\Feature\Reservation;

use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerReservationPreorderManagementFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_owner_can_view_current_preorder_for_owned_reservation(): void
    {
        $this->requirePreorderContract();

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'start_time' => $this->nowUtc()->copy()->addHours(4),
            'end_time' => $this->nowUtc()->copy()->addHours(6),
        ]);
        $itemId = $this->createPreorderMenuItem(price: '85000');
        $orderId = DB::table('preorders')->insertGetId([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'status' => 'draft',
            'row_version' => 1,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);
        DB::table('preorder_items')->insert([
            'preorder_id' => $orderId,
            'menu_item_id' => $itemId,
            'item_name_snapshot' => 'Preorder Item',
            'unit_price_snapshot' => '85000',
            'quantity' => 2,
            'line_total_snapshot' => '170000',
            'currency' => 'VND',
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        $response = $this->actingAsUserId($customerId)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/pre-order');

        $response->assertOk()
            ->assertJsonPath('data.reservation_id', $reservationId)
            ->assertJsonPath('data.pre_order.present', true)
            ->assertJsonPath('data.pre_order.order_id', $orderId)
            ->assertJsonPath('data.pre_order.lines.0.item_id', $itemId)
            ->assertJsonPath('data.pre_order.lines.0.quantity', 2)
            ->assertJsonPath('data.pre_order.totals.subtotal', '170000');
    }

    public function test_owner_can_replace_preorder_when_within_valid_window(): void
    {
        $this->requirePreorderContract();

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'start_time' => $this->nowUtc()->copy()->addHours(4),
            'end_time' => $this->nowUtc()->copy()->addHours(6),
        ]);
        $oldItemId = $this->createPreorderMenuItem(price: '50000');
        $replacementItemId = $this->createPreorderMenuItem(price: '85000');
        $orderId = DB::table('preorders')->insertGetId([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'status' => 'draft',
            'row_version' => 1,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);
        DB::table('preorder_items')->insert([
            'preorder_id' => $orderId,
            'menu_item_id' => $oldItemId,
            'item_name_snapshot' => 'Preorder Item Old',
            'unit_price_snapshot' => '50000',
            'quantity' => 1,
            'line_total_snapshot' => '50000',
            'currency' => 'VND',
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        $payload = [
            'row_version' => 1,
            'pre_order_row_version' => (int) DB::table('preorders')
                ->where('preorder_id', $orderId)
                ->value('row_version'),
            'pre_order_items' => [
                ['item_id' => $replacementItemId, 'quantity' => 3],
            ],
        ];

        $headers = [
            'Accept' => 'application/json',
            'Idempotency-Key' => 'replace-preorder-'.$reservationId,
        ];

        $replace = $this->actingAsUserId($customerId)
            ->withHeaders($headers)
            ->putJson('/api/v1/reservations/'.$reservationId.'/pre-order', $payload);

        $replace->assertOk()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('data.pre_order.present', true)
            ->assertJsonPath('data.pre_order.order_id', $orderId)
            ->assertJsonPath('data.pre_order.lines.0.item_id', $replacementItemId)
            ->assertJsonPath('data.pre_order.lines.0.quantity', 3)
            ->assertJsonPath('data.pre_order.lines.0.unit_price', '85000')
            ->assertJsonPath('data.pre_order.totals.subtotal', '255000');

        $second = $this->actingAsUserId($customerId)
            ->withHeaders($headers)
            ->putJson('/api/v1/reservations/'.$reservationId.'/pre-order', $payload);

        $second->assertOk()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame(
            1,
            (int) DB::table('preorder_items')
                ->where('preorder_id', $orderId)
                ->count()
        );
    }

    public function test_checked_in_completed_and_cancelled_reservations_reject_preorder_mutation(): void
    {
        $this->requirePreorderContract();

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $itemId = $this->createPreorderMenuItem(price: '85000');

        $cases = [
            [
                'status' => 'Reserved',
                'checked_in_at' => $this->nowUtc(),
                'checked_out_at' => null,
                'cancelled_at' => null,
            ],
            [
                'status' => 'Completed',
                'checked_in_at' => $this->nowUtc()->copy()->subHours(2),
                'checked_out_at' => $this->nowUtc()->copy()->subHour(),
                'cancelled_at' => null,
            ],
            [
                'status' => 'Cancelled',
                'checked_in_at' => null,
                'checked_out_at' => null,
                'cancelled_at' => $this->nowUtc(),
            ],
        ];

        foreach ($cases as $case) {
            $reservationId = $this->createReservation(array_merge([
                'user_id' => $customerId,
                'start_time' => $this->nowUtc()->copy()->addHours(4),
                'end_time' => $this->nowUtc()->copy()->addHours(6),
            ], $case));

            $response = $this->actingAsUserId($customerId)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Idempotency-Key' => 'preorder-invalid-status-'.$reservationId,
                ])
                ->putJson('/api/v1/reservations/'.$reservationId.'/pre-order', [
                    'row_version' => 1,
                    'pre_order_items' => [
                        ['item_id' => $itemId, 'quantity' => 1],
                    ],
                ]);

            $response->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_error');
        }
    }

    public function test_wrong_owner_cannot_view_or_mutate_preorder(): void
    {
        $this->requirePreorderContract();

        $ownerId = $this->createUser(['role_name' => 'Customer']);
        $otherCustomerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $ownerId,
            'status' => 'Confirmed',
            'start_time' => $this->nowUtc()->copy()->addHours(4),
            'end_time' => $this->nowUtc()->copy()->addHours(6),
        ]);
        $itemId = $this->createPreorderMenuItem(price: '85000');
        $orderId = DB::table('preorders')->insertGetId([
            'reservation_id' => $reservationId,
            'customer_user_id' => $ownerId,
            'status' => 'draft',
            'row_version' => 1,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);
        DB::table('preorder_items')->insert([
            'preorder_id' => $orderId,
            'menu_item_id' => $itemId,
            'item_name_snapshot' => 'Preorder Item',
            'unit_price_snapshot' => '85000',
            'quantity' => 1,
            'line_total_snapshot' => '85000',
            'currency' => 'VND',
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        $this->actingAsUserId($otherCustomerId)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/pre-order')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');

        $this->actingAsUserId($otherCustomerId)
            ->withHeaders([
                'Accept' => 'application/json',
                'Idempotency-Key' => 'wrong-owner-preorder-'.$reservationId,
            ])
            ->putJson('/api/v1/reservations/'.$reservationId.'/pre-order', [
                'row_version' => 1,
                'pre_order_items' => [
                    ['item_id' => $itemId, 'quantity' => 2],
                ],
            ])
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_invalid_menu_item_or_availability_rejects_preorder_update(): void
    {
        $this->requirePreorderContract();

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'start_time' => $this->nowUtc()->copy()->addHours(4),
            'end_time' => $this->nowUtc()->copy()->addHours(6),
        ]);
        $orderId = DB::table('preorders')->insertGetId([
            'reservation_id' => $reservationId,
            'customer_user_id' => $customerId,
            'status' => 'draft',
            'row_version' => 1,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);
        $existingItemId = $this->createPreorderMenuItem(price: '60000');
        DB::table('preorder_items')->insert([
            'preorder_id' => $orderId,
            'menu_item_id' => $existingItemId,
            'item_name_snapshot' => 'Existing Preorder Item',
            'unit_price_snapshot' => '60000',
            'quantity' => 1,
            'line_total_snapshot' => '60000',
            'currency' => 'VND',
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);
        $unavailableItemId = $this->createPreorderMenuItem(price: '70000', overrides: ['is_available' => 0]);

        $this->actingAsUserId($customerId)
            ->withHeaders([
                'Accept' => 'application/json',
                'Idempotency-Key' => 'preorder-invalid-item-'.$reservationId,
            ])
            ->putJson('/api/v1/reservations/'.$reservationId.'/pre-order', [
                'row_version' => 1,
                'pre_order_items' => [
                    ['item_id' => $unavailableItemId, 'quantity' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');
    }

    public function test_create_reservation_with_preorder_still_works_and_is_readable_via_new_endpoint(): void
    {
        $this->requirePreorderContract();

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable();
        $preorderItemId = $this->createPreorderMenuItem(price: '85000');
        $start = $this->nowUtc()->copy()->addHours(4);
        $end = $start->copy()->addHours(2);

        $create = $this->actingAsUserId($customerId)
            ->withHeaders([
                'Accept' => 'application/json',
                'Idempotency-Key' => 'reservation-create-preorder-'.$customerId,
            ])
            ->postJson('/api/v1/reservations', [
                'start_time' => $start->toIso8601String(),
                'end_time' => $end->toIso8601String(),
                'guest_count' => 2,
                'table_ids' => [$tableId],
                'pre_order_items' => [
                    ['item_id' => $preorderItemId, 'quantity' => 2],
                ],
            ]);

        $create->assertCreated();
        $reservationId = (int) $create->json('data.reservation_id');
        $showPreorder = $this->actingAsUserId($customerId)
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reservations/'.$reservationId.'/pre-order');

        $showPreorder->assertOk()
            ->assertJsonPath('data.pre_order.present', true)
            ->assertJsonPath('data.pre_order.lines.0.item_id', $preorderItemId)
            ->assertJsonPath('data.pre_order.lines.0.quantity', 2)
            ->assertJsonPath('data.pre_order.totals.subtotal', '170000');
    }

    public function test_create_reservation_with_preorder_rejects_item_without_effective_price_in_live_path(): void
    {
        $this->requirePreorderContract();

        $customerId = $this->createUser(['role_name' => 'Customer']);
        $tableId = $this->createRestaurantTable();
        $itemId = $this->createPreorderMenuItem(price: null);
        $start = $this->nowUtc()->copy()->addHours(4);
        $end = $start->copy()->addHours(2);

        $response = $this->actingAsUserId($customerId)
            ->withHeaders([
                'Accept' => 'application/json',
                'Idempotency-Key' => 'reservation-create-missing-price-'.$customerId,
            ])
            ->postJson('/api/v1/reservations', [
                'start_time' => $start->toIso8601String(),
                'end_time' => $end->toIso8601String(),
                'guest_count' => 2,
                'table_ids' => [$tableId],
                'pre_order_items' => [
                    ['item_id' => $itemId, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error');

        $this->assertSame(0, DB::table('preorders')->count());
        $this->assertSame(0, DB::table('preorder_items')->count());
    }

    private function actingAsUserId(int $userId): self
    {
        /** @var User $user */
        $user = User::query()->findOrFail($userId);
        $this->actingAs($user);

        return $this;
    }

    private function requirePreorderContract(): void
    {
        if (! MenuItem::supportsPreorderColumns()) {
            $this->markTestSkipped('Preorder columns are not available in this schema snapshot.');
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPreorderMenuItem(?string $price, array $overrides = []): int
    {
        $itemId = $this->createMenuItem(array_merge([
            'is_available' => 1,
            'is_preorder_enabled' => 1,
            'preorder_quota_per_day' => 20,
            'preorder_cutoff_minutes' => 30,
        ], $overrides));

        if ($price !== null) {
            $this->createMenuItemPrice([
                'item_id' => $itemId,
                'price' => $price,
                'currency' => 'VND',
                'effective_from' => Carbon::now('UTC')->subHour(),
                'effective_to' => null,
            ]);
        }

        return $itemId;
    }
}
