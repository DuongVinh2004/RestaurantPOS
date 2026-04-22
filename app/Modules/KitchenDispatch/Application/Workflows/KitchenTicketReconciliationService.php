<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Application\Workflows;

use App\Modules\KitchenDispatch\Domain\Models\KitchenOrderItemTicket;

class KitchenTicketReconciliationService
{
    public function __construct(
        private readonly KitchenTicketConsistencyInspector $inspector,
    ) {}

    /**
     * @param  array{branch_id?:int|null,station_id?:int|null,include_terminal?:bool|null}  $filters
     * @return array{
     *     checked_count:int,
     *     drift_count:int,
     *     status_drift_count:int,
     *     routing_drift_count:int,
     *     tickets:list<array<string,mixed>>
     * }
     */
    public function scan(array $filters = []): array
    {
        $query = KitchenOrderItemTicket::query()
            ->with(['station', 'route', 'orderItem']);

        if (($filters['station_id'] ?? null) !== null) {
            $query->where('station_id', (int) $filters['station_id']);
        }

        if (($filters['branch_id'] ?? null) !== null) {
            $query->whereHas('reservation', static function ($reservationQuery) use ($filters): void {
                $reservationQuery->where('branch_id', (int) $filters['branch_id']);
            });
        }

        if (! (bool) ($filters['include_terminal'] ?? false)) {
            $query->whereNotIn('ticket_status', ['Completed', 'Cancelled']);
        }

        $tickets = $query
            ->orderBy('ticket_id')
            ->get();

        $reportRows = [];
        $driftCount = 0;
        $statusDriftCount = 0;
        $routingDriftCount = 0;

        foreach ($tickets as $ticket) {
            $description = $this->inspector->describe($ticket);
            $reconciliation = $description['reconciliation'];
            $hasStatusDrift = ($reconciliation['sync_status'] ?? 'in_sync') !== 'in_sync';
            $hasRoutingDrift = ($reconciliation['routing_status'] ?? 'active_route') !== 'active_route';

            if (! $hasStatusDrift && ! $hasRoutingDrift) {
                continue;
            }

            $driftCount++;
            if ($hasStatusDrift) {
                $statusDriftCount++;
            }
            if ($hasRoutingDrift) {
                $routingDriftCount++;
            }

            $reportRows[] = [
                'ticket_id' => (int) $ticket->ticket_id,
                'station_id' => (int) $ticket->station_id,
                'order_id' => (int) $ticket->order_id,
                'order_item_id' => (int) $ticket->order_item_id,
                'ticket_status' => $description['lifecycle']['status'],
                'sync_status' => $reconciliation['sync_status'],
                'routing_status' => $reconciliation['routing_status'],
                'drift_reasons' => $reconciliation['drift_reasons'],
                'next_actions' => $reconciliation['next_actions'],
            ];
        }

        return [
            'checked_count' => $tickets->count(),
            'drift_count' => $driftCount,
            'status_drift_count' => $statusDriftCount,
            'routing_drift_count' => $routingDriftCount,
            'tickets' => $reportRows,
        ];
    }
}
