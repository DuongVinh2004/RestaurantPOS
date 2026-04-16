<?php

declare(strict_types=1);

namespace App\Support\AuditTrail;

use App\Support\Money;

final class LegacyAuditPayloadFactory
{
    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    public function make(string $eventName, array $context): ?array
    {
        return match ($eventName) {
            'reservation_created' => $this->reservationCreated($context),
            'reservation_status_changed' => $this->reservationStatusChanged($context),
            'staff.reservation.rescheduled',
            'customer.reservation.rescheduled',
            'customer.session_reservation.rescheduled' => $this->reservationRescheduled($context),
            'staff.reservation.checked_in' => $this->reservationCheckedIn($context),
            'staff.reservation.table_moved' => $this->reservationTableMoved($context),
            'staff.table.released' => $this->tableReleased($context),
            'staff.waiting_list.created',
            'customer.waiting_list.created' => $this->waitingListCreated($context),
            'staff.waiting_list.notified' => $this->waitingListNotified($context),
            'customer.waiting_list.accepted' => $this->waitingListAccepted($context),
            'customer.waiting_list.declined' => $this->waitingListDeclined($context),
            'customer.waiting_list.arrival_confirmed' => $this->waitingListArrivalConfirmed($context),
            'staff.waiting_list.seated' => $this->waitingListSeated($context),
            'staff.waiting_list.cancelled',
            'customer.waiting_list.cancelled' => $this->waitingListCancelled($context),
            'staff.reservation.voucher_applied' => $this->reservationVoucherApplied($context),
            'staff.reservation.voucher_removed' => $this->reservationVoucherRemoved($context),
            'loyalty_points_redeemed' => $this->loyaltyRedeemed($context),
            'loyalty_redemption_released' => $this->loyaltyReleased($context),
            'staff_order_payment_recorded' => $this->finalPaymentRecorded($context),
            'staff.reservation.payment_refunded' => $this->paymentRefunded($context),
            'staff.reservation.refund_cancelled' => $this->refundCancelled($context),
            'staff.cashier_shift.opened' => $this->cashierShiftOpened($context),
            'staff.cashier_shift.closed' => $this->cashierShiftClosed($context),
            'payment_provider_webhook' => $this->paymentWebhook($context),
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function reservationCreated(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        if ($reservationId === null) {
            return null;
        }

        return [
            'action' => 'reservation.created',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'subjects' => array_merge(
                $this->subjectsFromIds('restaurant_table', $context['table_ids'] ?? [], 'table'),
                $this->subjectFromScalar('user', $context['user_id'] ?? null, 'customer')
            ),
            'after' => [
                'reservation_code' => $this->stringOrNull($context['reservation_code'] ?? null),
                'source' => $this->stringOrNull($context['source'] ?? null),
                'start_time_utc' => $this->stringOrNull($context['start_time_utc'] ?? null),
                'end_time_utc' => $this->stringOrNull($context['end_time_utc'] ?? null),
                'hold_id' => $this->stringOrNull($context['hold_id'] ?? null),
                'table_ids' => $this->normalizeIdList($context['table_ids'] ?? []),
            ],
            'summary' => [
                'source' => $this->stringOrNull($context['source'] ?? null),
                'table_count' => count($this->normalizeIdList($context['table_ids'] ?? [])),
                'hold_id_present' => $this->stringOrNull($context['hold_id'] ?? null) !== null,
            ],
            'actor' => $this->staffActor($context['actor_user_id'] ?? null)
                ?? $this->customerActor($context['user_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function reservationStatusChanged(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        $from = $this->stringOrNull($context['from'] ?? null);
        $to = $this->stringOrNull($context['to'] ?? null);
        if ($reservationId === null || $from === null || $to === null) {
            return null;
        }

        return [
            'action' => 'reservation.status_changed',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'before' => [
                'status' => $from,
                'row_version' => $this->intOrNull($context['before_row_version'] ?? null),
            ],
            'after' => [
                'status' => $to,
                'row_version' => $this->intOrNull($context['new_row_version'] ?? null),
                'cancel_reason' => $this->stringOrNull($context['cancel_reason'] ?? null),
            ],
            'summary' => [
                'from_status' => $from,
                'to_status' => $to,
                'force' => (bool) ($context['force'] ?? false),
                'cancel_reason' => $this->stringOrNull($context['cancel_reason'] ?? null),
            ],
            'actor' => $this->staffActor($context['actor_user_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function reservationRescheduled(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        $changeSet = is_array($context['change_set'] ?? null) ? $context['change_set'] : [];
        if ($reservationId === null || $changeSet === []) {
            return null;
        }

        $beforeTableIds = $this->normalizeIdList($changeSet['previous_table_ids'] ?? []);
        $afterTableIds = $this->normalizeIdList($changeSet['new_table_ids'] ?? []);

        return [
            'action' => 'reservation.rescheduled',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'subjects' => $this->subjectsFromIds(
                'restaurant_table',
                array_values(array_unique(array_merge($beforeTableIds, $afterTableIds))),
                'table'
            ),
            'before' => [
                'start_time_utc' => $this->stringOrNull($changeSet['previous_start_time_utc'] ?? null),
                'end_time_utc' => $this->stringOrNull($changeSet['previous_end_time_utc'] ?? null),
                'guest_count' => $this->intOrNull($changeSet['previous_guest_count'] ?? null),
                'notes' => $this->stringOrNull($changeSet['previous_notes'] ?? null),
                'table_ids' => $beforeTableIds,
                'row_version' => $this->intOrNull($context['before_row_version'] ?? null),
            ],
            'after' => [
                'start_time_utc' => $this->stringOrNull($changeSet['new_start_time_utc'] ?? null),
                'end_time_utc' => $this->stringOrNull($changeSet['new_end_time_utc'] ?? null),
                'guest_count' => $this->intOrNull($changeSet['new_guest_count'] ?? null),
                'notes' => $this->stringOrNull($changeSet['new_notes'] ?? null),
                'table_ids' => $afterTableIds,
                'row_version' => $this->intOrNull($context['new_row_version'] ?? null),
            ],
            'summary' => [
                'changed_fields' => array_values(array_filter(array_map(
                    fn (mixed $value): ?string => $this->stringOrNull($value),
                    (array) ($changeSet['changed_fields'] ?? [])
                ))),
                'reason' => $this->stringOrNull($changeSet['reason'] ?? null),
                'time_changed' => (bool) ($context['time_changed'] ?? false),
                'guest_changed' => (bool) ($context['guest_changed'] ?? false),
                'table_changed' => (bool) ($context['table_changed'] ?? false),
                'notes_changed' => (bool) ($context['notes_changed'] ?? false),
            ],
            'actor' => $this->staffActor($context['staff_user_id'] ?? null)
                ?? $this->customerActor($context['customer_user_id'] ?? null, $context['customer_session_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function reservationCheckedIn(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        if ($reservationId === null) {
            return null;
        }

        $tableIds = $this->normalizeIdList($context['table_ids'] ?? []);

        return [
            'action' => 'reservation.checked_in',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'subjects' => $this->subjectsFromIds('restaurant_table', $tableIds, 'table'),
            'before' => [
                'status' => 'Confirmed',
            ],
            'after' => [
                'status' => 'Reserved',
                'checked_in_at' => $this->stringOrNull($context['checked_in_at'] ?? null),
                'table_ids' => $tableIds,
            ],
            'summary' => [
                'checked_in_at' => $this->stringOrNull($context['checked_in_at'] ?? null),
                'table_count' => count($tableIds),
            ],
            'actor' => $this->staffActor($context['staff_user_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function reservationTableMoved(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        $fromTableId = $this->stringId($context['from_table_id'] ?? null);
        $toTableId = $this->stringId($context['to_table_id'] ?? null);
        if ($reservationId === null || $fromTableId === null || $toTableId === null) {
            return null;
        }

        $afterTableIds = $this->normalizeIdList($context['table_ids_after'] ?? []);

        return [
            'action' => 'reservation.table_moved',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'subjects' => array_merge(
                [['type' => 'restaurant_table', 'id' => $fromTableId, 'role' => 'from_table']],
                [['type' => 'restaurant_table', 'id' => $toTableId, 'role' => 'to_table']],
                $this->subjectsFromIds('restaurant_table', $afterTableIds, 'table')
            ),
            'before' => [
                'from_table_id' => $fromTableId,
            ],
            'after' => [
                'to_table_id' => $toTableId,
                'table_ids' => $afterTableIds,
                'moved_at' => $this->stringOrNull($context['moved_at'] ?? null),
            ],
            'summary' => [
                'from_table_id' => $fromTableId,
                'to_table_id' => $toTableId,
                'moved_at' => $this->stringOrNull($context['moved_at'] ?? null),
            ],
            'actor' => $this->staffActor($context['staff_user_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function tableReleased(array $context): ?array
    {
        $tableId = $this->stringId($context['table_id'] ?? null);
        if ($tableId === null) {
            return null;
        }

        return [
            'action' => 'table.released',
            'entity_type' => 'restaurant_table',
            'entity_id' => $tableId,
            'after' => [
                'status' => $this->stringOrNull($context['result_status'] ?? null),
            ],
            'summary' => [
                'force' => (bool) ($context['force'] ?? false),
                'result_status' => $this->stringOrNull($context['result_status'] ?? null),
                'notes_present' => $this->stringOrNull($context['notes'] ?? null) !== null,
            ],
            'actor' => $this->staffActor($context['staff_user_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function waitingListCreated(array $context): ?array
    {
        $waitingId = $this->stringId($context['waiting_id'] ?? null);
        if ($waitingId === null) {
            return null;
        }

        return [
            'action' => 'waiting_list.created',
            'entity_type' => 'waiting_list',
            'entity_id' => $waitingId,
            'subjects' => $this->subjectFromScalar('user', $context['user_id'] ?? ($context['owner_user_id'] ?? null), 'customer'),
            'after' => [
                'status' => 'Waiting',
                'guest_count' => $this->intOrNull($context['guest_count'] ?? null),
            ],
            'summary' => [
                'guest_count' => $this->intOrNull($context['guest_count'] ?? null),
                'owner_type' => $this->ownerType($context),
            ],
            'actor' => $this->staffActor($context['staff_user_id'] ?? null)
                ?? $this->customerActor($context['user_id'] ?? ($context['owner_user_id'] ?? null), $context['customer_session_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function waitingListNotified(array $context): ?array
    {
        $waitingId = $this->stringId($context['waiting_id'] ?? null);
        $tableId = $this->stringId($context['table_id'] ?? null);
        if ($waitingId === null || $tableId === null) {
            return null;
        }

        return [
            'action' => 'waiting_list.notified',
            'entity_type' => 'waiting_list',
            'entity_id' => $waitingId,
            'subjects' => [
                ['type' => 'restaurant_table', 'id' => $tableId, 'role' => 'table'],
            ],
            'before' => [
                'status' => 'Waiting',
            ],
            'after' => [
                'status' => 'Notified',
                'table_id' => $tableId,
                'hold_id' => $this->stringOrNull($context['hold_id'] ?? null),
                'notify_expires_at' => $this->stringOrNull($context['notify_expires_at'] ?? null),
            ],
            'summary' => [
                'table_id' => $tableId,
                'hold_id' => $this->stringOrNull($context['hold_id'] ?? null),
                'notify_expires_at' => $this->stringOrNull($context['notify_expires_at'] ?? null),
            ],
            'actor' => $this->staffActor($context['staff_user_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function waitingListAccepted(array $context): ?array
    {
        return $this->waitingListCustomerResponse(
            context: $context,
            action: 'waiting_list.accepted',
            after: [
                'customer_response_status' => 'Accepted',
                'notify_expires_at' => $this->stringOrNull($context['notify_expires_at'] ?? null),
            ],
        );
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function waitingListDeclined(array $context): ?array
    {
        return $this->waitingListCustomerResponse(
            context: $context,
            action: 'waiting_list.declined',
            after: [
                'status' => 'Cancelled',
                'customer_response_status' => 'Declined',
            ],
        );
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function waitingListArrivalConfirmed(array $context): ?array
    {
        return $this->waitingListCustomerResponse(
            context: $context,
            action: 'waiting_list.arrival_confirmed',
            after: [
                'customer_response_status' => 'Accepted',
                'arrival_confirmed' => true,
            ],
        );
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function waitingListSeated(array $context): ?array
    {
        $waitingId = $this->stringId($context['waiting_id'] ?? null);
        if ($waitingId === null) {
            return null;
        }

        $tableIds = $this->normalizeIdList($context['table_ids'] ?? []);
        $reservationId = $this->stringId($context['reservation_id'] ?? null);

        return [
            'action' => 'waiting_list.seated',
            'entity_type' => 'waiting_list',
            'entity_id' => $waitingId,
            'subjects' => array_merge(
                $this->subjectsFromIds('restaurant_table', $tableIds, 'table'),
                $reservationId !== null ? [['type' => 'reservation', 'id' => $reservationId, 'role' => 'reservation']] : []
            ),
            'before' => [
                'status' => 'Notified',
                'hold_id' => $this->stringOrNull($context['hold_id'] ?? null),
            ],
            'after' => [
                'status' => 'Seated',
                'reservation_id' => $reservationId,
                'table_ids' => $tableIds,
            ],
            'summary' => [
                'reservation_id' => $reservationId,
                'table_count' => count($tableIds),
                'hold_id' => $this->stringOrNull($context['hold_id'] ?? null),
            ],
            'actor' => $this->staffActor($context['staff_user_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function waitingListCancelled(array $context): ?array
    {
        $waitingId = $this->stringId($context['waiting_id'] ?? null);
        if ($waitingId === null) {
            return null;
        }

        return [
            'action' => 'waiting_list.cancelled',
            'entity_type' => 'waiting_list',
            'entity_id' => $waitingId,
            'after' => [
                'status' => 'Cancelled',
                'cancel_reason' => $this->stringOrNull($context['cancel_reason'] ?? null),
            ],
            'summary' => [
                'cancel_reason' => $this->stringOrNull($context['cancel_reason'] ?? null),
                'owner_type' => $this->ownerType($context),
            ],
            'actor' => $this->staffActor($context['staff_user_id'] ?? null)
                ?? $this->customerActor($context['user_id'] ?? ($context['owner_user_id'] ?? null), $context['customer_session_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function reservationVoucherApplied(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        if ($reservationId === null) {
            return null;
        }

        return [
            'action' => 'reservation.voucher.applied',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'subjects' => array_merge(
                $this->subjectFromScalar('user_voucher', $context['user_voucher_id'] ?? null, 'user_voucher'),
                $this->subjectFromScalar('voucher', $context['voucher_id'] ?? null, 'voucher')
            ),
            'after' => [
                'voucher_id' => $this->intOrNull($context['voucher_id'] ?? null),
                'user_voucher_id' => $this->intOrNull($context['user_voucher_id'] ?? null),
                'voucher_code' => $this->stringOrNull($context['voucher_code'] ?? null),
                'discount_amount' => $this->floatOrNull($context['discount_amount'] ?? null),
            ],
            'summary' => [
                'voucher_code' => $this->stringOrNull($context['voucher_code'] ?? null),
                'discount_amount' => $this->floatOrNull($context['discount_amount'] ?? null),
                'subtotal' => $this->floatOrNull($context['subtotal'] ?? null),
            ],
            'actor' => $this->staffOrCustomerActor(
                $context['actor_user_id'] ?? null,
                $context['user_id'] ?? null,
            ),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function reservationVoucherRemoved(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        if ($reservationId === null) {
            return null;
        }

        return [
            'action' => 'reservation.voucher.removed',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'subjects' => array_merge(
                $this->subjectFromScalar('user_voucher', $context['user_voucher_id'] ?? null, 'user_voucher'),
                $this->subjectFromScalar('voucher', $context['voucher_id'] ?? null, 'voucher')
            ),
            'after' => [
                'voucher_removed' => true,
                'voucher_id' => $this->intOrNull($context['voucher_id'] ?? null),
                'user_voucher_id' => $this->intOrNull($context['user_voucher_id'] ?? null),
            ],
            'summary' => [
                'voucher_code' => $this->stringOrNull($context['voucher_code'] ?? null),
            ],
            'actor' => $this->staffOrCustomerActor(
                $context['actor_user_id'] ?? null,
                $context['user_id'] ?? null,
            ),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function loyaltyRedeemed(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        $userId = $this->stringId($context['user_id'] ?? null);
        if ($reservationId === null || $userId === null) {
            return null;
        }

        return [
            'action' => 'reservation.loyalty.redeemed',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'subjects' => [
                ['type' => 'user', 'id' => $userId, 'role' => 'customer'],
            ],
            'summary' => [
                'points' => $this->intOrNull($context['points'] ?? null),
                'amount_basis' => $this->floatOrNull($context['amount_basis'] ?? null),
                'remaining_points' => $this->intOrNull($context['remaining_points'] ?? null),
            ],
            'actor' => $this->staffOrCustomerActor(
                $context['actor_user_id'] ?? null,
                $context['user_id'] ?? null,
            ),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function loyaltyReleased(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        $userId = $this->stringId($context['user_id'] ?? null);
        if ($reservationId === null || $userId === null) {
            return null;
        }

        return [
            'action' => 'reservation.loyalty.released',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'subjects' => [
                ['type' => 'user', 'id' => $userId, 'role' => 'customer'],
            ],
            'summary' => [
                'released_points' => $this->intOrNull($context['released_points'] ?? null),
                'released_amount' => $this->floatOrNull($context['released_amount'] ?? null),
                'reason' => $this->stringOrNull($context['reason'] ?? null),
            ],
            'actor' => $this->staffOrCustomerActor(
                $context['actor_user_id'] ?? null,
                $context['user_id'] ?? null,
            ),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function finalPaymentRecorded(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        $orderId = $this->stringId($context['order_id'] ?? null);
        $paymentId = $this->stringId($context['payment_id'] ?? null);
        if ($reservationId === null || $orderId === null || $paymentId === null) {
            return null;
        }

        $remainingDueAfter = $this->floatOrNull($context['remaining_due_after'] ?? null);

        return [
            'action' => $remainingDueAfter !== null && Money::isZeroOrNegative($remainingDueAfter)
                ? 'checkout.finalized'
                : 'payment.final_captured',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'subjects' => [
                ['type' => 'reservation_order', 'id' => $orderId, 'role' => 'order'],
                ['type' => 'payment', 'id' => $paymentId, 'role' => 'payment'],
            ],
            'summary' => [
                'payment_status' => $this->stringOrNull($context['payment_status'] ?? null),
                'paid_amount' => $this->floatOrNull($context['paid_amount'] ?? null),
                'total_due' => $this->floatOrNull($context['total_due'] ?? null),
                'remaining_due_before' => $this->floatOrNull($context['remaining_due_before'] ?? null),
                'remaining_due_after' => $remainingDueAfter,
                'currency' => $this->stringOrNull($context['currency'] ?? null),
            ],
            'actor' => $this->staffActor($context['actor_user_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function paymentRefunded(array $context): ?array
    {
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        if ($reservationId === null) {
            return null;
        }

        return [
            'action' => 'payment.refunded',
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'subjects' => $this->subjectsFromIds('payment', $context['refund_payment_ids'] ?? [], 'refund_payment'),
            'summary' => [
                'refund_scope' => $this->stringOrNull($context['refund_scope'] ?? null),
                'refund_amount' => $this->floatOrNull($context['refund_amount'] ?? null),
                'refund_reason' => $this->stringOrNull($context['refund_reason'] ?? null),
                'reservation_status' => $this->stringOrNull($context['reservation_status'] ?? null),
            ],
            'actor' => $this->staffActor($context['actor_user_id'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function refundCancelled(array $context): ?array
    {
        $payload = $this->paymentRefunded($context);
        if ($payload === null) {
            return null;
        }

        $payload['action'] = 'reservation.refund_cancelled';
        $payload['summary']['cancel_reason'] = $this->stringOrNull($context['cancel_reason'] ?? null);
        $payload['summary']['cancel_after_payment'] = true;

        return $payload;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function cashierShiftOpened(array $context): ?array
    {
        $shiftId = $this->stringId($context['cashier_shift_id'] ?? null);
        if ($shiftId === null) {
            return null;
        }

        return [
            'action' => 'cashier_shift.opened',
            'entity_type' => 'cashier_shift',
            'entity_id' => $shiftId,
            'subjects' => array_merge(
                $this->subjectFromScalar('branch', $context['branch_id'] ?? null, 'branch'),
                $this->subjectFromScalar('user', $context['cashier_user_id'] ?? null, 'cashier')
            ),
            'after' => [
                'currency' => $this->stringOrNull($context['currency'] ?? null),
                'opening_float_amount' => $this->floatOrNull($context['opening_float_amount'] ?? null),
                'terminal_code' => $this->stringOrNull($context['terminal_code'] ?? null),
            ],
            'summary' => [
                'currency' => $this->stringOrNull($context['currency'] ?? null),
                'opening_float_amount' => $this->floatOrNull($context['opening_float_amount'] ?? null),
                'terminal_code' => $this->stringOrNull($context['terminal_code'] ?? null),
            ],
            'actor' => $this->staffActor($context['opened_by'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function cashierShiftClosed(array $context): ?array
    {
        $shiftId = $this->stringId($context['cashier_shift_id'] ?? null);
        if ($shiftId === null) {
            return null;
        }

        return [
            'action' => 'cashier_shift.closed',
            'entity_type' => 'cashier_shift',
            'entity_id' => $shiftId,
            'subjects' => array_merge(
                $this->subjectFromScalar('branch', $context['branch_id'] ?? null, 'branch'),
                $this->subjectFromScalar('user', $context['cashier_user_id'] ?? null, 'cashier')
            ),
            'after' => [
                'expected_cash_amount' => $this->floatOrNull($context['expected_cash_amount'] ?? null),
                'actual_cash_amount' => $this->floatOrNull($context['actual_cash_amount'] ?? null),
                'cash_discrepancy_amount' => $this->floatOrNull($context['cash_discrepancy_amount'] ?? null),
            ],
            'summary' => [
                'expected_cash_amount' => $this->floatOrNull($context['expected_cash_amount'] ?? null),
                'actual_cash_amount' => $this->floatOrNull($context['actual_cash_amount'] ?? null),
                'cash_discrepancy_amount' => $this->floatOrNull($context['cash_discrepancy_amount'] ?? null),
                'payment_count' => $this->intOrNull($context['payment_count'] ?? null),
                'refund_count' => $this->intOrNull($context['refund_count'] ?? null),
            ],
            'actor' => $this->staffActor($context['closed_by'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function paymentWebhook(array $context): ?array
    {
        if (($context['duplicate'] ?? false) === true) {
            return null;
        }

        $receiptId = $this->stringId($context['receipt_id'] ?? null);
        if ($receiptId === null) {
            return null;
        }

        $providerCode = $this->stringOrNull($context['provider_code'] ?? null);
        $sessionType = $this->stringOrNull($context['payment_session_type'] ?? null);
        $sessionId = $this->stringId($context['payment_session_id'] ?? null);
        $reservationId = $this->stringId($context['reservation_id'] ?? null);
        $linkedPaymentId = $this->stringId($context['linked_payment_id'] ?? null);

        return [
            'action' => 'payment.webhook.processed',
            'entity_type' => 'payment_provider_webhook_receipt',
            'entity_id' => $receiptId,
            'subjects' => array_values(array_filter([
                $reservationId !== null ? ['type' => 'reservation', 'id' => $reservationId, 'role' => 'reservation'] : null,
                ($sessionType !== null && $sessionId !== null) ? ['type' => $sessionType, 'id' => $sessionId, 'role' => 'payment_session'] : null,
                $linkedPaymentId !== null ? ['type' => 'payment', 'id' => $linkedPaymentId, 'role' => 'payment'] : null,
            ])),
            'summary' => [
                'provider_code' => $providerCode,
                'provider_event_code' => $this->stringOrNull($context['provider_event_code'] ?? null),
                'provider_session_code' => $this->stringOrNull($context['provider_session_code'] ?? null),
                'payment_scope' => $this->stringOrNull($context['payment_scope'] ?? null),
                'delivery_status' => $this->stringOrNull($context['delivery_status'] ?? null),
                'ignored_reason' => $this->stringOrNull($context['ignored_reason'] ?? null),
                'failure_message' => $this->stringOrNull($context['failure_message'] ?? null),
                'kind' => $this->stringOrNull($context['kind'] ?? null),
            ],
            'actor' => $providerCode !== null
                ? [
                    'type' => 'webhook_provider',
                    'key' => 'webhook_provider:' . $providerCode,
                ]
                : null,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $after
     * @return array<string,mixed>|null
     */
    private function waitingListCustomerResponse(array $context, string $action, array $after): ?array
    {
        $waitingId = $this->stringId($context['waiting_id'] ?? null);
        if ($waitingId === null) {
            return null;
        }

        return [
            'action' => $action,
            'entity_type' => 'waiting_list',
            'entity_id' => $waitingId,
            'after' => $after,
            'summary' => [
                'owner_type' => $this->ownerType($context),
            ],
            'actor' => $this->customerActor($context['user_id'] ?? ($context['owner_user_id'] ?? null), $context['customer_session_id'] ?? null),
        ];
    }

    /**
     * @param mixed $value
     * @return array<int,array{type:string,id:string,role:string}>
     */
    private function subjectsFromIds(string $type, mixed $value, string $role): array
    {
        $subjects = [];
        foreach ($this->normalizeIdList($value) as $id) {
            $subjects[] = [
                'type' => $type,
                'id' => $id,
                'role' => $role,
            ];
        }

        return $subjects;
    }

    /**
     * @return array<int,array{type:string,id:string,role:string}>
     */
    private function subjectFromScalar(string $type, mixed $value, string $role): array
    {
        $id = $this->stringId($value);
        if ($id === null) {
            return [];
        }

        return [[
            'type' => $type,
            'id' => $id,
            'role' => $role,
        ]];
    }

    /**
     * @return array<int,string>
     */
    private function normalizeIdList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            $id = $this->stringId($item);
            if ($id === null) {
                continue;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    private function stringId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function ownerType(array $context): string
    {
        if ($this->stringId($context['user_id'] ?? ($context['owner_user_id'] ?? null)) !== null) {
            return 'customer';
        }

        if ($this->stringOrNull($context['customer_session_id'] ?? null) !== null) {
            return 'customer_session';
        }

        return 'unknown';
    }

    /**
     * @return array<string,mixed>|null
     */
    private function staffActor(mixed $userId): ?array
    {
        $id = $this->intOrNull($userId);
        if ($id === null || $id <= 0) {
            return null;
        }

        return [
            'type' => 'staff_user',
            'user_id' => $id,
            'key' => 'staff_user:' . $id,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function staffOrCustomerActor(mixed $actorUserId, mixed $customerUserId, mixed $sessionId = null): ?array
    {
        $actorId = $this->intOrNull($actorUserId);
        $customerId = $this->intOrNull($customerUserId);

        if ($customerId !== null && $customerId > 0 && ($actorId === null || $actorId === $customerId)) {
            return $this->customerActor($customerId, $sessionId);
        }

        return $this->staffActor($actorUserId) ?? $this->customerActor($customerUserId, $sessionId);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function customerActor(mixed $userId, mixed $sessionId = null): ?array
    {
        $id = $this->intOrNull($userId);
        if ($id !== null && $id > 0) {
            return [
                'type' => 'customer_account',
                'user_id' => $id,
                'key' => 'customer_user:' . $id,
            ];
        }

        $session = $this->stringOrNull($sessionId);
        if ($session === null) {
            return null;
        }

        return [
            'type' => 'customer_session',
            'key' => $session,
        ];
    }
}
