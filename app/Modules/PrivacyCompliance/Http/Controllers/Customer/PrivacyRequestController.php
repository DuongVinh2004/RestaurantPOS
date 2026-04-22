<?php

declare(strict_types=1);

namespace App\Modules\PrivacyCompliance\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\PrivacyCompliance\Application\UseCases\Requests\CustomerDataExportHandler;
use App\Modules\PrivacyCompliance\Application\Workflows\Requests\PrivacyRequestWorkflow;
use App\Modules\PrivacyCompliance\Http\Requests\Customer\CreatePrivacyRequestRequest;
use App\Modules\PrivacyCompliance\Http\Requests\Customer\ListPrivacyRequestsRequest;
use App\Support\ApiErrorResponse;
use App\Support\AuditEvent;
use App\Support\Auth\RequestActorContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyRequestController extends Controller
{
    public function __construct(
        private readonly CustomerDataExportHandler $exportService,
        private readonly PrivacyRequestWorkflow $privacyRequestService,
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

    public function index(ListPrivacyRequestsRequest $request): JsonResponse
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

    public function store(CreatePrivacyRequestRequest $request): JsonResponse
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
