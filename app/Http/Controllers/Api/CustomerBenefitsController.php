<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ListCustomerVouchersRequest;
use App\Http\Requests\Customer\ShowCustomerLoyaltySummaryRequest;
use App\Http\Resources\CustomerLoyaltySummaryResource;
use App\Http\Resources\CustomerReservationBenefitsPreviewResource;
use App\Http\Resources\CustomerVoucherResource;
use App\Models\User;
use App\Services\Customer\CustomerBenefitsService;
use App\Support\ApiErrorResponse;
use App\Support\RequestActorContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerBenefitsController extends Controller
{
    public function __construct(
        private readonly CustomerBenefitsService $benefitsService,
    ) {}

    public function loyalty(ShowCustomerLoyaltySummaryRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);
        $summary = $this->benefitsService->getSelfLoyaltySummary(
            userId: (int) $user->user_id,
            limit: (int) ($request->validated('limit') ?? 20),
        );

        return response()->json([
            'data' => (new CustomerLoyaltySummaryResource($summary))->resolve($request),
        ]);
    }

    public function vouchers(ListCustomerVouchersRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);
        $paginator = $this->benefitsService->listSelfVouchers((int) $user->user_id, $request->validated());

        $items = $paginator->getCollection()
            ->map(fn (array $row) => (new CustomerVoucherResource($row))->resolve($request))
            ->values()
            ->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
                'filters' => [
                    'bucket' => strtolower((string) ($request->validated('bucket') ?? 'active')),
                    'q' => $request->validated('q'),
                ],
            ],
        ]);
    }

    public function reservationBenefitsPreview(int $id, Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);
        $preview = $this->benefitsService->previewOwnedReservationBenefits($id, (int) $user->user_id);

        return response()->json([
            'data' => (new CustomerReservationBenefitsPreviewResource($preview))->resolve($request),
        ]);
    }

    private function requireAuthenticatedUser(Request $request): User
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            throw new HttpResponseException(ApiErrorResponse::json(
                $request,
                403,
                'forbidden',
                'Staff must use dedicated staff loyalty and voucher endpoints for operational actions.',
            ));
        }

        $user = $request->user();
        if (! $actor->isCustomerOwner() || ! $user instanceof User) {
            throw new HttpResponseException(ApiErrorResponse::json(
                $request,
                401,
                'unauthorized',
                'Customer authentication is required.',
            ));
        }

        return $user;
    }
}
