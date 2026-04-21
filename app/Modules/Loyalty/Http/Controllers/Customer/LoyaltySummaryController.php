<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Loyalty\Http\Requests\Customer\ShowLoyaltySummaryRequest;
use App\Modules\Loyalty\Http\Resources\Customer\LoyaltySummaryResource;
use App\Support\ApiErrorResponse;
use App\Support\Auth\RequestActorContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltySummaryController extends Controller
{
    public function __construct(
        private readonly LoyaltyPointsService $loyaltyPointsService,
    ) {}

    public function show(ShowLoyaltySummaryRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser($request);
        $summary = $this->loyaltyPointsService->getUserLoyaltySummary(
            userId: (int) $user->user_id,
            limit: (int) ($request->validated('limit') ?? 20),
        );

        return response()->json([
            'data' => (new LoyaltySummaryResource($summary))->resolve($request),
        ]);
    }

    private function requireAuthenticatedUser(Request $request): User
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            throw new HttpResponseException(ApiErrorResponse::policyDenied(
                $request,
                'Staff must use dedicated staff loyalty endpoints for operational actions.',
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
