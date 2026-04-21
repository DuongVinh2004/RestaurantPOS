<?php

declare(strict_types=1);

namespace App\Modules\Cashiering\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOperations\Http\Requests\Staff\BranchScopeRequest;
use App\Modules\Cashiering\Application\UseCases\Shifts\StaffCashierShiftService;
use App\Modules\Cashiering\Http\Requests\Staff\CloseCashierShiftRequest;
use App\Modules\Cashiering\Http\Requests\Staff\ListStaffCashierShiftsRequest;
use App\Modules\Cashiering\Http\Requests\Staff\OpenCashierShiftRequest;
use App\Support\ApiErrorResponse;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierShiftController extends Controller
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

    public function current(BranchScopeRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $validated = $request->validated();
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $shift = $this->cashierShiftService->currentOpenShift($staffUserId, $branchId);

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
                'branch_id' => $branchId,
            ],
        ]);
    }

    public function open(OpenCashierShiftRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $validated = $request->validated();

        $shift = $this->cashierShiftService->openShift(
            cashierUserId: $staffUserId,
            openingFloatAmount: (float) ($validated['opening_float_amount'] ?? 0.0),
            currency: (string) ($validated['currency'] ?? 'VND'),
            terminalCode: isset($validated['terminal_code']) ? (string) $validated['terminal_code'] : null,
            openingNote: (string) ($validated['notes'] ?? ''),
            openedBy: $staffUserId,
            branchId: $validated['branch_id'] ?? null,
        );

        return response()->json([
            'data' => $this->cashierShiftService->toPayload($shift),
            'meta' => [
                'action' => 'cashier_shift_open',
            ],
        ], 201);
    }

    public function show(Request $request, int $shift_id): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $shift = $this->cashierShiftService->findShiftOrFail($shift_id, $staffUserId);

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
            cashierUserId: $staffUserId,
        );

        return response()->json([
            'data' => $this->cashierShiftService->toPayload($shift),
            'meta' => [
                'action' => 'cashier_shift_close',
            ],
        ]);
    }
}


