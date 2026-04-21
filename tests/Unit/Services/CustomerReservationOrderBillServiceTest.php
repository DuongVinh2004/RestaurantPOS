<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PaymentSessionScope;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Billing\Application\UseCases\Previews\CustomerReservationOrderBillService;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\PaymentProviderRolloutConfig;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\Ordering\Application\Queries\StaffOrderReadService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class CustomerReservationOrderBillServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_bill_preview_marks_staff_settlement_only_when_customer_self_pay_is_disabled(): void
    {
        $staffOrderReadService = Mockery::mock(StaffOrderReadService::class);
        $computedSnapshot = [
            'subtotal' => 100000.0,
            'discount' => 0.0,
            'total_due' => 100000.0,
            'currency' => 'VND',
        ];

        $staffOrderReadService->shouldReceive('findActiveOrderForReservationModel')
            ->once()
            ->with(
                Mockery::on(static fn (mixed $reservation): bool => $reservation instanceof Reservation
                    && (int) $reservation->reservation_id === 101),
                $computedSnapshot,
            )
            ->andReturn(null);

        $financialSyncService = Mockery::mock(ReservationFinancialSyncService::class);
        $financialSyncService->shouldReceive('computeReservationBillSnapshot')
            ->once()
            ->andReturn($computedSnapshot);

        $settlementAmountCalculator = Mockery::mock(SettlementAmountCalculator::class);
        $settlementAmountCalculator->shouldReceive('buildSettlementAmounts')
            ->once()
            ->andReturn([
                'settled_amount' => 0.0,
                'remaining_due' => 100000.0,
                'deposit_applied_amount' => 0.0,
                'deposit_net_amount' => 0.0,
                'final_paid_amount' => 0.0,
            ]);

        $loyaltyPointsService = Mockery::mock(LoyaltyPointsService::class);
        $loyaltyPointsService->shouldReceive('getReservationLoyaltyPreview')
            ->once()
            ->with(
                Mockery::on(static fn (mixed $reservation): bool => $reservation instanceof Reservation
                    && (int) $reservation->reservation_id === 101),
                Mockery::on(static fn (mixed $payments): bool => $payments instanceof Collection
                    && $payments->isEmpty()),
                $computedSnapshot,
            )
            ->andReturn(['reservation' => ['loyalty' => null]]);

        $paymentProviderRolloutConfig = Mockery::mock(PaymentProviderRolloutConfig::class);
        $paymentProviderRolloutConfig->shouldReceive('customerSelfPayStatus')
            ->once()
            ->with(PaymentSessionScope::Bill)
            ->andReturn([
                'ok' => false,
                'provider_code' => 'simulated',
                'message' => 'Customer self-pay is intentionally disabled for this rollout. Use staff settlement.',
            ]);

        $featureFlags = Mockery::mock(FeatureFlagService::class);
        $featureFlags->shouldReceive('resolve')
            ->once()
            ->with('customer.bill_self_payment', null)
            ->andReturn([
                'enabled' => true,
                'message' => 'Feature flag is enabled by config default.',
            ]);

        $service = app()->makeWith(CustomerReservationOrderBillService::class, [
            'staffOrderReadService' => $staffOrderReadService,
            'reservationFinancialSyncService' => $financialSyncService,
            'settlementAmountCalculator' => $settlementAmountCalculator,
            'loyaltyPointsService' => $loyaltyPointsService,
            'paymentProviderRolloutConfig' => $paymentProviderRolloutConfig,
            'featureFlags' => $featureFlags,
        ]);

        $user = new User;
        $user->user_id = 50;
        $user->setRelation('points', null);
        $user->setRelation('currentTier', null);

        $reservation = new Reservation;
        $reservation->reservation_id = 101;
        $reservation->status = 'Reserved';
        $reservation->billed_at = now('UTC');
        $reservation->final_bill_amount = '100000.00';
        $reservation->discount_amount = '0.00';
        $reservation->bill_currency = 'VND';
        $reservation->setRelation('user', $user);
        $reservation->setRelation('tables', collect());
        $reservation->setRelation('payments', collect());
        $reservation->setRelation('appliedUserVoucher', null);

        $result = $service->previewAccessibleBill($reservation);

        $this->assertFalse($result['bill_preview']['self_payment']['supported']);
        $this->assertFalse($result['bill_preview']['self_payment']['available']);
        $this->assertSame('staff_settlement_only', $result['bill_preview']['self_payment']['next_step']);
        $this->assertSame(
            'Customer self-pay is intentionally disabled for this rollout. Use staff settlement.',
            $result['bill_preview']['self_payment']['disabled_reason']
        );
    }

    public function test_active_order_seeds_in_memory_relations_without_hidden_hydration(): void
    {
        $staffOrderReadService = Mockery::mock(StaffOrderReadService::class);
        $staffOrderReadService->shouldReceive('findActiveOrderForReservationModel')
            ->once()
            ->withArgs(static fn (mixed $reservation, mixed ...$rest): bool => $reservation instanceof Reservation
                    && (int) $reservation->reservation_id === 202
                    && $reservation->relationLoaded('tables')
                    && $reservation->tables instanceof Collection
                    && $reservation->tables->isEmpty()
                    && $reservation->relationLoaded('payments')
                    && $reservation->payments instanceof Collection
                    && $reservation->payments->isEmpty()
                    && $rest === [])
            ->andReturn(null);

        $service = app()->makeWith(CustomerReservationOrderBillService::class, [
            'staffOrderReadService' => $staffOrderReadService,
            'reservationFinancialSyncService' => Mockery::mock(ReservationFinancialSyncService::class),
            'settlementAmountCalculator' => Mockery::mock(SettlementAmountCalculator::class),
            'loyaltyPointsService' => Mockery::mock(LoyaltyPointsService::class),
            'paymentProviderRolloutConfig' => Mockery::mock(PaymentProviderRolloutConfig::class),
            'featureFlags' => Mockery::mock(FeatureFlagService::class),
        ]);

        $reservation = new Reservation;
        $reservation->reservation_id = 202;
        $reservation->status = 'Reserved';

        $result = $service->showAccessibleActiveOrder($reservation);

        $this->assertSame(202, (int) $result['reservation']->reservation_id);
        $this->assertNull($result['active_order']);
    }
}

