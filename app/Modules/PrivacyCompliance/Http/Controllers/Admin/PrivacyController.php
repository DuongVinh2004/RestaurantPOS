<?php

declare(strict_types=1);

namespace App\Modules\PrivacyCompliance\Http\Controllers\Admin;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\PrivacyCompliance\Application\UseCases\Requests\CustomerDataExportHandler;
use App\Modules\PrivacyCompliance\Application\Workflows\Requests\PrivacyRequestWorkflow;
use App\Modules\PrivacyCompliance\Http\Requests\Admin\ListPrivacyRequestsRequest;
use App\Modules\PrivacyCompliance\Http\Requests\Admin\ReviewPrivacyRequestRequest;
use App\Support\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly CustomerDataExportHandler $exportService,
        private readonly PrivacyRequestWorkflow $privacyRequestService,
    ) {}

    public function exportCustomerData(Request $request, int $userId): JsonResponse
    {
        $data = $this->exportService->exportForUser($userId);

        AuditEvent::info('admin_customer_data_exported', [
            '_audit' => [
                'action' => 'customer_data.exported',
                'primary_subject' => ['type' => 'user', 'id' => (string) $userId, 'role' => 'customer'],
                'summary' => ['reservation_count' => data_get($data, 'summary.reservation_count', 0), 'conversation_count' => data_get($data, 'summary.conversation_count', 0), 'requested_by' => 'admin'],
            ],
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'action' => 'admin_customer_data_export',
            ],
        ]);
    }

    public function index(ListPrivacyRequestsRequest $request): JsonResponse
    {
        $paginator = $this->privacyRequestService->listForAdmin($request->validated());

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($item): array => $this->privacyRequestService->serializeRequest($item))->values()->all(),
            'meta' => [
                'action' => 'admin_customer_privacy_request_index',
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function review(ReviewPrivacyRequestRequest $request, int $requestId): JsonResponse
    {
        $result = $this->privacyRequestService->reviewRequest(
            $requestId,
            $request->validated(),
            $this->resolveStaffActorUserId($request),
        );

        return response()->json([
            'data' => $result['data'],
            'meta' => [
                'action' => 'admin_customer_privacy_request_review',
            ],
        ], $result['status']);
    }
}
