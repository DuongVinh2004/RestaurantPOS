<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ListFinancialReconciliationRequest;
use App\Services\Staff\StaffFinancialReconciliationService;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffFinancialReconciliationController extends Controller
{
    public function __construct(
        private readonly StaffFinancialReconciliationService $financialReconciliationService,
    ) {}

    public function index(ListFinancialReconciliationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $paginator = $this->financialReconciliationService->paginate($validated);

        return $this->financialJson([
            'data' => $paginator->items(),
            'meta' => ListingMetaFactory::paginated(
                $paginator,
                [
                    'reservation_id' => isset($validated['reservation_id']) ? (int) $validated['reservation_id'] : null,
                    'reservation_code' => $validated['reservation_code'] ?? null,
                    'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                    'status' => $validated['status'] ?? null,
                    'deposit_status' => $validated['deposit_status'] ?? null,
                    'payment_currency' => $validated['payment_currency'] ?? null,
                    'cashier_user_id' => isset($validated['cashier_user_id']) ? (int) $validated['cashier_user_id'] : null,
                    'activity_from' => $validated['activity_from'] ?? null,
                    'activity_to' => $validated['activity_to'] ?? null,
                    'has_discrepancy' => array_key_exists('has_discrepancy', $validated) ? $validated['has_discrepancy'] : null,
                ],
                [
                    'supported' => true,
                    'value' => (string) ($validated['sort'] ?? '-last_payment_activity_at'),
                    'by' => (string) ($validated['sort_by'] ?? 'last_payment_activity_at'),
                    'dir' => (string) ($validated['sort_dir'] ?? 'desc'),
                ],
                ListingMetaFactory::contract(
                    [
                        'reservation_id',
                        'reservation_code',
                        'user_id',
                        'status',
                        'deposit_status',
                        'payment_currency',
                        'cashier_user_id',
                        'activity_from',
                        'activity_to',
                        'has_discrepancy',
                    ],
                    ['reservation_id', 'start_time', 'updated_at', 'final_bill_amount', 'net_paid_amount', 'refunded_amount', 'last_payment_activity_at'],
                    '-last_payment_activity_at',
                    true,
                    100,
                    [
                        'reservation_id' => 'filter[reservation_id]',
                        'reservation_code' => 'filter[reservation_code]',
                        'user_id' => 'filter[user_id]',
                        'status' => 'filter[status]',
                        'deposit_status' => 'filter[deposit_status]',
                        'payment_currency' => 'filter[payment_currency]',
                        'cashier_user_id' => 'filter[cashier_user_id]',
                        'activity_from' => 'filter[activity_from]',
                        'activity_to' => 'filter[activity_to]',
                        'has_discrepancy' => 'filter[has_discrepancy]',
                        'sort_by' => 'sort',
                        'sort_dir' => 'sort',
                    ],
                ),
                [
                    'action' => 'financial_reconciliation_index',
                ],
            ),
        ]);
    }

    public function show(int $reservation_id): JsonResponse
    {
        return $this->financialJson([
            'data' => $this->financialReconciliationService->show($reservation_id),
            'meta' => [
                'action' => 'financial_reconciliation_show',
            ],
        ]);
    }

    public function export(ListFinancialReconciliationRequest $request): Response|JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $format = strtolower((string) ($filters['format'] ?? 'csv'));
        $rows = $this->financialReconciliationService->exportRows($filters);

        if ($format === 'json') {
            return $this->financialJson([
                'data' => $rows,
                'meta' => [
                    'action' => 'financial_reconciliation_export',
                    'format' => 'json',
                    'row_count' => count($rows),
                    'filters' => $filters,
                ],
            ]);
        }

        $filename = 'financial_reconciliation_export_' . now('UTC')->format('Ymd_His') . '.csv';

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
    private function financialJson(array $payload): JsonResponse
    {
        return response()->json($payload, 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
