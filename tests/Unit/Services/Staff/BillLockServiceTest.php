<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\CheckoutPayments\Application\Services\BillLockService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
            'unit_price' => '50000.00',
            'currency' => 'VND',
            'line_total' => '100000.00',
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
}
