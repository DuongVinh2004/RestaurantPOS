<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\UseCases\ServiceSessions;

use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationCodeGenerator;
use App\Modules\Reservations\Application\Services\ReservationConflictValidator;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\AuditEvent;
use App\Support\Auth\StaffActorGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mở service session tại chỗ cho khách walk-in và tra cứu session đang phục vụ theo bàn.
 */
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
        private readonly ?StaffBranchContextService $staffBranchContextService = null,
    ) {}

    public function createWalkInSession(array $payload, ?int $staffUserId = null): Reservation
    {
        // Walk-in được tạo thành reservation đã check-in ngay để POS dùng cùng một mô hình dữ liệu.
        // Pha 1: chuan hoa actor va input de walk-in session di theo mot payload viet ro.
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);
        $tableIds = $this->normalizeTableIds((array) ($payload['table_ids'] ?? []));
        $guestCount = (int) ($payload['guest_count'] ?? 0);
        $startedAt = isset($payload['started_at'])
            ? Carbon::parse((string) $payload['started_at'])->utc()
            : Carbon::now('UTC');

        // Lock theo table ids de khong co hai luong cung mo walk-in tren cung mot ban.
        return $this->locks->withTableLocks($tableIds, function () use ($payload, $tableIds, $guestCount, $startedAt, $staffUserId) {
            return DB::transaction(function () use ($payload, $tableIds, $guestCount, $startedAt, $staffUserId) {
                // Pha 2: lock table rows va gate capacity/allocatable truoc khi tao reservation.
                $tables = $this->conflictValidator->lockAndLoadTables($tableIds);
                $this->conflictValidator->assertTablesAllocatableAndCapacity($tables, $tableIds, $guestCount);

                // Suy ra chi nhánh vận hành từ bàn và kiểm tra staff có quyền thao tác tại đó.
                $tableBranchId = $this->reservationBranchScopeService->resolveTableBranchId(
                    $tables->pluck('branch_id')->all(),
                    'Selected tables must belong to a single branch.',
                    'table_ids',
                );
                $tableBranchId = $this->branchContextService->resolveBranchId($tableBranchId, true);
                $this->assertOperationalBranchAccessible($tableBranchId, $staffUserId);

                if (array_key_exists('branch_id', $payload) && $payload['branch_id'] !== null && $payload['branch_id'] !== '') {
                    $this->branchContextService->assertSameBranch(
                        $payload['branch_id'],
                        $tableBranchId,
                        'Selected tables do not belong to the requested branch.',
                        'branch_id',
                        true,
                    );
                }

                // Service window cua walk-in duoc suy ra tu payload hoac branch policy waiting list.
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

                // Conflict gate nay bao ve ca overlap reservation va cac rang buoc create khac.
                $this->conflictValidator->assertNoCreateConflicts($tableIds, $startedAt, $endAt);

                // Nếu là khách vãng lai, hệ thống tạo hồ sơ tối thiểu để reservation và billing bám vào.
                // Pha 3: xac dinh customer hien huu hay auto-tao profile toi thieu cho khach vang lai.
                [$customer, $customerCreated] = $this->resolveWalkInCustomer($payload);
                $branch = $this->branchSchedulingPolicyService->resolveBranch($tableBranchId, true);

                // Pha 4: tao reservation nguon WalkIn va mark checked-in ngay de POS dung chung model reservation.
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

                // Từ thời điểm này bàn đã có khách, outbox và realtime sẽ báo cho các màn hình liên quan.
                // Pha 5: board state, outbox va realtime duoc day ngay sau khi reservation/table mapping da ton tai.
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

                app(OperationalRealtimeService::class)->publishBoardEvent(
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

    public function findActiveSessionByTable(int $tableId, ?int $staffUserId = null): ?Reservation
    {
        // Doc active session theo table cung phai di qua branch scope va realtime table state.
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        // Chỉ trả session khi bàn đang Occupied và staff có quyền xem đúng chi nhánh.
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
        if (! $this->staffCanAccessBranch($tableBranchId, $staffUserId)) {
            return null;
        }

        // Query nay tim reservation dang phuc vu gan nhat tren table, uu tien checked_in_at moi nhat.
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

        // Sau khi load reservation day du, branch scope duoc kiem lai lan cuoi de tranh drift data.
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
        // Walk-in uu tien gan vao customer co san; neu khong co moi roi xuong guest profile toi thieu.
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

        // Phone la diem noi lai customer cu neu khach da tung ton tai trong he thong.
        $phone = $this->normalizeNullableString($payload['phone'] ?? null);
        if ($phone !== null) {
            /** @var User|null $existingCustomer */
            $existingCustomer = User::query()
                ->with('role')
                ->where('phone', $phone)
                ->first();

            if ($existingCustomer instanceof User) {
                if ((bool) $existingCustomer->is_deleted || ! $this->isAllowedWalkInCustomer($existingCustomer)) {
                    throw ValidationException::withMessages([
                        'phone' => ['This phone number is already linked to a non-customer account.'],
                    ]);
                }

                return [$existingCustomer, false];
            }
        }

        $customer = new User;
        $customer->username = $this->generateWalkInUsername();
        $customer->password_hash = null;
        $customer->full_name = $guestName;
        $customer->email = null;
        $customer->phone = $phone;
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

    private function assertOperationalBranchAccessible(int $branchId, ?int $staffUserId): void
    {
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        $this->staffBranchContextService()->assertAccessibleBranch($staffUserId, $branchId);
    }

    private function staffCanAccessBranch(int $branchId, ?int $staffUserId): bool
    {
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        try {
            $this->staffBranchContextService()->assertAccessibleBranch($staffUserId, $branchId);

            return true;
        } catch (ModelNotFoundException) {
            return false;
        }
    }

    private function staffBranchContextService(): StaffBranchContextService
    {
        return $this->staffBranchContextService ?? app(StaffBranchContextService::class);
    }
}
