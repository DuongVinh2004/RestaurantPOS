<?php

namespace App\Modules\BranchScheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BranchScheduling\Http\Requests\AvailableTablesRequest;
use App\Modules\BranchScheduling\Http\Resources\RestaurantTableResource;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\BranchScheduling\Application\Services\TableAvailabilityService;
use App\Modules\BranchScheduling\Application\Services\TableHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TableController extends Controller
{
    public function __construct(
        private readonly TableHoldService $tableHoldService,
        private readonly TableAvailabilityService $tableAvailabilityService,
        private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService,
    ) {
    }

    public function available(AvailableTablesRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        try {
            $this->tableHoldService->expireStaleHolds();
        } catch (\Throwable) {
            // ignore best-effort expiration failure
        }

        $fromInput = (string) $validated['from'];
        $toInput   = (string) $validated['to'];

        $fromUtc = CarbonImmutable::parse($fromInput)->utc();
        $toUtc   = CarbonImmutable::parse($toInput)->utc();

        $branchId   = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $zone       = $validated['zone'] ?? null;
        $templateId = $validated['template_id'] ?? null;
        $minSeats   = $validated['min_seats'] ?? null;
        $guestCount = $validated['guest_count'] ?? null;
        $sessionId  = $validated['session_id'] ?? null;
        $suggest    = (bool) ($validated['suggest'] ?? false);
        $maxSug     = (int) ($validated['max_suggestions'] ?? 10);
        $maxSug     = $maxSug > 0 ? min($maxSug, 50) : 10;
        $resolvedBranchId = $this->branchSchedulingPolicyService->resolveBranchId($branchId);
        $branchTimezone = $this->branchSchedulingPolicyService->branchTimezone($resolvedBranchId);
        $policyEvaluation = $this->branchSchedulingPolicyService->evaluateAvailabilityWindow($resolvedBranchId, $fromUtc, $toUtc);

        $tables = collect($this->tableAvailabilityService->getAvailable($fromUtc, $toUtc, [
            'branch_id' => $resolvedBranchId,
            'zone' => $zone,
            'template_id' => $templateId,
            'min_seats' => $minSeats,
            'guest_count' => $guestCount,
            'session_id' => $sessionId,
            'suggest' => $suggest,
        ]))->map(function (array $row) {
            $table = new RestaurantTable();
            $table->forceFill($row);
            $table->exists = true;

            return $table;
        });

        $suggestions = null;
        if ($suggest && $guestCount !== null && $tables->count() > 0) {
            $suggestions = $this->buildTableSuggestions(
                $tables->map(fn (RestaurantTable $table) => $table->toArray())->all(),
                (int) $guestCount,
                $maxSug
            );
        }

        return RestaurantTableResource::collection($tables)->additional([
            'meta' => [
                'timezone'   => 'UTC',
                'branch_id'  => $resolvedBranchId,
                'branch_timezone' => $branchTimezone,
                'from_utc'   => $fromUtc->toIso8601String(),
                'to_utc'     => $toUtc->toIso8601String(),
                'from_input' => $fromInput,
                'to_input'   => $toInput,
                'availability_policy' => [
                    'allowed' => (bool) ($policyEvaluation['allowed'] ?? true),
                    'reason' => $policyEvaluation['reason'] ?? null,
                    'message' => $policyEvaluation['message'] ?? null,
                ],
                'filters'    => [
                    'branch_id'       => $resolvedBranchId,
                    'zone'            => is_string($zone) ? $zone : null,
                    'template_id'     => $templateId !== null ? (int) $templateId : null,
                    'min_seats'       => $minSeats !== null ? (int) $minSeats : null,
                    'guest_count'     => $guestCount !== null ? (int) $guestCount : null,
                    'session_id'      => is_string($sessionId) ? $sessionId : null,
                    'suggest'         => $suggest,
                    'max_suggestions' => $maxSug,
                ],
                'count'      => $tables->count(),
                'suggestions'=> $suggestions,
            ],
        ]);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function buildTableSuggestions(array $tables, int $guestCount, int $limit): array
    {
        $items = [];
        foreach ($tables as $t) {
            $seats = (int) ($t['seats'] ?? 0);
            if ($seats <= 0) {
                continue;
            }
            $items[] = [
                'table_id'   => (int) ($t['table_id'] ?? 0),
                'table_code' => (string) ($t['table_code'] ?? ''),
                'seats'      => $seats,
            ];
        }

        if (count($items) === 0) {
            return [];
        }

        usort($items, fn ($a, $b) => $a['seats'] <=> $b['seats']);

        $suggestions = [];
        $seen = [];

        $add = function (array $combo) use (&$suggestions, &$seen, $guestCount, $limit): void {
            $ids = array_map(fn ($x) => $x['table_id'], $combo);
            sort($ids);
            $key = implode('-', $ids);

            if (isset($seen[$key])) {
                return;
            }

            $total = array_sum(array_map(fn ($x) => $x['seats'], $combo));
            if ($total < $guestCount) {
                return;
            }

            $seen[$key] = true;

            $suggestions[] = [
                'table_ids'   => $ids,
                'total_seats' => $total,
                'over'        => $total - $guestCount,
                'tables'      => array_values(array_map(fn ($x) => [
                    'table_id'   => $x['table_id'],
                    'table_code' => $x['table_code'],
                    'seats'      => $x['seats'],
                ], $combo)),
            ];

            usort($suggestions, function ($a, $b) {
                return [$a['over'], $a['total_seats'], count($a['table_ids'])]
                    <=> [$b['over'], $b['total_seats'], count($b['table_ids'])];
            });

            if (count($suggestions) > $limit) {
                $suggestions = array_slice($suggestions, 0, $limit);
            }
        };

        foreach ($items as $t) {
            if ($t['seats'] >= $guestCount) {
                $add([$t]);
            }
        }

        $n = min(count($items), 80);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $add([$items[$i], $items[$j]]);
            }
        }

        $k = min(count($items), 25);
        for ($i = 0; $i < $k; $i++) {
            for ($j = $i + 1; $j < $k; $j++) {
                for ($m = $j + 1; $m < $k; $m++) {
                    $add([$items[$i], $items[$j], $items[$m]]);
                }
            }
        }

        return $suggestions;
    }
}
