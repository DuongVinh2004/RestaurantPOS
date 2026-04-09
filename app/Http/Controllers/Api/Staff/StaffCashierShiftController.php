<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CloseCashierShiftRequest;
use App\Http\Requests\Staff\ListStaffCashierShiftsRequest;
use App\Http\Requests\Staff\OpenCashierShiftRequest;
use App\Services\Staff\StaffCashierShiftService;
use App\Support\ApiErrorResponse;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffCashierShiftController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffCashierShiftService $cashierShiftService,
    ) {}

    public function index(ListStaffCashierShiftsRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $validated = $request->validated();
        $paginator = $this->cashierShiftService->paginateShiftHistory($staffUserId, $validated);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn ($shift): array => $this->cashierShiftService->toPayload($shift))
                ->values(),
            'meta' => ListingMetaFactory::paginated(
                $paginator,
                [
                    'status' => $validated['status'] ?? null,
                    'branch_id' => isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                    'shift_code' => $validated['shift_code'] ?? null,
                    'terminal_code' => $validated['terminal_code'] ?? null,
                    'q' => $validated['q'] ?? null,
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? '-opened_at'),
                    'by' => (string) ($validated['sort_by'] ?? 'opened_at'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'desc'),
                ],
                ListingMetaFactory::contract(
                    ['status', 'branch_id', 'shift_code', 'terminal_code', 'q'],
                    ['opened_at', 'closed_at', 'cashier_shift_id', 'shift_code'],
                    '-opened_at',
                    true,
                    100,
                    [
                        'status' => 'filter[status]',
                        'branch_id' => 'filter[branch_id]',
                        'shift_code' => 'filter[shift_code]',
                        'terminal_code' => 'filter[terminal_code]',
                        'q' => 'filter[q]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
                [
                    'action' => 'cashier_shift_lookup',
                    'count' => $paginator->count(),
                    'scope' => 'authenticated_cashier',
                ],
            ),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $shift = $this->cashierShiftService->currentOpenShift($staffUserId);

        if ($shift === null) {
            return ApiErrorResponse::json(
                $request,
                404,
                'not_found',
                'No open cashier shift found for the authenticated staff actor.',
            );
        }

        return response()->json([
            'data' => $this->cashierShiftService->toPayload($shift),
            'meta' => [
                'action' => 'cashier_shift_current',
            ],
        ]);
    }

    public function open(OpenCashierShiftRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $shift = $this->cashierShiftService->openShift(
            cashierUserId: $staffUserId,
            openingFloatAmount: (float) ($request->input('opening_float_amount') ?? 0.0),
            currency: (string) ($request->input('currency') ?? 'VND'),
            terminalCode: $request->filled('terminal_code') ? (string) $request->input('terminal_code') : null,
            openingNote: (string) ($request->input('notes') ?? ''),
            openedBy: $staffUserId,
            branchId: $request->input('branch_id'),
        );

        return response()->json([
            'data' => $this->cashierShiftService->toPayload($shift),
            'meta' => [
                'action' => 'cashier_shift_open',
            ],
        ], 201);
    }

    public function show(int $shift_id): JsonResponse
    {
        $shift = $this->cashierShiftService->findShiftOrFail($shift_id);

        return response()->json([
            'data' => $this->cashierShiftService->toPayload($shift),
            'meta' => [
                'action' => 'cashier_shift_show',
            ],
        ]);
    }

    public function close(int $shift_id, CloseCashierShiftRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $shift = $this->cashierShiftService->closeShift(
            shiftId: $shift_id,
            actualCashAmount: (float) $request->input('actual_cash_amount'),
            expectedRowVersion: (int) $request->input('row_version'),
            closingNote: (string) ($request->input('notes') ?? ''),
            closedBy: $staffUserId,
        );

        return response()->json([
            'data' => $this->cashierShiftService->toPayload($shift),
            'meta' => [
                'action' => 'cashier_shift_close',
            ],
        ]);
    }
}
