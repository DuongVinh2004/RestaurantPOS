<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CreateCustomerPrivacyRequestRequest;
use App\Http\Requests\Customer\ListCustomerPrivacyRequestsRequest;
use App\Models\User;
use App\Services\DataLifecycle\CustomerDataExportService;
use App\Services\DataLifecycle\CustomerPrivacyRequestService;
use App\Support\ApiErrorResponse;
use App\Support\AuditEvent;
use App\Support\RequestActorContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDataLifecycleController extends Controller
{
    public function __construct(
        private readonly CustomerDataExportService $exportService,
        private readonly CustomerPrivacyRequestService $privacyRequestService,
    ) {}

    public function export(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedCustomer($request);
        $data = $this->exportService->exportForUser((int) $user->user_id);

        AuditEvent::info('customer_data_exported', [
            '_audit' => [
                'action' => 'customer_data.exported',
                'primary_subject' => ['type' => 'user', 'id' => (string) $user->user_id, 'role' => 'customer'],
                'summary' => ['reservation_count' => data_get($data, 'summary.reservation_count', 0), 'conversation_count' => data_get($data, 'summary.conversation_count', 0)],
            ],
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'action' => 'customer_data_export',
            ],
        ]);
    }

    public function index(ListCustomerPrivacyRequestsRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedCustomer($request);
        $paginator = $this->privacyRequestService->listForCustomer((int) $user->user_id, $request->validated());

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($item): array => $this->privacyRequestService->serializeRequest($item))->values()->all(),
            'meta' => [
                'action' => 'customer_privacy_request_index',
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(CreateCustomerPrivacyRequestRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedCustomer($request);
        $result = $this->privacyRequestService->submitAnonymizationRequest((int) $user->user_id, [
            'actor_type' => $request->attributes->get('customer_auth_mode', 'customer_account'),
            'requested_by_user_id' => (int) $user->user_id,
            'requested_via' => 'self_service',
            'reason' => $request->validated('reason'),
        ]);

        return response()->json([
            'data' => [
                'request' => $this->privacyRequestService->serializeRequest($result['request']),
                'created' => $result['created'],
            ],
            'meta' => [
                'action' => $result['created'] ? 'customer_privacy_request_created' : 'customer_privacy_request_existing',
            ],
        ], $result['created'] ? 201 : 200);
    }

    private function requireAuthenticatedCustomer(Request $request): User
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            throw new HttpResponseException(ApiErrorResponse::policyDenied(
                $request,
                'Staff actors must use dedicated admin privacy endpoints.',
            ));
        }

        $user = $request->user();
        if (! $actor->isCustomerOwner() || ! $user instanceof User) {
            throw new HttpResponseException(ApiErrorResponse::authenticationRequired(
                $request,
                'Customer authentication is required.',
            ));
        }

        return $user;
    }
}
