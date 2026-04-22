<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\WaitingListCustomerResponseStatus;
use App\Enums\WaitingListStatus;
use App\Modules\Waitlist\Domain\Models\WaitlistEntry;
use App\Modules\Waitlist\Domain\StateMachines\WaitlistInvitationStateMachine;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WaitingListStateMachineTest extends TestCase
{
    public function test_waiting_list_transition_matrix_rejects_terminal_backward_paths(): void
    {
        $cases = [
            [WaitingListStatus::Waiting, WaitingListStatus::Notified, true],
            [WaitingListStatus::Waiting, WaitingListStatus::Cancelled, true],
            [WaitingListStatus::Waiting, WaitingListStatus::Seated, false],
            [WaitingListStatus::Notified, WaitingListStatus::Waiting, true],
            [WaitingListStatus::Notified, WaitingListStatus::Seated, true],
            [WaitingListStatus::Notified, WaitingListStatus::Cancelled, true],
            [WaitingListStatus::Seated, WaitingListStatus::Waiting, false],
            [WaitingListStatus::Cancelled, WaitingListStatus::Notified, false],
        ];

        foreach ($cases as [$from, $to, $expected]) {
            self::assertSame(
                $expected,
                WaitlistInvitationStateMachine::canTransition($from, $to),
                sprintf('Unexpected waiting list transition result for %s -> %s.', $from->value, $to->value),
            );
        }
    }

    public function test_apply_expired_to_waiting_clears_notify_and_customer_response_fields(): void
    {
        $entry = new WaitlistEntry;
        $entry->status = WaitingListStatus::Notified;
        $entry->notified_at = Carbon::parse('2026-03-21T10:00:00Z');
        $entry->notify_expires_at = Carbon::parse('2026-03-21T10:10:00Z');
        $entry->notified_by = 9;
        $entry->customer_response_status = 'Accepted';
        $entry->customer_responded_at = Carbon::parse('2026-03-21T10:01:00Z');
        $entry->customer_confirmed_arrival_at = Carbon::parse('2026-03-21T10:05:00Z');

        WaitlistInvitationStateMachine::applyExpiredToWaiting($entry, null);

        self::assertSame(WaitingListStatus::Waiting, $entry->status);
        self::assertNull($entry->notified_at);
        self::assertNull($entry->notify_expires_at);
        self::assertNull($entry->notified_by);
        self::assertNull($entry->customer_response_status);
        self::assertNull($entry->customer_responded_at);
        self::assertNull($entry->customer_confirmed_arrival_at);
    }

    public function test_apply_customer_declined_preserves_customer_response_context(): void
    {
        $entry = new WaitlistEntry;
        $entry->status = WaitingListStatus::Notified;
        $entry->notify_expires_at = Carbon::parse('2026-03-21T10:10:00Z');

        $now = Carbon::parse('2026-03-21T10:05:00Z');
        WaitlistInvitationStateMachine::applyCustomerDeclined($entry, $now, 123);

        self::assertSame(WaitingListStatus::Cancelled, $entry->status);
        self::assertSame('Declined by customer', $entry->cancel_reason);
        self::assertSame(WaitingListCustomerResponseStatus::Declined, $entry->customer_response_status);
        self::assertSame('2026-03-21T10:05:00+00:00', $entry->customer_responded_at?->toIso8601String());
        self::assertSame(123, $entry->updated_by);
    }

    public function test_assert_can_seat_rejects_expired_notify_window(): void
    {
        $entry = new WaitlistEntry;
        $entry->status = WaitingListStatus::Notified;
        $entry->notify_expires_at = Carbon::now('UTC')->subMinute();

        try {
            WaitlistInvitationStateMachine::assertCanSeat($entry, Carbon::now('UTC'));
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('notify_window', $e->errors());
        }
    }
}
