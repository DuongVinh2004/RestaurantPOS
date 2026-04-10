<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use App\Services\Branch\BranchContextService;
use App\Services\Branch\BranchSchedulingPolicyService;
use App\Services\Branch\ReservationBranchScopeService;
use App\Services\NotificationOutboxService;
use App\Services\Reservation\ReservationConflictValidator;
use App\Services\ReservationCodeGenerator;
use App\Services\ReservationLockService;
use App\Services\RestaurantTableStateService;
use App\Support\AuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffServiceSessionService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly ReservationConflictValidator $conflictValidator,
        private readonly BranchContextService $branchContextService,
        private readonly ReservationBranchScopeService $reservationBranchScopeService,
        private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService,
        private readonly ReservationCodeGenerator $reservationCodeGenerator,
        private readonly RestaurantTableStateService $tableStateService,
        private readonly NotificationOutboxService $notificationOutboxService,
    ) {}

    public function createWalkInSession(array $payload, ?int $staffUserId = null): Reservation
    {
        $tableIds = $this->normalizeTableIds((array) ($payload['table_ids'] ?? []));
        $guestCount = (int) ($payload['guest_count'] ?? 0);
        $startedAt = isset($payload['started_at'])
            ? Carbon::parse((string) $payload['started_at'])->utc()
            : Carbon::now('UTC');

        return $this->locks->withTableLocks($tableIds, function () use ($payload, $tableIds, $guestCount, $startedAt, $staffUserId) {
            return DB::transaction(function () use ($payload, $tableIds, $guestCount, $startedAt, $staffUserId) {
                $tables = $this->conflictValidator->lockAndLoadTables($tableIds);
                $this->conflictValidator->assertTablesAllocatableAndCapacity($tables, $tableIds, $guestCount);

                $tableBranchId = $this->reservationBranchScopeService->resolveTableBranchId(
                    $tables->pluck('branch_id')->all(),
                    'Selected tables must belong to a single branch.',
                    'table_ids',
                );
                $tableBranchId = $this->branchContextService->resolveBranchId($tableBranchId, true);

                if (array_key_exists('branch_id', $payload) && $payload['branch_id'] !== null && $payload['branch_id'] !== '') {
                    $this->branchContextService->assertSameBranch(
                        $payload['branch_id'],
                        $tableBranchId,
                        'Selected tables do not belong to the requested branch.',
                        'branch_id',
                        true,
                    );
                }

                $serviceMinutes = isset($payload['service_minutes'])
                    ? (int) $payload['service_minutes']
                    : $this->branchSchedulingPolicyService->waitingListServiceMinutes($tableBranchId, true);
                $serviceMinutes = max(30, min(480, $serviceMinutes));
                $endAt = $startedAt->copy()->addMinutes($serviceMinutes);

                $this->branchSchedulingPolicyService->assertOperationalServiceWindowOpen(
                    $tableBranchId,
                    $startedAt,
                    $endAt,
                    'branch_id',
                    true,
                );

                $this->conflictValidator->assertNoCreateConflicts($tableIds, $startedAt, $endAt);

                [$customer, $customerCreated] = $this->resolveWalkInCustomer($payload);
                $branch = $this->branchSchedulingPolicyService->resolveBranch($tableBranchId, true);

                $reservation = new Reservation;
                $reservation->branch_id = $tableBranchId;
                $reservation->user_id = (int) $customer->user_id;
                $reservation->reservation_code = $this->reservationCodeGenerator->generate($startedAt->copy());
                $reservation->reserved_at = Carbon::now('UTC');
                $reservation->start_time = $startedAt;
                $reservation->end_time = $endAt;
                $reservation->guest_count = $guestCount;
                $reservation->status = ReservationStatus::checkedIn();
                $reservation->source = 'WalkIn';
                $reservation->checked_in_at = $startedAt;
                $reservation->bill_currency = (string) ($branch->currency ?: 'VND');
                $reservation->notes = $this->normalizeNullableString($payload['notes'] ?? null);
                $reservation->created_by = $staffUserId;
                $reservation->updated_by = $staffUserId;
                $reservation->save();
                $reservation->tables()->attach($tableIds);

                $this->tableStateService->occupyTables(
                    $tableIds,
                    $startedAt,
                    $staffUserId,
                    [
                        'reservation_id' => (int) $reservation->reservation_id,
                        'source' => 'staff_service_session_walk_in',
                        'reason' => 'walk_in_service_session',
                    ],
                );

                $reservation->load(['user', 'tables', 'orders.items.item', 'payments']);
                $this->notificationOutboxService->enqueueReservationCheckedIn($reservation);

                AuditEvent::info('staff.service_session.walk_in_created', [
                    '_audit' => [
                        'action' => 'service_session.walk_in_created',
                        'entity_type' => 'reservation',
                        'entity_id' => (string) $reservation->reservation_id,
                        'subjects' => array_merge(
                            array_map(
                                static fn (int $tableId): array => [
                                    'type' => 'restaurant_table',
                                    'id' => (string) $tableId,
                                    'role' => 'table',
                                ],
                                $tableIds,
                            ),
                            [[
                                'type' => 'user',
                                'id' => (string) $customer->user_id,
                                'role' => 'customer',
                            ]],
                        ),
                        'after' => [
                            'status' => ReservationStatus::checkedInDbValue(),
                            'source' => 'WalkIn',
                            'checked_in_at' => $startedAt->toIso8601String(),
                            'start_time_utc' => $startedAt->toIso8601String(),
                            'end_time_utc' => $endAt->toIso8601String(),
                            'guest_count' => $guestCount,
                            'table_ids' => $tableIds,
                        ],
                        'summary' => [
                            'branch_id' => $tableBranchId,
                            'table_count' => count($tableIds),
                            'service_minutes' => $serviceMinutes,
                            'customer_auto_created' => $customerCreated,
                        ],
                        'actor' => [
                            'type' => 'staff_user',
                            'user_id' => $staffUserId,
                        ],
                    ],
                    'reservation_id' => (int) $reservation->reservation_id,
                    'reservation_code' => (string) $reservation->reservation_code,
                    'user_id' => (int) $customer->user_id,
                    'guest_count' => $guestCount,
                    'table_ids' => $tableIds,
                    'branch_id' => $tableBranchId,
                    'started_at_utc' => $startedAt->toIso8601String(),
                    'ended_at_utc' => $endAt->toIso8601String(),
                    'service_minutes' => $serviceMinutes,
                    'customer_auto_created' => $customerCreated,
                    'staff_user_id' => $staffUserId,
                ]);

                app(StaffOperationalRealtimeService::class)->publishBoardEvent(
                    'service_session.walk_in_created',
                    [
                        'reservation_id' => (int) $reservation->reservation_id,
                        'table_ids' => $tableIds,
                        'branch_id' => $tableBranchId,
                    ],
                    ['board'],
                );

                return $reservation;
            });
        });
    }

    public function findActiveSessionByTable(int $tableId): ?Reservation
    {
        /** @var RestaurantTable|null $table */
        $table = RestaurantTable::query()
            ->where('table_id', $tableId)
            ->where('is_deleted', false)
            ->first();

        if (! $table instanceof RestaurantTable) {
            return null;
        }

        $tableStatus = (string) ($table->status?->value ?? $table->status);
        if ($tableStatus !== RestaurantTableStatus::Occupied->value) {
            return null;
        }

        $tableBranchId = $this->branchContextService->resolveBranchId($table->branch_id, true);
        $reservationId = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->where('rt.table_id', $tableId)
            ->where(function ($query): void {
                $query->where('r.status', ReservationStatus::checkedInDbValue())
                    ->orWhere(function ($legacy): void {
                        $legacy->whereIn('r.status', ReservationStatus::activeDbValues())
                            ->whereNotNull('r.checked_in_at');
                    });
            })
            ->whereNull('r.checked_out_at')
            ->whereNull('r.cancelled_at')
            ->whereNull('r.no_show_at')
            ->orderByDesc('r.checked_in_at')
            ->orderByDesc('r.reservation_id')
            ->value('r.reservation_id');

        if ($reservationId === null) {
            return null;
        }

        /** @var Reservation|null $reservation */
        $reservation = Reservation::query()
            ->with(['user', 'tables', 'orders.items.item', 'payments'])
            ->where('reservation_id', (int) $reservationId)
            ->first();

        if (! $reservation instanceof Reservation) {
            return null;
        }

        if (! $reservation->tables->contains(static fn (RestaurantTable $assignedTable): bool => (int) $assignedTable->table_id === $tableId)) {
            return null;
        }

        $this->reservationBranchScopeService->assertReservationMatchesTableBranches(
            $reservation->branch_id,
            $reservation->tables->pluck('branch_id')->all(),
            'Assigned tables must belong to a single branch.',
            'Reservation branch does not match the assigned table branch.',
            'reservation_id',
        );

        $this->branchContextService->assertSameBranch(
            $reservation->branch_id,
            $tableBranchId,
            'Reservation branch does not match the requested table branch.',
            'table_id',
            true,
        );

        return $reservation;
    }

    /**
     * @return array{0:User,1:bool}
     */
    private function resolveWalkInCustomer(array $payload): array
    {
        $userId = isset($payload['user_id']) ? (int) $payload['user_id'] : 0;
        if ($userId > 0) {
            return [$this->resolveExistingWalkInCustomer($userId), false];
        }

        $guestName = trim((string) ($payload['guest_name'] ?? ''));
        if ($guestName === '') {
            throw ValidationException::withMessages([
                'guest_name' => ['guest_name is required when user_id is not provided.'],
            ]);
        }

        $customer = new User;
        $customer->username = $this->generateWalkInUsername();
        $customer->password_hash = null;
        $customer->full_name = $guestName;
        $customer->email = null;
        $customer->phone = $this->normalizeNullableString($payload['phone'] ?? null);
        $customer->role_id = $this->ensureCustomerRoleId();
        $customer->language_pref = 'vn';
        $customer->is_deleted = false;
        $customer->save();

        return [$customer->refresh(), true];
    }

    private function resolveExistingWalkInCustomer(int $userId): User
    {
        /** @var User|null $customer */
        $customer = User::query()
            ->with('role')
            ->where('user_id', $userId)
            ->where('is_deleted', false)
            ->first();

        if (! $customer instanceof User) {
            throw ValidationException::withMessages([
                'user_id' => ['Selected user is invalid or deleted.'],
            ]);
        }

        if (! $this->isAllowedWalkInCustomer($customer)) {
            throw ValidationException::withMessages([
                'user_id' => ['Selected user must belong to a configured customer role.'],
            ]);
        }

        return $customer;
    }

    private function isAllowedWalkInCustomer(User $customer): bool
    {
        $allowedRoleIds = array_values(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            (array) config('customer_auth.allowed_role_ids', [3])
        ), static fn (int $value): bool => $value > 0));

        if ($allowedRoleIds !== []) {
            return in_array((int) ($customer->role_id ?? 0), $allowedRoleIds, true);
        }

        return mb_strtolower(trim((string) ($customer->role?->role_name ?? ''))) === 'customer';
    }

    private function ensureCustomerRoleId(): int
    {
        $role = Role::query()->firstOrCreate([
            'role_name' => 'Customer',
        ]);

        return (int) $role->role_id;
    }

    private function generateWalkInUsername(): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = 'walkin_'.Str::lower(Str::random(12));

            if (! User::query()->where('username', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'guest_name' => ['Unable to allocate a walk-in customer profile. Please try again.'],
        ]);
    }

    /**
     * @param  array<int,mixed>  $tableIds
     * @return array<int,int>
     */
    private function normalizeTableIds(array $tableIds): array
    {
        $normalized = array_values(array_unique(array_filter(
            array_map('intval', $tableIds),
            static fn (int $tableId): bool => $tableId > 0,
        )));
        sort($normalized);

        return $normalized;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
