<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ReservationTimelineRequest;
use App\Http\Resources\StaffReservationTimelineItemResource;
use App\Services\Staff\StaffOperationalRealtimeService;
use App\Services\Staff\StaffReservationTimelineService;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class StaffReservationTimelineController extends Controller
{
    public function __construct(
        private readonly StaffReservationTimelineService $timelineService,
    ) {}

    public function index(ReservationTimelineRequest $request): JsonResponse
    {
        $timeline = $this->timelineService->buildTimeline($request->validated());

        $request->attributes->set('staff_reservation_timeline_timezone', $timeline['timezone']);
        $request->attributes->set('staff_reservation_timeline_now_utc', $timeline['now_utc']);
        $request->attributes->set('staff_reservation_timeline_due_soon_cutoff_utc', $timeline['due_soon_cutoff_utc']);
        $request->attributes->set('staff_reservation_timeline_overdue_cutoff_utc', $timeline['overdue_cutoff_utc']);
        $request->attributes->set('staff_reservation_timeline_candidate_tables_by_reservation', $timeline['candidate_tables_by_reservation'] ?? []);
        $request->attributes->set('staff_reservation_timeline_check_in_readiness_by_reservation', $timeline['check_in_readiness_by_reservation'] ?? []);
        $request->attributes->set('staff_reservation_timeline_assignment_request_context', [
            'board_from' => $timeline['range_start_utc']->toIso8601String(),
            'board_to' => $timeline['range_end_utc']->toIso8601String(),
            'branch_id' => $timeline['filters']['branch_id'] ?? null,
            'zone' => $timeline['filters']['zone'] ?? null,
            'include_slot_only_candidates' => false,
        ]);

        $items = collect();
        $slots = [];

        foreach ($timeline['slots'] as $slot) {
            $slotItems = $slot['reservations']->map(fn ($reservation): array => (new StaffReservationTimelineItemResource($reservation))->toArray($request))->values();
            $items = $items->merge($slotItems);
            $slots[] = [
                'slot_start' => $slot['slot_start']->toIso8601String(),
                'slot_end' => $slot['slot_end']->toIso8601String(),
                'reservation_count' => $slotItems->count(),
                'reservations' => $slotItems->all(),
            ];
        }

        $items = $items->values();
        $calendar = $this->buildCalendarPayload($slots, (string) ($timeline['lane_mode'] ?? 'slot'));

        return response()->json([
            'data' => [
                'timezone' => $timeline['timezone'],
                'slot_minutes' => (int) $timeline['slot_minutes'],
                'range' => [
                    'start_local' => $timeline['range_start_local']->toIso8601String(),
                    'end_local' => $timeline['range_end_local']->toIso8601String(),
                    'start_utc' => $timeline['range_start_utc']->toIso8601String(),
                    'end_utc' => $timeline['range_end_utc']->toIso8601String(),
                ],
                'slots' => $slots,
                'items' => $items->all(),
                'summary' => $this->buildSummary($items),
                'calendar' => $calendar,
            ],
            'meta' => ListingMetaFactory::collection(
                $timeline['filters'],
                [
                    'supported' => false,
                    'value' => null,
                    'by' => null,
                    'dir' => null,
                ],
                ListingMetaFactory::contract(
                    [
                        'date',
                        'start_date',
                        'end_date',
                        'from_time',
                        'to_time',
                        'branch_id',
                        'status',
                        'table_id',
                        'zone',
                        'q',
                        'deposit_acknowledged',
                        'deposit_intent_status',
                        'slot_minutes',
                        'lane_by',
                        'include_candidate_tables',
                    ],
                    [],
                    null,
                    false,
                    0,
                    [
                        'date' => 'filter[date]',
                        'start_date' => 'filter[start_date]',
                        'end_date' => 'filter[end_date]',
                        'from_time' => 'filter[from_time]',
                        'to_time' => 'filter[to_time]',
                        'branch_id' => 'filter[branch_id]',
                        'status' => 'filter[status]',
                        'table_id' => 'filter[table_id]',
                        'zone' => 'filter[zone]',
                        'q' => 'filter[q]',
                        'deposit_acknowledged' => 'filter[deposit_acknowledged]',
                        'deposit_intent_status' => 'filter[deposit_intent_status]',
                        'slot_minutes' => 'filter[slot_minutes]',
                        'lane_by' => 'filter[lane_by]',
                        'include_candidate_tables' => 'filter[include_candidate_tables]',
                    ],
                ),
                [
                    'action' => 'reservation_timeline',
                    'selection_policy' => 'Defaults to operational reservations overlapping the requested window; terminal statuses are included only when an explicit status filter is provided.',
                    'supported_lane_modes' => ['slot', 'zone', 'table'],
                    'workbench' => [
                        'supported' => true,
                        'drag_drop_backend_supported' => false,
                        'mutation_policy' => 'Timeline reuses canonical reservation assignment and check-in services through lightweight action aliases only.',
                    ],
                    'realtime' => app(StaffOperationalRealtimeService::class)->describeTopic(
                        StaffOperationalRealtimeService::TOPIC_BOARD,
                        '/api/v1/staff/tables/board/changes',
                        ['board', 'timeline'],
                    ),
                ],
            ),
        ]);
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $items
     * @return array<string,mixed>
     */
    private function buildSummary($items): array
    {
        $statusCounts = [];
        $operationalStateCounts = [];
        $flagCounts = [
            'due_soon' => 0,
            'late' => 0,
            'overdue' => 0,
            'checked_in' => 0,
            'has_active_order' => 0,
            'needs_assignment' => 0,
            'deposit_acknowledged' => 0,
            'deposit_intent_submitted' => 0,
            'deposit_self_service_follow_up' => 0,
        ];
        $workbenchActionCounts = [
            'assign_best_fit' => 0,
            'assign_suggested' => 0,
            'check_in' => 0,
            'move_table' => 0,
            'reschedule' => 0,
            'deposit_preview' => 0,
        ];
        $recommendedActionCounts = [];

        foreach ($items as $item) {
            $status = (string) data_get($item, 'reservation.status', '');
            $operationalState = (string) data_get($item, 'operational_state', 'scheduled');
            $statusCounts[$status] = (int) ($statusCounts[$status] ?? 0) + 1;
            $operationalStateCounts[$operationalState] = (int) ($operationalStateCounts[$operationalState] ?? 0) + 1;

            foreach (array_keys($flagCounts) as $flag) {
                if ((bool) data_get($item, 'flags.'.$flag, false)) {
                    $flagCounts[$flag]++;
                }
            }

            foreach ((array) data_get($item, 'workbench.actions', []) as $action) {
                $key = (string) ($action['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $isAvailable = (bool) ($action['available'] ?? $action['enabled'] ?? false);
                if ($isAvailable) {
                    $workbenchActionCounts[$key] = (int) ($workbenchActionCounts[$key] ?? 0) + 1;
                }

                if ($isAvailable && (bool) ($action['recommended'] ?? false)) {
                    $recommendedActionCounts[$key] = (int) ($recommendedActionCounts[$key] ?? 0) + 1;
                }
            }
        }

        return [
            'total_reservations' => $items->count(),
            'status_counts' => $statusCounts,
            'operational_state_counts' => $operationalStateCounts,
            'flag_counts' => $flagCounts,
            'workbench_action_counts' => $workbenchActionCounts,
            'recommended_action_counts' => $recommendedActionCounts,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $slots
     * @return array<string,mixed>
     */
    private function buildCalendarPayload(array $slots, string $laneMode): array
    {
        if ($laneMode === 'slot') {
            return [
                'lane_mode' => 'slot',
                'has_lane_grouping' => false,
                'lane_anchor_policy' => 'first_assigned_table_anchor',
                'lanes' => [],
            ];
        }

        $laneMap = [];

        foreach ($slots as $slot) {
            foreach ((array) ($slot['reservations'] ?? []) as $item) {
                $lane = $this->resolveLaneDescriptor($item, $laneMode);
                $laneKey = (string) $lane['lane_key'];
                $slotKey = (string) ($slot['slot_start'] ?? '');

                if (! isset($laneMap[$laneKey])) {
                    $laneMap[$laneKey] = [
                        'lane_key' => $laneKey,
                        'lane_type' => (string) $lane['lane_type'],
                        'label' => (string) $lane['label'],
                        'zone' => $lane['zone'] ?? null,
                        'table' => $lane['table'] ?? null,
                        'slot_map' => [],
                        'items' => [],
                    ];
                }

                if (! isset($laneMap[$laneKey]['slot_map'][$slotKey])) {
                    $laneMap[$laneKey]['slot_map'][$slotKey] = [
                        'slot_start' => $slot['slot_start'],
                        'slot_end' => $slot['slot_end'],
                        'reservations' => [],
                    ];
                }

                $laneMap[$laneKey]['slot_map'][$slotKey]['reservations'][] = $item;
                $laneMap[$laneKey]['items'][] = $item;
            }
        }

        $lanes = array_values(array_map(function (array $lane): array {
            ksort($lane['slot_map']);
            $laneItems = collect($lane['items']);

            return [
                'lane_key' => $lane['lane_key'],
                'lane_type' => $lane['lane_type'],
                'label' => $lane['label'],
                'zone' => $lane['zone'],
                'table' => $lane['table'],
                'reservation_count' => $laneItems->count(),
                'summary' => $this->buildSummary($laneItems),
                'slots' => array_values(array_map(static function (array $slot): array {
                    return [
                        'slot_start' => (string) $slot['slot_start'],
                        'slot_end' => (string) $slot['slot_end'],
                        'reservation_count' => count((array) $slot['reservations']),
                        'reservations' => array_values((array) $slot['reservations']),
                    ];
                }, $lane['slot_map'])),
            ];
        }, $laneMap));

        usort($lanes, static function (array $left, array $right): int {
            $leftRank = $left['lane_type'] === 'unassigned' ? 1 : 0;
            $rightRank = $right['lane_type'] === 'unassigned' ? 1 : 0;

            return [$leftRank, (string) $left['label'], (string) $left['lane_key']] <=> [$rightRank, (string) $right['label'], (string) $right['lane_key']];
        });

        return [
            'lane_mode' => $laneMode,
            'has_lane_grouping' => true,
            'lane_anchor_policy' => 'first_assigned_table_anchor',
            'lane_count' => count($lanes),
            'lanes' => $lanes,
        ];
    }

    /**
     * @param  array<string,mixed>  $item
     * @return array<string,mixed>
     */
    private function resolveLaneDescriptor(array $item, string $laneMode): array
    {
        if ($laneMode === 'zone') {
            $zone = data_get($item, 'calendar.primary_zone');

            if (! is_string($zone) || trim($zone) === '') {
                return [
                    'lane_key' => 'unassigned',
                    'lane_type' => 'unassigned',
                    'label' => 'Unassigned',
                    'zone' => null,
                    'table' => null,
                ];
            }

            $zone = trim($zone);

            return [
                'lane_key' => 'zone:'.$zone,
                'lane_type' => 'zone',
                'label' => $zone,
                'zone' => $zone,
                'table' => null,
            ];
        }

        $table = data_get($item, 'calendar.primary_table');
        if (! is_array($table) || empty($table)) {
            return [
                'lane_key' => 'unassigned',
                'lane_type' => 'unassigned',
                'label' => 'Unassigned',
                'zone' => null,
                'table' => null,
            ];
        }

        return [
            'lane_key' => 'table:'.(int) ($table['table_id'] ?? 0),
            'lane_type' => 'table',
            'label' => (string) ($table['table_code'] ?? 'Unknown Table'),
            'zone' => $table['zone'] ?? null,
            'table' => $table,
        ];
    }
}
