<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOperations\Http\Requests\Staff\BranchScopeRequest;
use App\Modules\Billing\Application\UseCases\Invoices\StaffInvoiceService;
use App\Modules\Billing\Http\Requests\Staff\ListAccountingExportRequest;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffInvoiceService $invoiceService,
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    public function show(int $reservation_id, BranchScopeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $actorUserId = $this->resolveStaffActorUserId($request);

        if ($branchId !== null) {
            try {
                $branchId = $this->branchContextService->assertAccessibleBranch($actorUserId, $branchId);
            } catch (ModelNotFoundException) {
                return $this->notFoundResponse($request, 'Branch not found.');
            }
        }

        return $this->financeJson([
            'data' => $this->invoiceService->show($reservation_id, $branchId, $actorUserId),
            'meta' => [
                'action' => 'finance_invoice_show',
                'branch_id' => $branchId,
            ],
        ]);
    }

    public function issue(int $reservation_id, BranchScopeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $actorUserId = $this->resolveStaffActorUserId($request);

        $result = $this->invoiceService->issue($reservation_id, $actorUserId, $branchId);
        $status = $result['created'] ? 201 : 200;

        return response()->json([
            'data' => $this->invoiceService->show($reservation_id, $branchId, $actorUserId),
            'meta' => [
                'action' => 'finance_invoice_issued',
                'created' => $result['created'],
                'branch_id' => $branchId,
            ],
        ], $status, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function accountingExport(ListAccountingExportRequest $request): Response|JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $actorUserId = $this->resolveStaffActorUserId($request);
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        if ($branchId !== null) {
            try {
                $filters['branch_id'] = $this->branchContextService->assertAccessibleBranch($actorUserId, $branchId);
            } catch (ModelNotFoundException) {
                return $this->notFoundResponse($request, 'Branch not found.');
            }
        }

        $format = strtolower((string) ($filters['format'] ?? 'csv'));
        $rows = $this->invoiceService->exportRows($filters, $actorUserId);

        if ($format === 'json') {
            return $this->financeJson([
                'data' => $rows,
                'meta' => [
                    'action' => 'finance_accounting_export',
                    'format' => 'json',
                    'row_count' => count($rows),
                    'filters' => $filters,
                ],
            ]);
        }

        $filename = 'financial_accounting_export_'.now('UTC')->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            if ($rows === []) {
                fputcsv($handle, ['reservation_id']);
                fclose($handle);

                return;
            }

            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function financeJson(array $payload): JsonResponse
    {
        return response()->json($payload, 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    private function notFoundResponse(BranchScopeRequest|ListAccountingExportRequest $request, string $message): JsonResponse
    {
        return ApiErrorResponse::json(
            $request,
            404,
            'not_found',
            $message,
        );
    }
}


