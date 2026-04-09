<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ListAccountingExportRequest;
use App\Services\Staff\StaffInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffFinanceInvoiceController extends Controller
{
    public function __construct(
        private readonly StaffInvoiceService $invoiceService,
    ) {
    }

    public function show(int $reservation_id): JsonResponse
    {
        return $this->financeJson([
            'data' => $this->invoiceService->show($reservation_id),
            'meta' => [
                'action' => 'finance_invoice_show',
            ],
        ]);
    }

    public function issue(int $reservation_id, Request $request): JsonResponse
    {
        $actorUserId = (int) ($request->attributes->get('staff_actor_user_id') ?? 0);
        $result = $this->invoiceService->issue($reservation_id, $actorUserId);
        $status = $result['created'] ? 201 : 200;

        return response()->json([
            'data' => $this->invoiceService->show($reservation_id),
            'meta' => [
                'action' => 'finance_invoice_issued',
                'created' => $result['created'],
            ],
        ], $status, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function accountingExport(ListAccountingExportRequest $request): Response|JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $format = strtolower((string) ($filters['format'] ?? 'csv'));
        $rows = $this->invoiceService->exportRows($filters);

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

        $filename = 'financial_accounting_export_' . now('UTC')->format('Ymd_His') . '.csv';

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
     * @param array<string,mixed> $payload
     */
    private function financeJson(array $payload): JsonResponse
    {
        return response()->json($payload, 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
