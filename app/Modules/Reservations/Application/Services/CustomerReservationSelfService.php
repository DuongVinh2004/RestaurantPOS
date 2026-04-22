<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Policies\ReservationAccessScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerReservationSelfService
{
    public function __construct(
        private readonly ReservationSessionAccessWorkflow $customerSessionAccessService,
        private readonly ReservationService $reservationService,
        private readonly ReservationRescheduleService $reservationRescheduleService,
        private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return array{scope:string,paginator:LengthAwarePaginator}
     */
    public function listAccessibleReservations(?int $customerUserId, ?string $sessionId, array $filters = []): array
    {
        if ($customerUserId !== null) {
            return [
                'scope' => ReservationAccessScope::OWNER,
                'paginator' => $this->paginateOwnerReservations($customerUserId, $filters),
            ];
        }

        if ($sessionId !== null && trim($sessionId) !== '') {
            return [
                'scope' => ReservationAccessScope::SESSION,
                'paginator' => $this->paginateSessionReservations(trim($sessionId), $filters),
            ];
        }

        throw ValidationException::withMessages([
            'customer' => ['Customer authentication or a valid session_id is required.'],
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function cancelAccessibleReservation(int $reservationId, ?int $customerUserId, ?string $sessionId, array $payload = []): Reservation
    {
        $reservation = $this->findAccessibleReservationOrFail($reservationId, $customerUserId, $sessionId);

        $this->assertCustomerCanCancel($reservation);

        $actorUserId = $customerUserId;
        $cancelReason = isset($payload['cancel_reason']) && trim((string) $payload['cancel_reason']) !== ''
            ? trim((string) $payload['cancel_reason'])
            : 'Cancelled by customer self-service';

        $updatedReservation = $this->reservationService->updateReservationStatus(
            reservationId: $reservationId,
            newStatus: ReservationStatus::Cancelled->value,
            expectedRowVersion: isset($payload['row_version']) ? (int) $payload['row_version'] : null,
            actorUserId: $actorUserId,
            options: [
                'cancel_reason' => $cancelReason,
                'force' => false,
                'audit_context' => $sessionId !== null && trim($sessionId) !== '' && $customerUserId === null
                    ? ['customer_session_id' => trim($sessionId)]
                    : [],
            ],
        );

        return $this->refreshReservationForScope($updatedReservation, $customerUserId);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function rescheduleAccessibleReservation(int $reservationId, ?int $customerUserId, ?string $sessionId, array $payload = []): Reservation
    {
        $reservation = $this->findAccessibleReservationOrFail($reservationId, $customerUserId, $sessionId);

        $this->assertCustomerCanReschedule($reservation);

        $actorUserId = $customerUserId;
        $normalizedPayload = $payload;
        $normalizedPayload['reason'] = isset($payload['reason']) && trim((string) $payload['reason']) !== ''
            ? trim((string) $payload['reason'])
            : 'Customer self-service reschedule';

        $updatedReservation = $this->reservationRescheduleService->reschedule($reservationId, $normalizedPayload, [
            'type' => $customerUserId !== null ? 'customer' : 'customer_session',
            'user_id' => $actorUserId,
            'session_id' => $customerUserId === null && $sessionId !== null ? trim($sessionId) : null,
        ]);

        return $this->refreshReservationForScope($updatedReservation, $customerUserId);
    }

    public function findAccessibleReservationOrFail(int $reservationId, ?int $customerUserId, ?string $sessionId): Reservation
    {
        if ($customerUserId !== null) {
            /** @var Reservation|null $reservation */
            $reservation = Reservation::query()
                ->where('reservation_id', $reservationId)
                ->where('user_id', $customerUserId)
                ->first();

            if ($reservation instanceof Reservation) {
                return $reservation;
            }

            throw new ModelNotFoundException('Reservation not found.');
        }

        $sessionId = trim((string) $sessionId);
        /** @var Reservation|null $reservation */
        $reservation = Reservation::query()->find($reservationId);

        if (! $reservation instanceof Reservation || $sessionId === '' || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $sessionId)) {
            throw new ModelNotFoundException('Reservation not found.');
        }

        return $reservation;
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function paginateOwnerReservations(int $customerUserId, array $filters): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $bucket = $this->resolveBucket($filters);
        $statuses = $this->resolveStatuses($filters);

        $query = Reservation::query()
            ->where('user_id', $customerUserId)
            ->with($this->relationsForScope(ReservationAccessScope::OWNER));

        $this->applyBucketFilter($query, $bucket);
        $this->applyStatusFilter($query, $statuses);

        if (! empty($filters['from'])) {
            $query->where('start_time', '>=', Carbon::parse((string) $filters['from'])->utc());
        }

        if (! empty($filters['to'])) {
            $query->where('start_time', '<=', Carbon::parse((string) $filters['to'])->utc());
        }

        $this->applySort($query, $bucket);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function paginateSessionReservations(string $sessionId, array $filters): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $bucket = $this->resolveBucket($filters);
        $statuses = $this->resolveStatuses($filters);

        $reservationIds = DB::table('table_holds')
            ->where('session_id', $sessionId)
            ->whereNotNull('confirmed_reservation_id')
            ->orderByDesc('created_at')
            ->pluck('confirmed_reservation_id')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        if ($reservationIds === []) {
            return $this->paginateCollection(collect(), $perPage, $page, $filters);
        }

        $query = Reservation::query()
            ->whereIn('reservation_id', $reservationIds)
            ->with($this->relationsForScope(ReservationAccessScope::SESSION));

        $this->applyBucketFilter($query, $bucket);
        $this->applyStatusFilter($query, $statuses);

        if (! empty($filters['from'])) {
            $query->where('start_time', '>=', Carbon::parse((string) $filters['from'])->utc());
        }

        if (! empty($filters['to'])) {
            $query->where('start_time', '<=', Carbon::parse((string) $filters['to'])->utc());
        }

        $this->applySort($query, $bucket);

        $reservations = $query->get()
            ->filter(fn (Reservation $reservation) => $this->customerSessionAccessService->canAccessReservationBySession($reservation, $sessionId))
            ->values();

        return $this->paginateCollection($reservations, $perPage, $page, $filters);
    }

    private function assertCustomerCanCancel(Reservation $reservation): void
    {
        $status = $reservation->status instanceof ReservationStatus
            ? $reservation->status
            : ReservationStatus::from((string) $reservation->getRawOriginal('status'));

        if ($status !== ReservationStatus::Confirmed) {
            throw ValidationException::withMessages([
                'status' => ['Only Confirmed reservations can be cancelled by the customer self-service flow.'],
            ]);
        }

        if ($reservation->checked_in_at !== null) {
            throw ValidationException::withMessages([
                'status' => ['Checked-in reservations cannot be cancelled from customer self-service.'],
            ]);
        }

        $cutoffMinutes = max(0, $this->branchSchedulingPolicyService->customerCancellationCutoffMinutes($reservation->branch_id, false));
        $cutoffAt = Carbon::parse((string) $reservation->start_time)->utc()->subMinutes($cutoffMinutes);
        if (Carbon::now('UTC')->gte($cutoffAt)) {
            throw ValidationException::withMessages([
                'reservation' => [sprintf('Reservation can only be cancelled at least %d minute(s) before the start time.', $cutoffMinutes)],
            ]);
        }
    }

    private function refreshReservationForScope(Reservation $reservation, ?int $customerUserId): Reservation
    {
        $scope = $customerUserId !== null ? ReservationAccessScope::OWNER : ReservationAccessScope::SESSION;

        return Reservation::query()
            ->with($this->relationsForScope($scope))
            ->findOrFail((int) $reservation->reservation_id);
    }

    private function assertCustomerCanReschedule(Reservation $reservation): void
    {
        $status = $reservation->status instanceof ReservationStatus
            ? $reservation->status
            : ReservationStatus::from((string) $reservation->getRawOriginal('status'));

        if ($status !== ReservationStatus::Confirmed) {
            throw ValidationException::withMessages([
                'status' => ['Only Confirmed reservations can be rescheduled by the customer self-service flow.'],
            ]);
        }

        if ($reservation->checked_in_at !== null) {
            throw ValidationException::withMessages([
                'status' => ['Checked-in reservations cannot be rescheduled from customer self-service.'],
            ]);
        }

        $cutoffMinutes = max(0, $this->branchSchedulingPolicyService->customerRescheduleCutoffMinutes($reservation->branch_id, false));
        $cutoffAt = Carbon::parse((string) $reservation->start_time)->utc()->subMinutes($cutoffMinutes);
        if (Carbon::now('UTC')->gte($cutoffAt)) {
            throw ValidationException::withMessages([
                'reservation' => [sprintf('Reservation can only be rescheduled at least %d minute(s) before the start time.', $cutoffMinutes)],
            ]);
        }
    }

    private function resolvePerPage(array $filters): int
    {
        $max = max(1, (int) config('booking.customer_reservation_self_service_page_max', 20));
        $default = min($max, max(1, (int) config('booking.customer_reservation_self_service_page_default', 10)));
        $requested = isset($filters['per_page']) ? (int) $filters['per_page'] : $default;

        return max(1, min($max, $requested));
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function resolveBucket(array $filters): string
    {
        $bucket = strtolower(trim((string) ($filters['bucket'] ?? 'upcoming')));

        return in_array($bucket, ['upcoming', 'history', 'all'], true) ? $bucket : 'upcoming';
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return list<string>
     */
    private function resolveStatuses(array $filters): array
    {
        $statuses = array_map(
            static fn ($value) => trim((string) $value),
            (array) ($filters['status'] ?? [])
        );

        return array_values(array_filter($statuses, static fn (string $value) => $value !== ''));
    }

    private function applyBucketFilter($query, string $bucket): void
    {
        $now = Carbon::now('UTC');

        if ($bucket === 'upcoming') {
            $query->where(function ($inner) use ($now): void {
                $inner->whereNotIn('status', [
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::Expired->value,
                    ReservationStatus::Completed->value,
                    ReservationStatus::NoShow->value,
                ])->where('end_time', '>=', $now);
            });

            return;
        }

        if ($bucket === 'history') {
            $query->where(function ($inner) use ($now): void {
                $inner->whereIn('status', [
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::Expired->value,
                    ReservationStatus::Completed->value,
                    ReservationStatus::NoShow->value,
                ])->orWhere('end_time', '<', $now);
            });
        }
    }

    /**
     * @param  list<string>  $statuses
     */
    private function applyStatusFilter($query, array $statuses): void
    {
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }
    }

    private function applySort($query, string $bucket): void
    {
        if ($bucket === 'upcoming') {
            $query->orderBy('start_time')->orderBy('reservation_id');

            return;
        }

        $query->orderByDesc('start_time')->orderByDesc('reservation_id');
    }

    /**
     * @param  Collection<int,Reservation>  $items
     * @param  array<string,mixed>  $filters
     */
    private function paginateCollection(Collection $items, int $perPage, int $page, array $filters): LengthAwarePaginator
    {
        $offset = max(0, ($page - 1) * $perPage);
        $results = $items->slice($offset, $perPage)->values();

        return new Paginator(
            $results,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => '/api/v1/reservations',
                'query' => array_filter($filters, static fn ($value) => $value !== null && $value !== '' && $value !== []),
            ]
        );
    }

    /**
     * @return list<string>
     */
    private function relationsForScope(string $scope): array
    {
        return match ($scope) {
            ReservationAccessScope::SESSION => [
                'user',
                'tables',
                'orders.items.item',
            ],
            ReservationAccessScope::OWNER => [
                'user',
                'user.points',
                'user.currentTier',
                'tables',
                'orders.items.item',
                'payments',
                'depositPaymentSessions',
                'appliedUserVoucher.voucher',
            ],
            default => [
                'user',
                'tables',
            ],
        };
    }
}
