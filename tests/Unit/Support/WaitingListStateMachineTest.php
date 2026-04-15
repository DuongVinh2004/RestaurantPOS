<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\WaitingListStatus;
use App\Modules\WaitingList\Domain\Models\WaitingList;
use App\Modules\WaitingList\Domain\State\WaitingListStateMachine;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WaitingListStateMachineTest extends TestCase
{
    public function test_apply_expired_to_waiting_clears_notify_and_customer_response_fields(): void
    {
        $entry = new WaitingList();
        $entry->status = WaitingListStatus::Notified;
        $entry->notified_at = Carbon::parse('2026-03-21T10:00:00Z');
        $entry->notify_expires_at = Carbon::parse('2026-03-21T10:10:00Z');
        $entry->notified_by = 9;
        $entry->customer_response_status = 'Accepted';
        $entry->customer_responded_at = Carbon::parse('2026-03-21T10:01:00Z');
        $entry->customer_confirmed_arrival_at = Carbon::parse('2026-03-21T10:05:00Z');

        WaitingListStateMachine::applyExpiredToWaiting($entry, null);

        self::assertSame(WaitingListStatus::Waiting, $entry->status);
        self::assertNull($entry->notified_at);
        self::assertNull($entry->notify_expires_at);
        self::assertNull($entry->notified_by);
        self::assertNull($entry->customer_response_status);
        self::assertNull($entry->customer_responded_at);
        self::assertNull($entry->customer_confirmed_arrival_at);
    }

    public function test_assert_can_seat_rejects_expired_notify_window(): void
    {
        $entry = new WaitingList();
        $entry->status = WaitingListStatus::Notified;
        $entry->notify_expires_at = Carbon::now('UTC')->subMinute();

        try {
            WaitingListStateMachine::assertCanSeat($entry, Carbon::now('UTC'));
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('notify_window', $e->errors());
        }
    }
}
