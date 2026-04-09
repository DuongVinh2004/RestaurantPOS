<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TableHold\CancelTableHoldRequest;
use App\Http\Requests\TableHold\RefreshTableHoldRequest;
use App\Http\Requests\TableHold\StoreTableHoldRequest;
use App\Http\Resources\TableHoldResource;
use App\Services\TableHoldService;
use App\Support\ApiErrorResponse;
use App\Support\StaffActorResolver;
use App\Support\StaffCapabilityResolver;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TableHoldController extends Controller
{
    public function __construct(
        private readonly TableHoldService $tableHoldService,
        private readonly StaffActorResolver $staffActorResolver,
        private readonly StaffCapabilityResolver $staffCapabilityResolver,
    ) {}

    public function store(StoreTableHoldRequest $request): JsonResponse
    {
        $actorUserId = $request->user()?->user_id;
        $hold = $this->tableHoldService->createHold($request->validated(), $actorUserId);

        return (new TableHoldResource($hold))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $hold_id): TableHoldResource|JsonResponse
    {
        $sessionId = $this->extractSessionId($request);
        if ($sessionId !== '') {
            $hold = $this->tableHoldService->getHold($hold_id, $sessionId);

            return new TableHoldResource($hold);
        }

        if ($this->staffActorResolver->extractProvidedKey($request) === '') {
            throw ValidationException::withMessages([
                'session_id' => ['session_id is required to view this hold.'],
            ]);
        }

        $this->resolveAuthorizedStaffActor($request, 'reservation.manage');
        $hold = $this->tableHoldService->getHold($hold_id, null);

        return new TableHoldResource($hold);
    }

    public function cancel(string $hold_id, CancelTableHoldRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sessionId = trim((string) ($validated['session_id'] ?? ''));
        $expectedRowVersion = array_key_exists('row_version', $validated) && $validated['row_version'] !== null
            ? (int) $validated['row_version']
            : null;
        $staffActorUserId = null;
        $isStaff = false;

        if ($sessionId === '') {
            if ($this->staffActorResolver->extractProvidedKey($request) === '') {
                throw ValidationException::withMessages([
                    'session_id' => ['session_id is required to cancel this hold.'],
                ]);
            }

            $staffActor = $this->resolveAuthorizedStaffActor($request, 'reservation.manage');
            $isStaff = true;
            $staffActorUserId = (int) (($staffActor['user']->user_id ?? 0));
        }

        $hold = $this->tableHoldService->cancelHold(
            $hold_id,
            $isStaff ? null : $sessionId,
            $isStaff,
            $expectedRowVersion,
            $staffActorUserId,
        );

        return (new TableHoldResource($hold))->response();
    }

    public function refresh(string $hold_id, RefreshTableHoldRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sessionId = trim((string) ($validated['session_id'] ?? ''));
        $expectedRowVersion = array_key_exists('row_version', $validated) && $validated['row_version'] !== null
            ? (int) $validated['row_version']
            : null;
        $staffActorUserId = null;
        $isStaff = false;

        if ($sessionId === '') {
            if ($this->staffActorResolver->extractProvidedKey($request) === '') {
                throw ValidationException::withMessages([
                    'session_id' => ['session_id is required to refresh this hold.'],
                ]);
            }

            $staffActor = $this->resolveAuthorizedStaffActor($request, 'reservation.manage');
            $isStaff = true;
            $staffActorUserId = (int) (($staffActor['user']->user_id ?? 0));
        }

        $hold = $this->tableHoldService->refreshHold(
            $hold_id,
            $isStaff ? null : $sessionId,
            (int) ($validated['extend_minutes'] ?? 5),
            $isStaff,
            $expectedRowVersion,
            $staffActorUserId,
        );

        return (new TableHoldResource($hold))->response();
    }

    private function extractSessionId(Request $request): string
    {
        $candidates = [
            $request->input('session_id'),
            $request->query('session_id'),
            $request->header('X-Session-Id'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveStaffActor(Request $request): array
    {
        return $this->staffActorResolver->resolveFromRequest($request);
    }

    /**
     * @return array{ok:bool,status:int,error_code?:string,message?:string,user?:mixed,mode?:string}
     */
    private function resolveAuthorizedStaffActor(Request $request, string $capability): array
    {
        $staffActor = $this->resolveStaffActor($request);
        if (! (bool) ($staffActor['ok'] ?? false)) {
            $this->throwStaffErrorResponse(
                $request,
                (int) ($staffActor['status'] ?? 401),
                [
                    'error_code' => (string) ($staffActor['error_code'] ?? 'unauthorized'),
                    'message' => (string) ($staffActor['message'] ?? 'Unauthorized.'),
                ],
            );
        }

        $user = $staffActor['user'] ?? null;
        $roleName = trim((string) ($user?->role?->role_name ?? ''));
        $resolved = $this->staffCapabilityResolver->resolveForActor(
            (int) ($user?->role_id ?? 0),
            $roleName,
        );
        $knownCapabilities = array_values(array_filter(array_map('strval', (array) ($resolved['known_capabilities'] ?? config('staff_capabilities.known_capabilities', [])))));

        if ((bool) config('staff_capabilities.enforce_known_capabilities', false)
            && $knownCapabilities !== []
            && ! in_array($capability, $knownCapabilities, true)) {
            $this->throwStaffErrorResponse($request, 500, [
                'error_code' => 'staff_capability_not_registered',
                'message' => (string) config('staff_capabilities.messages.unknown_capability', 'Staff capability contract is not registered.'),
                'required_capability' => $capability,
            ]);
        }

        $capabilities = array_values(array_filter(array_map('strval', (array) ($resolved['capabilities'] ?? []))));
        if (! in_array('*', $capabilities, true) && ! in_array($capability, $capabilities, true)) {
            $this->throwStaffErrorResponse($request, 403, [
                'error_code' => 'forbidden',
                'message' => (string) config('staff_capabilities.messages.default', 'Forbidden.'),
                'required_capability' => $capability,
                'staff_role_name' => $roleName !== '' ? $roleName : null,
            ]);
        }

        return $staffActor;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function throwStaffErrorResponse(Request $request, int $status, array $payload): never
    {
        $code = (string) ($payload['error_code'] ?? 'unauthorized');
        $message = (string) ($payload['message'] ?? 'Unauthorized.');

        unset($payload['error_code'], $payload['message']);

        throw new HttpResponseException(ApiErrorResponse::json(
            $request,
            $status,
            $code,
            $message,
            extra: $payload,
        ));
    }
}
