<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Modules\Billing\Application\UseCases\Previews\BillLockService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class BillLockServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeBillLockService(): BillLockService
    {
        return app(BillLockService::class);
    }

    public function test_lock_bill_sets_billed_snapshot_and_attached_totals(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation(['status' => 'Reserved']);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 2,
            'unit_price' => '50000',
            'currency' => 'VND',
            'line_total' => '100000',
        ]);

        $order = $this->makeBillLockService()->lockBill(
            orderId: $orderId,
            discountAmount: null,
            notes: 'bill locked',
            staffUserId: $staffId,
            expectedRowVersion: 1,
            assertExpectedOrderRowVersion: function (ReservationOrder $order, ?int $expectedRowVersion): void {
                if ($expectedRowVersion === null) {
                    return;
                }

                if ((int) ($order->row_version ?? 1) !== $expectedRowVersion) {
                    throw ValidationException::withMessages([
                        'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
                    ]);
                }
            },
            currentLoyaltyDiscountAmount: fn (int $reservationId): float => 0.0,
            currentVoucherDiscountAmount: fn (int $reservationId, bool $lock = false): float => 0.0,
            attachTotals: function (
                ReservationOrder $order,
                float $subtotal,
                float $discount,
                float $totalDue,
                string $currency
            ): ReservationOrder {
                $order->setAttribute('subtotal_amount', round($subtotal, 2));
                $order->setAttribute('discount_amount', round($discount, 2));
                $order->setAttribute('total_due_amount', round($totalDue, 2));
                $order->setAttribute('paid_amount', 0.0);
                $order->setAttribute('deposit_applied_amount', 0.0);
                $order->setAttribute('deposit_net_amount', 0.0);
                $order->setAttribute('final_paid_amount', 0.0);
                $order->setAttribute('outstanding_amount', round($totalDue, 2));
                $order->setAttribute('currency', $currency !== '' ? $currency : 'VND');
                $order->setAttribute('payment_status', 'Failed');

                return $order;
            },
        );

        $reservation = $order->reservation()->first();

        $this->assertNotNull($reservation?->billed_at);
        $this->assertSame(100000.0, (float) ($reservation?->final_bill_amount ?? 0.0));
        $this->assertSame('bill locked', (string) ($order->notes ?? ''));
        $this->assertSame(100000.0, (float) $order->getAttribute('total_due_amount'));
        $this->assertSame(100000.0, (float) $order->getAttribute('outstanding_amount'));
    }

    public function test_lock_bill_can_skip_reservation_row_version_bump_without_raw_direct_update(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $tableId = $this->createRestaurantTable(['status' => 'Occupied']);
        $reservationId = $this->createReservation(['status' => 'Reserved', 'row_version' => 3]);
        $this->attachReservationTable($reservationId, $tableId);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'row_version' => 1,
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'quantity' => 1,
            'unit_price' => '50000',
            'currency' => 'VND',
            'line_total' => '50000',
        ]);

        $this->makeBillLockService()->lockBill(
            orderId: $orderId,
            discountAmount: 5000.0,
            notes: 'bill locked without reservation version bump',
            staffUserId: $staffId,
            expectedRowVersion: 1,
            assertExpectedOrderRowVersion: function (ReservationOrder $order, ?int $expectedRowVersion): void {
                if ($expectedRowVersion === null) {
                    return;
                }

                if ((int) ($order->row_version ?? 1) !== $expectedRowVersion) {
                    throw ValidationException::withMessages([
                        'row_version' => ['Dá»¯ liá»‡u Ä‘Ã£ thay Ä‘á»•i (row_version mismatch). HÃ£y reload rá»“i thá»­ láº¡i.'],
                    ]);
                }
            },
            currentLoyaltyDiscountAmount: fn (int $reservationId): float => 0.0,
            currentVoucherDiscountAmount: fn (int $reservationId, bool $lock = false): float => 0.0,
            attachTotals: fn (ReservationOrder $order, float $subtotal, float $discount, float $totalDue, string $currency): ReservationOrder => $order,
            bumpReservationVersion: false,
        );

        $reservation = DB::table('reservations')->where('reservation_id', $reservationId)->first();

        $expectedReservationRowVersion = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) ? 4 : 3;

        self::assertSame($expectedReservationRowVersion, (int) ($reservation->row_version ?? 0));
        self::assertSame(45000.0, (float) ($reservation->final_bill_amount ?? 0.0));
        self::assertSame(5000.0, (float) ($reservation->discount_amount ?? 0.0));
        self::assertNotNull($reservation->billed_at);
    }
}
