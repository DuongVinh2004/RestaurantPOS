<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Branch;

use App\Services\Branch\BranchSchedulingPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class BranchSchedulingPolicyServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
    }

    public function test_reservation_window_uses_branch_timezone_business_hours_and_closure_windows(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'SGN',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'business_hours' => $this->dailyBusinessHours('10:00', '22:00'),
            'closure_windows' => [
                [
                    'start_local' => '2026-05-01 12:00:00',
                    'end_local' => '2026-05-01 13:30:00',
                    'type' => 'holiday',
                    'reason' => 'Lunch closure',
                ],
            ],
        ]);

        $service = app(BranchSchedulingPolicyService::class);
        $nowUtc = Carbon::parse('2026-04-30 00:00:00', 'UTC');

        $allowed = $service->evaluateReservationWindow(
            $branchId,
            Carbon::parse('2026-05-01 10:30:00', 'Asia/Ho_Chi_Minh')->utc(),
            Carbon::parse('2026-05-01 11:30:00', 'Asia/Ho_Chi_Minh')->utc(),
            $nowUtc,
            'reservation',
            false,
        );

        self::assertTrue($allowed['allowed']);
        self::assertNull($allowed['reason']);

        $outsideHours = $service->evaluateReservationWindow(
            $branchId,
            Carbon::parse('2026-05-01 09:30:00', 'Asia/Ho_Chi_Minh')->utc(),
            Carbon::parse('2026-05-01 10:30:00', 'Asia/Ho_Chi_Minh')->utc(),
            $nowUtc,
            'reservation',
            false,
        );

        self::assertFalse($outsideHours['allowed']);
        self::assertSame('outside_business_hours', $outsideHours['reason']);

        $closureOverlap = $service->evaluateReservationWindow(
            $branchId,
            Carbon::parse('2026-05-01 12:15:00', 'Asia/Ho_Chi_Minh')->utc(),
            Carbon::parse('2026-05-01 13:00:00', 'Asia/Ho_Chi_Minh')->utc(),
            $nowUtc,
            'reservation',
            false,
        );

        self::assertFalse($closureOverlap['allowed']);
        self::assertSame('closure_window', $closureOverlap['reason']);
    }

    public function test_reservation_window_applies_lead_cutoff_and_max_advance_in_branch_timezone(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'NYC',
            'timezone' => 'America/New_York',
            'business_hours' => $this->dailyBusinessHours('00:00', '24:00'),
            'booking_policy' => [
                'reservation' => [
                    'min_lead_time_minutes' => 120,
                    'max_advance_time_minutes' => 60 * 24 * 7,
                    'same_day_cutoff_time' => '18:00',
                ],
            ],
        ]);

        $service = app(BranchSchedulingPolicyService::class);

        $leadFailure = $service->evaluateReservationWindow(
            $branchId,
            Carbon::parse('2026-07-01 18:30:00', 'America/New_York')->utc(),
            Carbon::parse('2026-07-01 19:30:00', 'America/New_York')->utc(),
            Carbon::parse('2026-07-01 17:30:00', 'America/New_York')->utc(),
            'reservation',
            false,
        );

        self::assertFalse($leadFailure['allowed']);
        self::assertSame('lead_time', $leadFailure['reason']);

        $cutoffFailure = $service->evaluateReservationWindow(
            $branchId,
            Carbon::parse('2026-07-01 20:30:00', 'America/New_York')->utc(),
            Carbon::parse('2026-07-01 21:30:00', 'America/New_York')->utc(),
            Carbon::parse('2026-07-01 18:05:00', 'America/New_York')->utc(),
            'reservation',
            false,
        );

        self::assertFalse($cutoffFailure['allowed']);
        self::assertSame('same_day_cutoff', $cutoffFailure['reason']);

        $advanceFailure = $service->evaluateReservationWindow(
            $branchId,
            Carbon::parse('2026-07-10 12:00:00', 'America/New_York')->utc(),
            Carbon::parse('2026-07-10 13:00:00', 'America/New_York')->utc(),
            Carbon::parse('2026-07-01 09:00:00', 'America/New_York')->utc(),
            'reservation',
            false,
        );

        self::assertFalse($advanceFailure['allowed']);
        self::assertSame('max_advance', $advanceFailure['reason']);
    }

    public function test_waiting_list_eligibility_requires_open_enabled_branch_and_respects_closure_windows(): void
    {
        $service = app(BranchSchedulingPolicyService::class);

        $disabledBranchId = $this->createBranch([
            'branch_code' => 'OFF',
            'timezone' => 'UTC',
            'business_hours' => $this->dailyBusinessHours('00:00', '24:00'),
            'booking_policy' => [
                'waiting_list' => [
                    'enabled' => false,
                ],
            ],
        ]);

        try {
            $service->assertWaitingListEligible($disabledBranchId, Carbon::parse('2026-08-01 12:00:00', 'UTC'), 'branch_id', false);
            self::fail('Expected disabled waiting-list branch to reject.');
        } catch (ValidationException $e) {
            self::assertSame('Waiting list is disabled for the selected branch.', $e->errors()['branch_id'][0] ?? null);
        }

        $openWindowBranchId = $this->createBranch([
            'branch_code' => 'DAY',
            'timezone' => 'Asia/Bangkok',
            'business_hours' => $this->dailyBusinessHours('10:00', '20:00'),
            'closure_windows' => [
                [
                    'start_local' => '2026-08-01 15:00:00',
                    'end_local' => '2026-08-01 16:00:00',
                    'type' => 'blackout',
                    'reason' => 'Private event',
                ],
            ],
        ]);

        try {
            $service->assertWaitingListEligible($openWindowBranchId, Carbon::parse('2026-08-01 09:30:00', 'Asia/Bangkok')->utc(), 'branch_id', false);
            self::fail('Expected closed business-hour window to reject.');
        } catch (ValidationException $e) {
            self::assertSame('Waiting list is only available while the branch is open.', $e->errors()['branch_id'][0] ?? null);
        }

        try {
            $service->assertWaitingListEligible($openWindowBranchId, Carbon::parse('2026-08-01 15:15:00', 'Asia/Bangkok')->utc(), 'branch_id', false);
            self::fail('Expected closure window to reject.');
        } catch (ValidationException $e) {
            self::assertSame('Waiting list is unavailable because the branch is closed: Private event.', $e->errors()['branch_id'][0] ?? null);
        }
    }

    /**
     * @return array<int, array{day_of_week:int,periods:array<int, array{start_time:string,end_time:string}>>>
     */
    private function dailyBusinessHours(string $startTime, string $endTime): array
    {
        return collect(range(0, 6))
            ->map(static fn (int $day): array => [
                'day_of_week' => $day,
                'periods' => [[
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]],
            ])
            ->all();
    }
}
