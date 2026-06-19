<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Loyalty;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyAdjustmentService;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyBalanceService;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyRedemptionService;
use App\Modules\Loyalty\Application\UseCases\Tiers\LoyaltyTierSyncService;
use App\Modules\Loyalty\Application\Workflows\LoyaltyCompletionSyncService;
use App\Modules\Loyalty\Application\Workflows\LoyaltyLedgerWriter;
use App\Modules\Loyalty\Application\Workflows\LoyaltyRefundSyncService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class LoyaltySyncServicesTest extends TestCase
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

    public function test_adjustment_service_writes_ledger_and_updates_points(): void
    {
        $tierId = $this->createLoyaltyTier();
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->ensureUserPoints($customerId, 10, $staffId);

        /** @var User $user */
        $user = User::query()->where('user_id', $customerId)->firstOrFail();
        $ledgerWriter = new LoyaltyLedgerWriter;
        $pointLedger = $ledgerWriter->lockUserPointLedger($user, $staffId);

        $service = new LoyaltyAdjustmentService($ledgerWriter, new LoyaltyTierSyncService);
        $service->adjustUserPointsLocked($user, $pointLedger, 15, 'batch2', $staffId);

        $this->assertSame(25, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(15, (int) DB::table('loyalty_point_transactions')
            ->where('user_id', $customerId)
            ->where('txn_type', 'Adjust')
            ->where('reason', 'manual.adjust:batch2')
            ->sum('points'));
    }

    public function test_completion_and_refund_sync_services_award_then_reverse_earn_and_release_redemption_on_cancel(): void
    {
        $tierId = $this->createLoyaltyTier();
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->ensureUserPoints($customerId, 500, $staffId);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => Carbon::parse('2026-03-18 10:00:00', 'UTC'),
            'bill_currency' => 'VND',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 1,
            'unit_price' => 250000,
            'line_total' => 250000,
            'status' => 'Ordered',
        ]);

        $runtime = $this->mockRuntimeSettings();
        $balanceService = new LoyaltyBalanceService($runtime);
        $ledgerWriter = new LoyaltyLedgerWriter;
        $tierSyncService = new LoyaltyTierSyncService;
        $financialSync = new ReservationFinancialSyncService;
        $redemptionService = new LoyaltyRedemptionService($financialSync, $ledgerWriter, $tierSyncService, $balanceService);

        $this->makeLoyaltyService()->redeemReservationPoints(
            reservationId: $reservationId,
            points: 100,
            reason: 'unit-batch2',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->where('reservation_id', $reservationId)->firstOrFail();
        $reservation->status = 'Completed';
        $reservation->checked_out_at = Carbon::parse('2026-03-18 10:30:00', 'UTC');
        $reservation->billed_at = Carbon::parse('2026-03-18 10:30:00', 'UTC');
        $reservation->final_bill_amount = 150000.0;
        $reservation->save();

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '150000',
            'currency' => 'VND',
            'transaction_code' => 'LOYALTY-SYNC-B2-1',
        ]);

        /** @var User $user */
        $user = User::query()->where('user_id', $customerId)->lockForUpdate()->firstOrFail();
        $pointLedger = $ledgerWriter->lockUserPointLedger($user, $staffId);
        $payments = Payment::query()->where('reservation_id', $reservationId)->lockForUpdate()->get();

        $completionService = new LoyaltyCompletionSyncService($balanceService, $ledgerWriter, $tierSyncService);
        $completionService->syncReservationCompletionLocked($reservation->fresh(), $user, $pointLedger, $payments, $staffId);

        $this->assertSame(415, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(15, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'earn.completed')
            ->sum('points'));

        $reservation = Reservation::query()->where('reservation_id', $reservationId)->firstOrFail();
        $reservation->status = 'Cancelled';
        $reservation->cancelled_at = Carbon::parse('2026-03-18 11:00:00', 'UTC');
        $reservation->save();

        $refundService = new LoyaltyRefundSyncService($balanceService, $ledgerWriter, $tierSyncService, $redemptionService);
        $refundService->syncReservationRefundImpactLocked($reservation->fresh(), $user, $pointLedger->fresh(), $payments, $staffId, true);

        $this->assertSame(500, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(100, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'redeem.release:cancelled_after_payment')
            ->sum('points'));
        $this->assertSame(-15, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'earn.sync.refund')
            ->sum('points'));
    }

    public function test_refund_sync_service_is_idempotent_when_cancelled_flow_is_replayed(): void
    {
        $tierId = $this->createLoyaltyTier();
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->ensureUserPoints($customerId, 500, $staffId);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Reserved',
            'checked_in_at' => Carbon::parse('2026-03-18 12:00:00', 'UTC'),
            'bill_currency' => 'VND',
        ]);
        $orderId = $this->createOrder([
            'reservation_id' => $reservationId,
            'status' => 'Active',
        ]);
        $this->createOrderItem([
            'order_id' => $orderId,
            'currency' => 'VND',
            'quantity' => 1,
            'unit_price' => 200000,
            'line_total' => 200000,
            'status' => 'Ordered',
        ]);

        $runtime = $this->mockRuntimeSettings();
        $balanceService = new LoyaltyBalanceService($runtime);
        $ledgerWriter = new LoyaltyLedgerWriter;
        $tierSyncService = new LoyaltyTierSyncService;
        $financialSync = new ReservationFinancialSyncService;
        $redemptionService = new LoyaltyRedemptionService($financialSync, $ledgerWriter, $tierSyncService, $balanceService);

        $this->makeLoyaltyService()->redeemReservationPoints(
            reservationId: $reservationId,
            points: 100,
            reason: 'unit-batch3',
            expectedRowVersion: null,
            staffUserId: $staffId,
        );

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->where('reservation_id', $reservationId)->firstOrFail();
        $reservation->status = 'Completed';
        $reservation->checked_out_at = Carbon::parse('2026-03-18 12:30:00', 'UTC');
        $reservation->billed_at = Carbon::parse('2026-03-18 12:30:00', 'UTC');
        $reservation->final_bill_amount = 100000.0;
        $reservation->save();

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000',
            'currency' => 'VND',
            'transaction_code' => 'LOYALTY-SYNC-B3-1',
        ]);

        /** @var User $user */
        $user = User::query()->where('user_id', $customerId)->lockForUpdate()->firstOrFail();
        $pointLedger = $ledgerWriter->lockUserPointLedger($user, $staffId);
        $payments = Payment::query()->where('reservation_id', $reservationId)->lockForUpdate()->get();
        $completionService = new LoyaltyCompletionSyncService($balanceService, $ledgerWriter, $tierSyncService);
        $completionService->syncReservationCompletionLocked($reservation->fresh(), $user, $pointLedger, $payments, $staffId);

        $reservation = Reservation::query()->where('reservation_id', $reservationId)->firstOrFail();
        $reservation->status = 'Cancelled';
        $reservation->cancelled_at = Carbon::parse('2026-03-18 13:00:00', 'UTC');
        $reservation->save();

        $refundService = new LoyaltyRefundSyncService($balanceService, $ledgerWriter, $tierSyncService, $redemptionService);
        $refundService->syncReservationRefundImpactLocked($reservation->fresh(), $user, $pointLedger->fresh(), $payments, $staffId, true);
        $refundService->syncReservationRefundImpactLocked($reservation->fresh(), $user, $pointLedger->fresh(), $payments, $staffId, true);

        $this->assertSame(500, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'redeem.release:cancelled_after_payment')
            ->count());
        $this->assertSame(1, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'earn.sync.refund')
            ->count());
    }

    public function test_refund_sync_keeps_earn_points_when_only_deposit_net_changes_after_completion(): void
    {
        $tierId = $this->createLoyaltyTier();
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'current_tier_id' => $tierId,
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $this->ensureUserPoints($customerId, 500, $staffId);

        $reservationId = $this->createReservation([
            'user_id' => $customerId,
            'status' => 'Completed',
            'checked_in_at' => Carbon::parse('2026-03-18 14:00:00', 'UTC'),
            'checked_out_at' => Carbon::parse('2026-03-18 14:40:00', 'UTC'),
            'billed_at' => Carbon::parse('2026-03-18 14:40:00', 'UTC'),
            'final_bill_amount' => 150000.0,
            'bill_currency' => 'VND',
        ]);

        $depositPaymentId = $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Deposit',
            'status' => 'Success',
            'amount' => '50000',
            'currency' => 'VND',
            'transaction_code' => 'LOYALTY-SYNC-DEPOSIT-KEEP-1',
        ]);
        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Final',
            'status' => 'Success',
            'amount' => '100000',
            'currency' => 'VND',
            'transaction_code' => 'LOYALTY-SYNC-DEPOSIT-KEEP-2',
        ]);

        $runtime = $this->mockRuntimeSettings();
        $balanceService = new LoyaltyBalanceService($runtime);
        $ledgerWriter = new LoyaltyLedgerWriter;
        $tierSyncService = new LoyaltyTierSyncService;
        $financialSync = new ReservationFinancialSyncService;
        $redemptionService = new LoyaltyRedemptionService($financialSync, $ledgerWriter, $tierSyncService, $balanceService);

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->where('reservation_id', $reservationId)->firstOrFail();
        /** @var User $user */
        $user = User::query()->where('user_id', $customerId)->lockForUpdate()->firstOrFail();
        $pointLedger = $ledgerWriter->lockUserPointLedger($user, $staffId);
        $payments = Payment::query()->where('reservation_id', $reservationId)->lockForUpdate()->get();

        $completionService = new LoyaltyCompletionSyncService($balanceService, $ledgerWriter, $tierSyncService);
        $completionService->syncReservationCompletionLocked($reservation->fresh(), $user, $pointLedger, $payments, $staffId);

        $this->assertSame(510, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(10, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'earn.completed')
            ->sum('points'));

        $this->createPayment([
            'reservation_id' => $reservationId,
            'payment_type' => 'Refund',
            'status' => 'Refunded',
            'amount' => '20000',
            'currency' => 'VND',
            'refund_of_payment_id' => $depositPaymentId,
            'transaction_code' => 'LOYALTY-SYNC-DEPOSIT-REFUND-1',
            'provider_response_json' => json_encode(['refund_target_payment_type' => 'Deposit'], JSON_THROW_ON_ERROR),
        ]);

        $payments = Payment::query()->where('reservation_id', $reservationId)->lockForUpdate()->get();
        $refundService = new LoyaltyRefundSyncService($balanceService, $ledgerWriter, $tierSyncService, $redemptionService);
        $refundService->syncReservationRefundImpactLocked($reservation->fresh(), $user, $pointLedger->fresh(), $payments, $staffId, false);

        $this->assertSame(510, (int) DB::table('user_points')->where('user_id', $customerId)->value('total_points'));
        $this->assertSame(10, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->whereIn('reason', ['earn.completed', 'earn.sync.complete', 'earn.sync.refund'])
            ->sum('points'));
        $this->assertSame(0, (int) DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where('reason', 'earn.sync.refund')
            ->count());
    }
}
