<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Application\Workflows;

use App\Enums\KitchenTicketStatus;
use App\Enums\ReservationOrderItemStatus;
use App\Modules\KitchenDispatch\Domain\Models\KitchenOrderItemTicket;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;

class KitchenTicketConsistencyInspector
{
    /**
     * @return array{
     *     lifecycle: array{
     *         status:string,
     *         state_reason:string,
     *         is_terminal:bool,
     *         allowed_actions:list<string>
     *     },
     *     reconciliation: array{
     *         sync_status:string,
     *         routing_status:string,
     *         order_item_expected_status:?string,
     *         order_item_matches_ticket:?bool,
     *         route_present:bool,
     *         route_active:?bool,
     *         station_active:?bool,
     *         station_matches_route:?bool,
     *         drift_reasons:list<string>,
     *         next_actions:list<string>
     *     }
     * }
     */
    public function describe(KitchenOrderItemTicket $ticket): array
    {
        // Snapshot nay giai thich dong thoi 2 mat: ticket dang o stage nao va routing/order item co bi lech khong.
        $ticketStatus = $this->normalizeTicketStatus($ticket);
        $orderItem = $ticket->relationLoaded('orderItem') ? $ticket->orderItem : null;
        $routeLoaded = $ticket->relationLoaded('route');
        $stationLoaded = $ticket->relationLoaded('station');
        $route = $routeLoaded ? $ticket->route : null;
        $station = $stationLoaded ? $ticket->station : null;

        $routingStatus = 'active_route';
        $routePresent = $routeLoaded ? $route !== null : $ticket->route_id !== null;
        $routeActive = $routeLoaded && $route !== null ? (bool) ($route->is_active ?? false) : null;
        $stationActive = $stationLoaded && $station !== null ? (bool) ($station->is_active ?? false) : null;
        $stationMatchesRoute = $routeLoaded && $stationLoaded && $route !== null && $station !== null
            ? ((int) $route->station_id === (int) $station->station_id)
            : null;

        // Danh gia drift routing tach rieng khoi drift trang thai de caller biet can sua route hay can dong bo order item.
        if (! $routePresent) {
            $routingStatus = 'route_missing';
        } elseif ($routeLoaded && $routeActive === false) {
            $routingStatus = 'route_inactive';
        } elseif ($stationLoaded && $station !== null && $stationActive === false) {
            $routingStatus = 'station_inactive';
        } elseif ($routeLoaded && $stationLoaded && $stationMatchesRoute === false) {
            $routingStatus = 'station_route_mismatch';
        }

        $expectedStatus = $this->expectedTicketStatus($orderItem);
        $orderItemMatchesTicket = $orderItem instanceof ReservationOrderItem
            ? $this->orderItemStatusAcceptsTicket($this->normalizeOrderItemStatus($orderItem), $ticketStatus)
            : null;

        // Ticket co the dung route nhung van lech lifecycle so voi order item, vi vay sync_status duoc tinh doc lap.
        $syncStatus = 'in_sync';
        if (! $orderItem instanceof ReservationOrderItem) {
            $syncStatus = 'order_item_missing';
        } elseif ($orderItemMatchesTicket !== true) {
            $syncStatus = 'drift_detected';
        }

        $driftReasons = [];
        if ($syncStatus === 'order_item_missing') {
            $driftReasons[] = 'order_item_missing';
        } elseif ($syncStatus === 'drift_detected') {
            $driftReasons[] = 'order_item_ticket_mismatch';
        }

        if ($routingStatus !== 'active_route') {
            $driftReasons[] = $routingStatus;
        }

        // Caller nhan ve mot mo ta trung tinh, khong mutate du lieu, de reuse cho UI, audit va reconcile batch.
        return [
            'lifecycle' => [
                'status' => $ticketStatus->value,
                'state_reason' => $ticketStatus->stateReason(),
                'is_terminal' => $ticketStatus->isTerminal(),
                'allowed_actions' => $ticketStatus->allowedActions(),
            ],
            'reconciliation' => [
                'sync_status' => $syncStatus,
                'routing_status' => $routingStatus,
                'order_item_expected_status' => $expectedStatus?->value,
                'order_item_matches_ticket' => $orderItemMatchesTicket,
                'route_present' => $routePresent,
                'route_active' => $routeActive,
                'station_active' => $stationActive,
                'station_matches_route' => $stationMatchesRoute,
                'drift_reasons' => array_values(array_unique($driftReasons)),
                'next_actions' => $this->nextActionsFor($syncStatus, $routingStatus),
            ],
        ];
    }

    public function canRedispatchActiveTicket(KitchenOrderItemTicket $ticket): bool
    {
        // Redispatch chi an toan khi ticket dang sach ca ve lifecycle lan routing.
        $description = $this->describe($ticket);

        return ($description['reconciliation']['sync_status'] ?? 'drift_detected') === 'in_sync'
            && ($description['reconciliation']['routing_status'] ?? 'route_missing') === 'active_route';
    }

    public function expectedTicketStatus(?ReservationOrderItem $orderItem): ?KitchenTicketStatus
    {
        // Helper nay bien order item status thanh trang thai ticket du kien de detect drift.
        if (! $orderItem instanceof ReservationOrderItem) {
            return null;
        }

        return match ($this->normalizeOrderItemStatus($orderItem)) {
            ReservationOrderItemStatus::InProgress => KitchenTicketStatus::Fired,
            ReservationOrderItemStatus::Served => KitchenTicketStatus::Completed,
            ReservationOrderItemStatus::Cancelled => KitchenTicketStatus::Cancelled,
            ReservationOrderItemStatus::Ordered => KitchenTicketStatus::Queued,
        };
    }

    private function normalizeTicketStatus(KitchenOrderItemTicket $ticket): KitchenTicketStatus
    {
        return $ticket->ticket_status instanceof KitchenTicketStatus
            ? $ticket->ticket_status
            : KitchenTicketStatus::from((string) $ticket->ticket_status);
    }

    private function normalizeOrderItemStatus(ReservationOrderItem $orderItem): ReservationOrderItemStatus
    {
        return $orderItem->status instanceof ReservationOrderItemStatus
            ? $orderItem->status
            : ReservationOrderItemStatus::from((string) $orderItem->status);
    }

    private function orderItemStatusAcceptsTicket(
        ReservationOrderItemStatus $orderItemStatus,
        KitchenTicketStatus $ticketStatus,
    ): bool {
        // Cho phep mot vai cap hop le nhu InProgress <-> Fired/Ready de kitchen khong bi coi la drift gia.
        $acceptable = match ($orderItemStatus) {
            ReservationOrderItemStatus::Ordered => [KitchenTicketStatus::Queued],
            ReservationOrderItemStatus::InProgress => [KitchenTicketStatus::Fired, KitchenTicketStatus::Ready],
            ReservationOrderItemStatus::Served => [KitchenTicketStatus::Completed],
            ReservationOrderItemStatus::Cancelled => [KitchenTicketStatus::Cancelled],
        };

        return in_array($ticketStatus, $acceptable, true);
    }

    /**
     * @return list<string>
     */
    private function nextActionsFor(string $syncStatus, string $routingStatus): array
    {
        // Reconciliation chi tra ve goi y hanh dong tiep theo, khong tu sua state o day.
        $actions = [];

        if ($syncStatus === 'order_item_missing') {
            $actions[] = 'run_kitchen_reconciliation';
        }

        if ($syncStatus === 'drift_detected') {
            $actions[] = 'reload_order_item_state';
            $actions[] = 'run_kitchen_reconciliation';
        }

        if ($routingStatus !== 'active_route') {
            $actions[] = 'review_station_routes';
            $actions[] = 'run_kitchen_reconciliation';
        }

        return array_values(array_unique($actions));
    }
}
