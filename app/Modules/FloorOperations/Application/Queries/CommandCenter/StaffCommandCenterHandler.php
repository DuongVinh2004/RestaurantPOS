<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\Queries\CommandCenter;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregation of pending staff actions across all operational domains.
 * No transactional state is modified here.
 */
class StaffCommandCenterHandler
{
    private const HARD_CAP = 100;

    /**
     * @param  list<int>  $branchIds
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $branchIds, array $filters): array
    {
        $limit = min((int) ($filters['limit'] ?? 50), self::HARD_CAP);
        $priorityFilter = $filters['priority'] ?? null;
        $typeFilter = $filters['type'] ?? null;
        $horizonHours = max(1, min((int) ($filters['horizon_hours'] ?? 24), 168)); // max 7 days

        $horizonAt = Carbon::now()->addHours($horizonHours);
        $now = Carbon::now();

        $actions = [];

        $allowedTypes = $typeFilter !== null ? [(string) $typeFilter] : null;

        // 1. reservation_upcoming — Confirmed reservations starting in the next horizon window
        if ($this->typeAllowed('reservation_upcoming', $allowedTypes)) {
            $upcoming = DB::table('reservations as r')
                ->select([
                    'r.reservation_id',
                    'r.reservation_code',
                    'r.guest_name',
                    'r.guest_count',
                    'r.start_time',
                    'r.branch_id',
                    'r.status',
                    'r.deposit_status',
                ])
                ->whereIn('r.status', ['Confirmed', 'Reserved'])
                ->where('r.start_time', '>=', $now->toDateTimeString())
                ->where('r.start_time', '<=', $horizonAt->toDateTimeString())
                ->whereIn('r.branch_id', $branchIds)
                ->orderBy('r.start_time')
                ->limit($limit)
                ->get();

            foreach ($upcoming as $row) {
                $actions[] = $this->buildAction(
                    type: 'reservation_upcoming',
                    priority: 'normal',
                    entityType: 'reservation',
                    entityId: (int) $row->reservation_id,
                    branchId: (int) $row->branch_id,
                    title: "Upcoming: {$row->guest_name} ({$row->guest_count} guests)",
                    description: "Reservation #{$row->reservation_code} arriving at ".Carbon::parse($row->start_time)->format('H:i'),
                    dueAt: $row->start_time,
                    deepLink: "/reservations/{$row->reservation_id}",
                    meta: ['reservation_code' => $row->reservation_code, 'status' => $row->status],
                );
            }
        }

        // 2. reservation_needs_check_in — Reservations past start time but not checked in
        if ($this->typeAllowed('reservation_needs_check_in', $allowedTypes)) {
            $needsCheckIn = DB::table('reservations as r')
                ->select(['r.reservation_id', 'r.reservation_code', 'r.guest_name', 'r.guest_count', 'r.start_time', 'r.branch_id'])
                ->whereIn('r.status', ['Confirmed', 'Reserved'])
                ->whereNull('r.checked_in_at')
                ->where('r.start_time', '<', $now->toDateTimeString())
                ->whereIn('r.branch_id', $branchIds)
                ->orderBy('r.start_time')
                ->limit($limit)
                ->get();

            foreach ($needsCheckIn as $row) {
                $actions[] = $this->buildAction(
                    type: 'reservation_needs_check_in',
                    priority: 'high',
                    entityType: 'reservation',
                    entityId: (int) $row->reservation_id,
                    branchId: (int) $row->branch_id,
                    title: "Check-in overdue: {$row->guest_name}",
                    description: "Reservation #{$row->reservation_code} was due at ".Carbon::parse($row->start_time)->format('H:i')." and has not checked in.",
                    dueAt: $row->start_time,
                    deepLink: "/reservations/{$row->reservation_id}",
                    meta: [],
                );
            }
        }

        // 3. deposit_pending — Reservations requiring deposit that haven't been paid
        if ($this->typeAllowed('deposit_pending', $allowedTypes)) {
            $depositPending = DB::table('reservations as r')
                ->select(['r.reservation_id', 'r.reservation_code', 'r.guest_name', 'r.deposit_required_amount', 'r.deposit_paid_amount', 'r.bill_currency', 'r.start_time', 'r.branch_id'])
                ->whereIn('r.status', ['Confirmed', 'Reserved'])
                ->whereIn('r.deposit_status', ['Pending', 'PartiallyPaid'])
                ->whereIn('r.branch_id', $branchIds)
                ->orderBy('r.start_time')
                ->limit($limit)
                ->get();

            foreach ($depositPending as $row) {
                $outstanding = (float) $row->deposit_required_amount - (float) $row->deposit_paid_amount;
                $actions[] = $this->buildAction(
                    type: 'deposit_pending',
                    priority: 'high',
                    entityType: 'reservation',
                    entityId: (int) $row->reservation_id,
                    branchId: (int) $row->branch_id,
                    title: "Deposit required: {$row->guest_name}",
                    description: "Reservation #{$row->reservation_code} needs deposit of ".number_format($outstanding, 0)." {$row->bill_currency}.",
                    dueAt: $row->start_time,
                    deepLink: "/reservations/{$row->reservation_id}",
                    meta: [
                        'deposit_required_amount' => $row->deposit_required_amount,
                        'deposit_paid_amount' => $row->deposit_paid_amount,
                        'currency' => $row->bill_currency,
                    ],
                );
            }
        }

        // 4. deposit_expired — Deposit intent expired/revoked but reservation still active
        if ($this->typeAllowed('deposit_expired', $allowedTypes)) {
            $depositExpired = DB::table('reservations as r')
                ->select(['r.reservation_id', 'r.reservation_code', 'r.guest_name', 'r.deposit_required_amount', 'r.bill_currency', 'r.start_time', 'r.branch_id'])
                ->whereIn('r.status', ['Confirmed', 'Reserved'])
                ->where('r.deposit_status', 'Pending')
                ->whereIn('r.deposit_intent_status', ['Revoked', 'Expired'])
                ->whereIn('r.branch_id', $branchIds)
                ->orderBy('r.start_time')
                ->limit($limit)
                ->get();

            foreach ($depositExpired as $row) {
                $actions[] = $this->buildAction(
                    type: 'deposit_expired',
                    priority: 'high',
                    entityType: 'reservation',
                    entityId: (int) $row->reservation_id,
                    branchId: (int) $row->branch_id,
                    title: "Deposit intent expired: {$row->guest_name}",
                    description: "Reservation #{$row->reservation_code} deposit intent lapsed. Follow up with guest.",
                    dueAt: $row->start_time,
                    deepLink: "/reservations/{$row->reservation_id}",
                    meta: [
                        'deposit_required_amount' => $row->deposit_required_amount,
                        'currency' => $row->bill_currency,
                    ],
                );
            }
        }

        // 5. preorder_pending — Reservations with preorders awaiting staff confirmation
        if ($this->typeAllowed('preorder_pending', $allowedTypes)) {
            $preorderPending = DB::table('reservation_orders as ro')
                ->join('reservations as r', 'r.reservation_id', '=', 'ro.reservation_id')
                ->select(['ro.order_id', 'r.reservation_id', 'r.reservation_code', 'r.guest_name', 'r.start_time', 'r.branch_id'])
                ->where('ro.order_type', 'PreOrder')
                ->where('ro.status', 'Active')
                ->whereIn('r.status', ['Confirmed', 'Reserved'])
                ->whereIn('r.branch_id', $branchIds)
                ->orderBy('r.start_time')
                ->limit($limit)
                ->get();

            foreach ($preorderPending as $row) {
                $actions[] = $this->buildAction(
                    type: 'preorder_pending',
                    priority: 'normal',
                    entityType: 'reservation',
                    entityId: (int) $row->reservation_id,
                    branchId: (int) $row->branch_id,
                    title: "Preorder awaiting confirmation: {$row->guest_name}",
                    description: "Reservation #{$row->reservation_code} has a preorder that needs staff review.",
                    dueAt: $row->start_time,
                    deepLink: "/reservations/{$row->reservation_id}/preorder",
                    meta: ['order_id' => $row->order_id],
                );
            }
        }

        // 6. bill_payment_pending — Bills generated but not yet paid
        if ($this->typeAllowed('bill_payment_pending', $allowedTypes)) {
            $billPending = DB::table('reservations as r')
                ->select(['r.reservation_id', 'r.reservation_code', 'r.guest_name', 'r.final_bill_amount', 'r.bill_currency', 'r.billed_at', 'r.branch_id'])
                ->whereIn('r.status', ['Confirmed', 'Reserved'])
                ->whereNotNull('r.billed_at')
                ->whereNotNull('r.final_bill_amount')
                ->whereIn('r.branch_id', $branchIds)
                ->orderBy('r.billed_at')
                ->limit($limit)
                ->get();

            foreach ($billPending as $row) {
                $actions[] = $this->buildAction(
                    type: 'bill_payment_pending',
                    priority: 'high',
                    entityType: 'reservation',
                    entityId: (int) $row->reservation_id,
                    branchId: (int) $row->branch_id,
                    title: "Payment pending: {$row->guest_name}",
                    description: "Bill of ".number_format((float) $row->final_bill_amount, 0)." {$row->bill_currency} awaiting payment for #{$row->reservation_code}.",
                    dueAt: $row->billed_at,
                    deepLink: "/reservations/{$row->reservation_id}",
                    meta: [
                        'final_bill_amount' => $row->final_bill_amount,
                        'currency' => $row->bill_currency,
                    ],
                );
            }
        }

        // 7. checkout_pending — Orders in Completed state but reservation not checked out
        if ($this->typeAllowed('checkout_pending', $allowedTypes)) {
            $checkoutPending = DB::table('reservations as r')
                ->select(['r.reservation_id', 'r.reservation_code', 'r.guest_name', 'r.final_bill_amount', 'r.bill_currency', 'r.branch_id'])
                ->whereIn('r.status', ['Confirmed', 'Reserved'])
                ->whereNotNull('r.checked_in_at')
                ->whereNull('r.checked_out_at')
                ->whereNotNull('r.billed_at')
                ->whereIn('r.branch_id', $branchIds)
                ->orderBy('r.checked_in_at')
                ->limit($limit)
                ->get();

            foreach ($checkoutPending as $row) {
                $actions[] = $this->buildAction(
                    type: 'checkout_pending',
                    priority: 'normal',
                    entityType: 'reservation',
                    entityId: (int) $row->reservation_id,
                    branchId: (int) $row->branch_id,
                    title: "Checkout pending: {$row->guest_name}",
                    description: "Reservation #{$row->reservation_code} billed and awaiting final checkout.",
                    dueAt: null,
                    deepLink: "/reservations/{$row->reservation_id}",
                    meta: [
                        'final_bill_amount' => $row->final_bill_amount,
                        'currency' => $row->bill_currency,
                    ],
                );
            }
        }

        // 8. waiting_list_pending — Active waiting list entries
        if ($this->typeAllowed('waiting_list_pending', $allowedTypes)) {
            $waitingPending = DB::table('waiting_list as wl')
                ->select(['wl.waiting_id', 'wl.guest_name', 'wl.guest_count', 'wl.requested_at', 'wl.branch_id', 'wl.phone'])
                ->whereIn('wl.status', ['Waiting'])
                ->whereIn('wl.branch_id', $branchIds)
                ->orderBy('wl.requested_at')
                ->limit($limit)
                ->get();

            foreach ($waitingPending as $row) {
                $actions[] = $this->buildAction(
                    type: 'waiting_list_pending',
                    priority: 'normal',
                    entityType: 'waiting_list',
                    entityId: (int) $row->waiting_id,
                    branchId: (int) $row->branch_id,
                    title: "Waiting: {$row->guest_name} ({$row->guest_count} guests)",
                    description: "In queue since ".Carbon::parse($row->requested_at)->format('H:i')." — needs table assignment.",
                    dueAt: $row->requested_at,
                    deepLink: '/waiting-list',
                    meta: ['phone' => $row->phone],
                );
            }
        }

        // Apply priority filter
        if ($priorityFilter !== null) {
            $actions = array_values(array_filter($actions, fn ($a) => $a['priority'] === $priorityFilter));
        }

        // Cap final list
        $actions = array_slice($actions, 0, $limit);

        // Build summary
        $summary = [
            'open_actions' => count($actions),
            'high_priority' => count(array_filter($actions, fn ($a) => $a['priority'] === 'high')),
            'deposit_pending' => count(array_filter($actions, fn ($a) => in_array($a['type'], ['deposit_pending', 'deposit_expired'], true))),
            'preorder_pending' => count(array_filter($actions, fn ($a) => $a['type'] === 'preorder_pending')),
            'payment_pending' => count(array_filter($actions, fn ($a) => in_array($a['type'], ['bill_payment_pending', 'checkout_pending'], true))),
            'reservation_upcoming' => count(array_filter($actions, fn ($a) => $a['type'] === 'reservation_upcoming')),
        ];

        return [
            'summary' => $summary,
            'actions' => $actions,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function buildAction(
        string $type,
        string $priority,
        string $entityType,
        int $entityId,
        int $branchId,
        string $title,
        string $description,
        ?string $dueAt,
        string $deepLink,
        array $meta = [],
    ): array {
        return [
            'id' => "{$type}:{$entityType}:{$entityId}",
            'type' => $type,
            'priority' => $priority,
            'status' => 'open',
            'title' => $title,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'branch_id' => $branchId,
            'due_at' => $dueAt,
            'deep_link' => $deepLink,
            'meta' => $meta,
        ];
    }

    /**
     * @param  list<string>|null  $allowedTypes
     */
    private function typeAllowed(string $type, ?array $allowedTypes): bool
    {
        if ($allowedTypes === null) {
            return true;
        }

        return in_array($type, $allowedTypes, true);
    }
}
