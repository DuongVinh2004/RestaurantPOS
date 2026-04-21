<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Promotions\Application\UseCases\Benefits\CustomerBenefitsService;
use App\Modules\Promotions\Http\Requests\Customer\ListVouchersRequest;
use App\Modules\Promotions\Http\Resources\Customer\ReservationBenefitsPreviewResource;
use App\Modules\Promotions\Http\Resources\Customer\VoucherResource;
use App\Support\ApiErrorResponse;
use App\Support\Auth\RequestActorContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BenefitsController extends Controller
{
    public function __construct(
        private readonly CustomerBenefitsService $benefitsService,
    ) {}

    public function vouchers(ListVouchersRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);
        $paginator = $this->benefitsService->listSelfVouchers((int) $user->user_id, $request->validated());

        $items = $paginator->getCollection()
            ->map(fn (array $row) => (new VoucherResource($row))->resolve($request))
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
            'data' => (new ReservationBenefitsPreviewResource($preview))->resolve($request),
        ]);
    }

    private function requireAuthenticatedUser(Request $request): User
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            throw new HttpResponseException(ApiErrorResponse::policyDenied(
                $request,
                'Staff must use dedicated staff loyalty and voucher endpoints for operational actions.',
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
