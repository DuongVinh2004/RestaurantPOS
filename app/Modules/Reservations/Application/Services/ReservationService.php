<?php

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Enums\TableHoldStatus;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\BranchScheduling\Application\Services\TableHoldService;
use App\Modules\BranchScheduling\Application\Services\TableTimeConflictService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\BranchScheduling\Domain\Models\TableHold;
use App\Modules\Catalog\Application\UseCases\PolicyPreview\MenuPreorderPolicyService;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Promotions\Domain\Models\UserVoucher;
use App\Modules\Promotions\Domain\Policies\VoucherRedemptionSupport;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Models\ReservationTable;
use App\Modules\Reservations\Domain\Policies\ReservationStatusTransitionPolicy;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use App\Support\AvailabilityCacheVersion;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service reservation tổng hợp theo kiểu legacy:
 * ôm các luồng create, đổi trạng thái, và vài helper nghiệp vụ dùng chung.
 */
class ReservationService
{
    private TableHoldService $tableHoldService;

    private ReservationLockService $lockService;

    private ReservationCodeGenerator $codeGenerator;

    private NotificationOutboxService $notificationOutboxService;

    private LoyaltyPointsService $loyaltyPointsService;

    private RestaurantTableStateService $tableStateService;

    private TableTimeConflictService $tableTimeConflictService;

    private ReservationFinancialSyncService $reservationFinancialSyncService;

    private BranchContextService $branchContextService;

    private BranchSchedulingPolicyService $branchSchedulingPolicyService;

    private MenuPreorderPolicyService $menuPreorderPolicyService;

    private StaffBranchContextService $staffBranchContextService;

    public function __construct(
        TableHoldService $tableHoldService,
        ReservationLockService $lockService,
        ReservationCodeGenerator $codeGenerator,
        NotificationOutboxService $notificationOutboxService,
        LoyaltyPointsService $loyaltyPointsService,
        RestaurantTableStateService $tableStateService,
        TableTimeConflictService $tableTimeConflictService,
        ReservationFinancialSyncService $reservationFinancialSyncService,
        BranchContextService|BranchSchedulingPolicyService|MenuPreorderPolicyService|null $branchContextService = null,
        BranchSchedulingPolicyService|MenuPreorderPolicyService|null $branchSchedulingPolicyService = null,
        ?MenuPreorderPolicyService $menuPreorderPolicyService = null,
        ?StaffBranchContextService $staffBranchContextService = null,
    ) {
        // Xử lý fallback để tương thích ngược khi inject các dependency mới
        if ($branchContextService instanceof BranchSchedulingPolicyService && $branchSchedulingPolicyService === null) {
            $branchSchedulingPolicyService = $branchContextService;
            $branchContextService = null;
        }

        if ($branchContextService instanceof MenuPreorderPolicyService && $menuPreorderPolicyService === null) {
            $menuPreorderPolicyService = $branchContextService;
            $branchContextService = null;
        }

        if ($branchSchedulingPolicyService instanceof MenuPreorderPolicyService && $menuPreorderPolicyService === null) {
            $menuPreorderPolicyService = $branchSchedulingPolicyService;
            $branchSchedulingPolicyService = null;
        }

        $this->tableHoldService = $tableHoldService;
        $this->lockService = $lockService;
        $this->codeGenerator = $codeGenerator;
        $this->notificationOutboxService = $notificationOutboxService;
        $this->loyaltyPointsService = $loyaltyPointsService;
        $this->tableStateService = $tableStateService;
        $this->tableTimeConflictService = $tableTimeConflictService;
        $this->reservationFinancialSyncService = $reservationFinancialSyncService;
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
        $this->branchSchedulingPolicyService = $branchSchedulingPolicyService ?? app(BranchSchedulingPolicyService::class);
        $this->menuPreorderPolicyService = $menuPreorderPolicyService ?? app(MenuPreorderPolicyService::class);
        $this->staffBranchContextService = $staffBranchContextService ?? app(StaffBranchContextService::class);
    }

    /**
     * --- HÀM CHÍNH: TẠO MỚI ĐƠN ĐẶT BÀN ---
     * Điều phối toàn bộ quy trình: Chọn bàn, kiểm tra chỗ, giữ bàn, đặt món trước, ghi log.
     */
    public function createReservation(array $payload, ?int $actorUserId = null, array $options = []): Reservation
    {
        // 1. Chuẩn hóa thời gian về UTC để tránh lệch múi giờ
        $startUtc = Carbon::parse((string) $payload['start_time'])->utc();
        $endUtc = Carbon::parse((string) $payload['end_time'])->utc();

        // Lấy các tham số cấu hình tùy chọn
        $holdId = isset($payload['hold_id']) ? (string) $payload['hold_id'] : null;
        $sessionId = isset($payload['session_id']) ? (string) $payload['session_id'] : null;
        $skipLocking = (bool) ($options['skip_locking'] ?? false);
        $policyNowUtc = $options['policy_now_utc'] ?? null;
        $policyUseCase = isset($options['policy_use_case']) && is_string($options['policy_use_case']) && $options['policy_use_case'] !== ''
            ? $options['policy_use_case']
            : 'reservation';

        // Mảng chứa các ID giữ bàn an toàn
        $trustedHoldIds = array_values(array_unique(array_filter(
            array_map('strval', (array) ($options['trusted_hold_ids'] ?? [])),
            static fn (string $value) => $value !== ''
        )));

        // Lấy danh sách ID bàn từ Request truyền lên, hoặc chiết xuất từ `hold_id`
        $tableIds = $this->resolveTableIdsFromPayloadOrHold($payload, $holdId, $sessionId, $startUtc, $endUtc);
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        sort($tableIds);

        if (is_string($holdId) && $holdId !== '') {
            $trustedHoldIds[] = $holdId;
            $trustedHoldIds = array_values(array_unique($trustedHoldIds));
        }

        // 2. Định nghĩa Transaction Runner (Đảm bảo an toàn dữ liệu All-or-Nothing)
        $runner = function () use ($payload, $actorUserId, $startUtc, $endUtc, $tableIds, $holdId, $sessionId, $trustedHoldIds, $policyNowUtc, $policyUseCase) {
            return DB::transaction(function () use ($payload, $actorUserId, $startUtc, $endUtc, $tableIds, $holdId, $sessionId, $trustedHoldIds, $policyNowUtc, $policyUseCase) {

                // Dọn dẹp các yêu cầu giữ bàn cũ rác
                $this->tableHoldService->expireStaleHolds();

                // Xác định danh tính khách hàng (User hay Guest)
                $userId = $this->resolveReservationUserId($payload, $actorUserId);
                $guestSnapshot = $this->resolveGuestSnapshot($payload, $userId);

                $user = User::query()
                    ->where('user_id', $userId)
                    ->where('is_deleted', 0)
                    ->first();
                if ($userId !== null && ! $user) {
                    throw ValidationException::withMessages([
                        'user_id' => ['User không tồn tại hoặc đã bị xoá.'],
                    ]);
                }

                // Lock record giữ chỗ lại để không ai khác cướp được
                $holdBranchId = null;
                if (is_string($holdId) && $holdId !== '' && is_string($sessionId) && $sessionId !== '') {
                    $holdBranchId = $this->lockAndAssertActiveHoldForReservation($holdId, $sessionId, $startUtc, $endUtc);
                }

                // 3. THẨM ĐỊNH BÀN (Table Validation)
                $guestCount = (int) $payload['guest_count'];

                // Trích xuất bàn vật lý và khóa DB (Pessimistic Lock)
                $tables = RestaurantTable::query()
                    ->whereIn('table_id', $tableIds)
                    ->lockForUpdate()
                    ->get();

                if ($tables->count() !== count($tableIds)) {
                    throw ValidationException::withMessages([
                        'table_ids' => ['Có bàn không tồn tại.'],
                    ]);
                }

                $deletedTables = $tables->where('is_deleted', 1)->pluck('table_id')->values()->all();
                if (! empty($deletedTables)) {
                    throw ValidationException::withMessages([
                        'table_ids' => ['Có bàn đã bị xoá: '.implode(',', $deletedTables)],
                    ]);
                }

                // Bàn có sẵn sàng để đặt không?
                $nonAllocatable = $tables->filter(fn ($t) => ! $this->tableStateService->isAllocatableForBooking((string) ($t->status?->value ?? $t->status)))
                    ->pluck('table_id')->values()->all();
                if (! empty($nonAllocatable)) {
                    throw ValidationException::withMessages([
                        'table_ids' => ['Có bàn không ở trạng thái Available: '.implode(',', $nonAllocatable)],
                    ]);
                }

                // Đảm bảo tất cả các bàn được chọn thuộc CÙNG 1 chi nhánh
                $tableBranchId = $this->branchContextService->assertSingleBranch(
                    $tables->pluck('branch_id')->all(),
                    'Selected tables must belong to a single branch.',
                    'table_ids',
                    false
                );

                if (array_key_exists('branch_id', $payload) && $payload['branch_id'] !== null && $payload['branch_id'] !== '') {
                    $this->branchContextService->assertSameBranch(
                        $payload['branch_id'],
                        $tableBranchId,
                        'Selected tables do not belong to the requested branch.',
                        'branch_id',
                        false
                    );
                }

                if ($holdBranchId !== null) {
                    $this->branchContextService->assertSameBranch(
                        $holdBranchId,
                        $tableBranchId,
                        'Hold branch does not match the selected table branch.',
                        'hold_id',
                        false
                    );
                }

                // 4. THẨM ĐỊNH NGHIỆP VỤ NHÀ HÀNG (Giờ mở cửa, Sức chứa, Đụng độ)
                $this->branchSchedulingPolicyService->assertReservationWindowAllowed(
                    $tableBranchId,
                    $startUtc,
                    $endUtc,
                    'start_time',
                    $policyNowUtc instanceof \DateTimeInterface ? Carbon::instance($policyNowUtc)->utc() : null,
                    $policyUseCase,
                    false
                );

                $this->assertCapacityEnough($tables, $guestCount);

                // Kiểm tra xem bàn có đang bị Hold bởi người khác không
                $holdConflicts = $this->tableTimeConflictService->findHoldConflictTableIds($tableIds, $startUtc, $endUtc, $trustedHoldIds, null, true);
                if (! empty($holdConflicts)) {
                    throw ValidationException::withMessages([
                        'table_ids' => ['Bàn đang bị giữ chỗ bởi session khác: '.implode(',', $holdConflicts)],
                    ]);
                }

                // Kiểm tra xem bàn có bị trùng giờ với booking của khách khác không
                $conflictTableIds = $this->tableTimeConflictService->findReservationConflictTableIds($tableIds, $startUtc, $endUtc, null, true);
                if (! empty($conflictTableIds)) {
                    throw ValidationException::withMessages([
                        'table_ids' => ['Bàn bị trùng lịch (overlap reservation): '.implode(',', $conflictTableIds)],
                    ]);
                }

                // 5. KHỞI TẠO ĐƠN ĐẶT BÀN VÀ LƯU VÀO DATABASE
                $reservation = new Reservation;
                $reservation->branch_id = $tableBranchId;
                $reservation->user_id = $userId;
                $reservation->guest_name = $guestSnapshot['guest_name'];
                $reservation->guest_phone = $guestSnapshot['guest_phone'];
                $reservation->guest_email = $guestSnapshot['guest_email'];
                $reservation->reservation_code = $this->codeGenerator->generate($startUtc);
                $now = Carbon::now('UTC');
                $reservation->reserved_at = $now;
                $reservation->start_time = $startUtc;
                $reservation->end_time = $endUtc;
                $reservation->guest_count = $guestCount;
                $reservation->status = ReservationStatus::Confirmed;
                $reservation->source = $actorUserId !== null
                    && $actorUserId > 0
                    && ($userId === null || $actorUserId !== $userId)
                    ? 'Offline' // Nhân viên tạo giúp
                    : 'Online'; // Khách tự tạo
                
                // Online Deposit Lean Rule: Yêu cầu đặt cọc 500,000 VND nếu số lượng khách >= 5 (cho booking Online)
                if ($reservation->source === 'Online' && $guestCount >= 5) {
                    $reservation->deposit_required_amount = 500000.00;
                    $reservation->deposit_status = \App\Enums\DepositStatus::Pending;
                } else {
                    $reservation->deposit_required_amount = 0.00;
                    $reservation->deposit_status = \App\Enums\DepositStatus::NotRequired;
                }

                $reservation->notes = $payload['notes'] ?? null;
                $reservation->created_by = $actorUserId;
                $reservation->updated_by = $actorUserId;
                $reservation->save();

                // Cập nhật trạng thái Hold thành Đã xác nhận (Confirmed)
                if (is_string($holdId) && $holdId !== '' && is_string($sessionId) && $sessionId !== '') {
                    $hold = TableHold::query()
                        ->whereKey($holdId)
                        ->where('session_id', $sessionId)
                        ->where('branch_id', $tableBranchId)
                        ->whereIn('hold_status', [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value])
                        ->lockForUpdate()
                        ->first();

                    if (! $hold instanceof TableHold) {
                        throw ValidationException::withMessages([
                            'hold_id' => ['Hold đã thay đổi trạng thái trong lúc tạo reservation. Hãy reload rồi thử lại.'],
                        ]);
                    }

                    $hold->branch_id = $tableBranchId;
                    $hold->hold_status = TableHoldStatus::Confirmed;
                    $hold->confirmed_reservation_id = (int) $reservation->reservation_id;
                    $hold->user_id = $userId;
                    $hold->expire_at = $now;
                    $hold->updated_at = $now;
                    $hold->updated_by = $actorUserId;
                    $hold->save();
                }

                // Liên kết Bàn với Phiên đặt chỗ
                $reservation->tables()->attach($tableIds);

                // 6. XỬ LÝ GỌI MÓN TRƯỚC (PRE-ORDER)
                $preOrderItems = $payload['pre_order_items'] ?? null;
                if (is_array($preOrderItems) && count($preOrderItems) > 0) {
                    if (! MenuItem::supportsPreorderColumns()) {
                        throw ValidationException::withMessages([
                            'pre_order_items' => ['Hệ thống chưa được đồng bộ contract pre-order. Vui lòng áp dụng patch database mới nhất rồi thử lại.'],
                        ]);
                    }

                    $preparedPreorder = $this->menuPreorderPolicyService->prepareRequestedItems($preOrderItems, $startUtc);
                    $normalizedPreOrderItems = $preparedPreorder['rows'];
                    $menuItems = $preparedPreorder['menu_items'];
                    $priceRows = $preparedPreorder['price_rows'];

                    // Tạo hóa đơn loại PreOrder
                    $order = new ReservationOrder;
                    $order->reservation_id = $reservation->reservation_id;
                    $order->setAttribute('order_type', ReservationOrderType::PreOrder);
                    $order->status = ReservationOrderStatus::Active;
                    $order->created_by = $actorUserId;
                    $order->updated_by = $actorUserId;
                    $order->notes = null;
                    $order->save();

                    // Thêm các món ăn vào hóa đơn
                    foreach ($normalizedPreOrderItems as $row) {
                        $menuItem = $menuItems->get((int) $row['item_id']);
                        $priceRow = $priceRows->get((int) $row['item_id']);
                        $unitPriceMinor = Money::minorUnits($priceRow->price, true);
                        $currency = (string) $priceRow->currency;
                        $quantity = (int) $row['quantity'];

                        $item = new ReservationOrderItem;
                        $item->order_id = $order->order_id;
                        $item->item_id = (int) $row['item_id'];
                        $item->quantity = $quantity;
                        $item->unit_price = Money::formatMinor($unitPriceMinor);
                        $item->currency = $currency;
                        $item->line_total = Money::formatMinor($unitPriceMinor * $quantity);
                        $item->item_name_snapshot = $menuItem ? (string) $menuItem->name : null;
                        $item->status = ReservationOrderItemStatus::Ordered;
                        $item->notes = null;
                        $item->updated_by = $actorUserId;
                        $item->save();
                    }
                }

                // 7. GHI LOG & GỬI THÔNG BÁO
                AuditEvent::info('reservation_created', [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'reservation_code' => (string) $reservation->reservation_code,
                    'user_id' => $reservation->user_id !== null ? (int) $reservation->user_id : null,
                    'guest_name' => $reservation->guest_name,
                    'guest_phone' => $reservation->guest_phone,
                    'guest_email' => $reservation->guest_email,
                    'source' => (string) $reservation->source,
                    'actor_user_id' => $actorUserId,
                    'start_time_utc' => $startUtc->toIso8601String(),
                    'end_time_utc' => $endUtc->toIso8601String(),
                    'table_ids' => $tableIds,
                    'hold_id' => $holdId ?: null,
                ]);

                // Bắn event vào hàng đợi (Queue)
                $this->notificationOutboxService->enqueueReservationCreated($reservation);

                return (int) $reservation->reservation_id;
            });
        };

        // 8. BỌC LUỒNG RUNNER TRONG HỆ THỐNG LOCK BÀN
        try {
            $reservationId = (int) ($skipLocking
                ? $runner()
                : $this->lockService->withTableLocks($tableIds, $runner));
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }

        // Đánh dấu cho cache hệ thống biết dữ liệu đặt bàn đã thay đổi
        AvailabilityCacheVersion::bump();

        return Reservation::query()
            ->with(['user', 'tables', 'orders.items.item', 'payments'])
            ->where('reservation_id', $reservationId)
            ->firstOrFail();
    }

    public function updateStatus(int $reservationId, string $newStatus): Reservation
    {
        // Entry ngắn cho các caller cũ; toàn bộ side-effect vẫn đi qua hàm lõi bên dưới.
        return $this->updateReservationStatus($reservationId, $newStatus, null, null, []);
    }

    /**
     * --- CHẠY TỰ ĐỘNG: QUÉT ĐÁNH DẤU KHÁCH KHÔNG ĐẾN (NO-SHOW) ---
     */
    public function markNoShows(int $graceMinutes = 15): int
    {
        // Pha 1: xac dinh moc qua han no-show theo grace window ma scheduler/trusted caller truyen vao.
        $graceMinutes = max(0, $graceMinutes);
        $threshold = Carbon::now('UTC')->subMinutes($graceMinutes);

        // Chỉ quét các reservation Confirmed đã quá giờ nhưng chưa check-in.
        $reservationIds = Reservation::query()
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereNull('checked_in_at')
            ->where('start_time', '<=', $threshold)
            ->orderBy('reservation_id')
            ->pluck('reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Batch no-show xu ly tung reservation rieng de mot ca loi khong lam hong ca dot quet.
        $count = 0;
        foreach ($reservationIds as $reservationId) {
            try {
                // Mỗi reservation được xử lý độc lập để một ca lỗi không chặn cả đợt quét.
                $this->updateReservationStatus(
                    reservationId: $reservationId,
                    newStatus: ReservationStatus::NoShow->value,
                    expectedRowVersion: null,
                    actorUserId: null,
                    options: ['source' => 'scheduler.no_show']
                );
                $count++;
            } catch (\Throwable $e) {
                AuditEvent::warning('reservation_mark_no_show_failed', [
                    'reservation_id' => (int) $reservationId,
                    'grace_minutes' => $graceMinutes,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
        }

        return $count;
    }

    /**
     * --- BẢO VỆ STATE MACHINE ---
     * Kiểm tra logic việc chuyển đổi trạng thái
     */
    private function assertStatusTransitionAllowed(string $current, string $target, bool $force = false): void
    {
        if ($current === $target) {
            return;
        }

        $allowed = [
            ReservationStatus::Confirmed->value => [
                ReservationStatus::Cancelled->value,
                ReservationStatus::Expired->value,
                ReservationStatus::NoShow->value,
            ],
            ReservationStatus::checkedInDbValue() => [],
            ReservationStatus::Cancelled->value => [],
            ReservationStatus::Expired->value => [],
            ReservationStatus::Completed->value => [],
            ReservationStatus::NoShow->value => [],
        ];

        if ($current === ReservationStatus::checkedInDbValue() && $target === ReservationStatus::Cancelled->value && $force) {
            return;
        }

        if (! array_key_exists($current, $allowed)) {
            throw ValidationException::withMessages([
                'status' => ["Không cho phép chuyển trạng thái từ '{$current}'."],
            ]);
        }

        if (! in_array($target, $allowed[$current], true)) {
            throw ValidationException::withMessages([
                'status' => ["Transition không hợp lệ: {$current} -> {$target}."],
            ]);
        }
    }

    /**
     * --- HÀM LÕI: THAY ĐỔI TRẠNG THÁI RESERVATION ---
     */
    public function updateReservationStatus(
        int $reservationId,
        string $newStatus,
        ?int $expectedRowVersion,
        ?int $actorUserId = null,
        array $options = []
    ): Reservation {
        // Pha 1: normalize target status va actor options cho generic status endpoint.
        $newStatus = trim($newStatus);
        if ($newStatus === '') {
            throw ValidationException::withMessages(['status' => ['status là bắt buộc.']]);
        }

        try {
            $targetEnum = ReservationStatus::from($newStatus);
        } catch (\ValueError) {
            throw ValidationException::withMessages(['status' => ['status không hợp lệ.']]);
        }

        $force = (bool) ($options['force'] ?? false);
        $cancelReason = isset($options['cancel_reason']) ? trim((string) $options['cancel_reason']) : null;
        $actorType = mb_strtolower(trim((string) ($options['actor_type'] ?? 'staff')));
        $enforceStaffBranchScope = array_key_exists('enforce_staff_branch_scope', $options)
            ? (bool) $options['enforce_staff_branch_scope']
            : $actorType === 'staff';

        if ($targetEnum === ReservationStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => ['Completed is not allowed via generic status endpoint. Use checkout / settlement flow instead.'],
            ]);
        }

        // Mọi đổi trạng thái đều dồn qua một lock point để giữ state machine và side-effect nhất quán.
        return $this->lockService->withReservationLock($reservationId, function () use ($reservationId, $targetEnum, $expectedRowVersion, $actorUserId, $force, $cancelReason, $enforceStaffBranchScope) {
            // Pha 2: lock reservation truoc, roi lock them tables/orders/payments lien quan trong transaction ben trong.
            $tableIds = ReservationTable::query()
                ->where('reservation_id', $reservationId)
                ->orderBy('table_id')
                ->pluck('table_id')
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();

            $work = function () use ($reservationId, $targetEnum, $expectedRowVersion, $actorUserId, $force, $cancelReason, $tableIds, $enforceStaffBranchScope) {
                DB::transaction(function () use ($reservationId, $targetEnum, $expectedRowVersion, $actorUserId, $force, $cancelReason, $tableIds, $enforceStaffBranchScope) {

                    /** @var Reservation $reservation */
                    $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);

                    // Phân quyền chi nhánh
                    if ($enforceStaffBranchScope) {
                        $this->assertStaffCanMutateReservationBranch($reservation, $actorUserId);
                    }

                    $currentEnum = $reservation->status instanceof ReservationStatus
                        ? $reservation->status
                        : ReservationStatus::from((string) $reservation->getRawOriginal('status'));
                    $current = $currentEnum->value;
                    $target = $targetEnum->value;

                    // Nếu request lặp lại trạng thái hiện tại thì chỉ ghi audit, không làm lại side-effect.
                    if ($current === $target) {
                        AuditEvent::info('reservation_status_noop', [
                            'reservation_id' => (int) $reservation->reservation_id,
                            'status' => $current,
                            'expected_row_version' => $expectedRowVersion,
                            'current_row_version' => (int) ($reservation->row_version ?? 1),
                            'force' => $force,
                            'actor_user_id' => $actorUserId,
                        ]);

                        return;
                    }

                    ReservationStatusTransitionPolicy::assertTransitionAllowed($current, $target, $force);

                    // Sau điểm này là vùng nhạy cảm: lock orders/payments/tables trước khi chạm side-effect.
                    $beforeVersion = (int) ($reservation->row_version ?? 1);

                    $orders = ReservationOrder::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->get();

                    $payments = Payment::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->get();

                    // Optimistic Locking Check
                    if ($expectedRowVersion !== null && $beforeVersion !== (int) $expectedRowVersion) {
                        throw ValidationException::withMessages([
                            'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
                        ]);
                    }

                    if (! empty($tableIds)) {
                        RestaurantTable::query()->whereIn('table_id', $tableIds)->lockForUpdate()->get();
                    }

                    $now = Carbon::now('UTC');

                    // Hủy bàn khách đang ngồi (Checked-In -> Cancelled)
                    if ($current === ReservationStatus::checkedInDbValue() && $target === ReservationStatus::Cancelled->value) {
                        if (! $force) {
                            throw ValidationException::withMessages([
                                'status' => ['Checked-in reservations (stored as Reserved) can only be cancelled via force=true after manual confirmation.'],
                            ]);
                        }

                        $paymentSummary = PaymentSummary::fromPayments($payments);
                        if (Money::isPositive($paymentSummary['final_net_amount'] ?? 0)) {
                            throw ValidationException::withMessages([
                                'status' => ['Reservation still has unrefunded final payments. Use refund/cancel-after-payment flow before cancelling.'],
                            ]);
                        }

                        $this->cancelActiveOrders($orders, $actorUserId, $now);
                        $this->releaseTables($tableIds);
                        $reservation->cancelled_at = $now;
                        $reservation->cancelled_by = $actorUserId;
                        $reservation->cancel_reason = ($cancelReason !== null && $cancelReason !== '')
                            ? $cancelReason
                            : ($reservation->cancel_reason ?? 'Forced staff cancellation');

                    }

                    // Trả mã Voucher và Điểm thưởng khi booking bị Hủy
                    if ($target === ReservationStatus::Cancelled->value && in_array($current, ReservationStatus::activeDbValues(), true)) {
                        $this->releaseReservationVoucherForStatusLocked($reservation, $actorUserId);
                        $this->loyaltyPointsService->releaseReservationRedemptionForStatusLocked(
                            reservation: $reservation,
                            staffUserId: $actorUserId,
                            reason: 'status_cancelled'
                        );
                    }

                    // Hủy booking do quá hạn hoặc bom bàn (NoShow)
                    if ($target === ReservationStatus::Expired->value || $target === ReservationStatus::NoShow->value) {
                        $this->releaseReservationVoucherForStatusLocked($reservation, $actorUserId);
                        $this->loyaltyPointsService->releaseReservationRedemptionForStatusLocked(
                            reservation: $reservation,
                            staffUserId: $actorUserId,
                            reason: $target === ReservationStatus::Expired->value ? 'status_expired' : 'status_no_show'
                        );

                        $activeOrderIds = $orders
                            ->filter(fn ($order) => (string) ($order->status?->value ?? $order->status) === ReservationOrderStatus::Active->value)
                            ->pluck('order_id')
                            ->map(fn ($id) => (int) $id)
                            ->values()
                            ->all();

                        if ($activeOrderIds !== []) {
                            $this->cancelActiveOrders($orders, $actorUserId, $now);

                            AuditEvent::info('reservation_terminal_status_cancelled_active_orders', [
                                'reservation_id' => (int) $reservation->reservation_id,
                                'target_status' => $target,
                                'cancelled_order_ids' => $activeOrderIds,
                                'actor_user_id' => $actorUserId,
                            ]);
                        }
                    }

                    // Hủy bàn bình thường
                    if ($target === ReservationStatus::Cancelled->value && $current === ReservationStatus::Confirmed->value) {
                        $paymentSummary = PaymentSummary::fromPayments($payments);
                        if (Money::isPositive($paymentSummary['final_net_amount'] ?? 0)) {
                            throw ValidationException::withMessages([
                                'status' => ['Reservation still has unrefunded final payments. Use refund/cancel-after-payment flow before cancelling.'],
                            ]);
                        }

                        $this->cancelActiveOrders($orders, $actorUserId, $now);
                        $reservation->cancelled_at = $now;
                        $reservation->cancelled_by = $actorUserId;
                        $reservation->cancel_reason = $cancelReason !== '' ? $cancelReason : $reservation->cancel_reason;
                    }
                    if ($target === ReservationStatus::NoShow->value) {
                        $reservation->no_show_at = $reservation->no_show_at ?? $now;
                    }

                    $reservation->status = $targetEnum;
                    $reservation->updated_by = $actorUserId;
                    $reservation->save();

                    // Outbox Events
                    if ($current !== $target && $target === ReservationStatus::Cancelled->value) {
                        $this->notificationOutboxService->enqueueReservationCancelled($reservation);
                    }

                    if ($current !== $target && $target === ReservationStatus::Expired->value) {
                        $this->notificationOutboxService->enqueueReservationExpired($reservation);
                    }

                    if ($current !== $target && $target === ReservationStatus::NoShow->value) {
                        $this->notificationOutboxService->enqueueReservationNoShow($reservation);
                    }

                    AuditEvent::info('reservation_status_changed', [
                        'reservation_id' => (int) $reservation->reservation_id,
                        'from' => $current,
                        'to' => $target,
                        'force' => $force,
                        'cancel_reason' => $cancelReason,
                        'expected_row_version' => $expectedRowVersion,
                        'before_row_version' => $beforeVersion,
                        'new_row_version' => $beforeVersion + 1,
                        'actor_user_id' => $actorUserId,
                    ]);

                });

                AvailabilityCacheVersion::bump();

                return Reservation::query()
                    ->with(['user', 'tables', 'orders.items.item', 'payments'])
                    ->findOrFail($reservationId);
            };

            if (! empty($tableIds)) {
                return $this->lockService->withTableLocks($tableIds, $work);
            }

            return $work();
        });
    }

    private function assertStaffCanMutateReservationBranch(Reservation $reservation, ?int $actorUserId): void
    {
        if ($actorUserId === null || $actorUserId <= 0) {
            return;
        }

        $branchId = $this->branchContextService->resolveBranchId($reservation->branch_id ?? null, true);
        $this->staffBranchContextService->assertAccessibleBranch($actorUserId, $branchId);
    }

    /**
     * --- HOÀN VOUCHER ---
     * Khách áp mã giảm giá mà booking bị hủy -> Trả mã lại cho khách.
     */
    private function releaseReservationVoucherForStatusLocked(Reservation $reservation, ?int $actorUserId = null): void
    {
        $userVoucherId = (int) ($reservation->applied_user_voucher_id ?? 0);
        if ($userVoucherId <= 0) {
            return;
        }

        /** @var UserVoucher|null $userVoucher */
        $userVoucher = UserVoucher::query()
            ->with('voucher')
            ->where('user_voucher_id', $userVoucherId)
            ->lockForUpdate()
            ->first();

        if (! $userVoucher) {
            $reservation->applied_user_voucher_id = null;

            return;
        }

        $orders = ReservationOrder::query()
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->whereIn('status', [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value])
            ->with('items')
            ->lockForUpdate()
            ->get();

        $voucherDiscount = $userVoucher->voucher
            ? Money::toFloat(VoucherRedemptionSupport::calculateDiscount($userVoucher->voucher, $orders)['discount_amount'] ?? 0, true)
            : 0.0;

        if (! (bool) ($userVoucher->is_used ?? false)) {
            $userVoucher->lock_token = null;
            $userVoucher->locked_until = null;
            $userVoucher->updated_by = $actorUserId;
            $userVoucher->save();
        }

        if (! (bool) ($userVoucher->is_used ?? false)) {
            $reservation->applied_user_voucher_id = null;
            $this->reservationFinancialSyncService->syncReservationDiscountSnapshot(
                reservation: $reservation,
                totalDiscount: Money::minorToFloat(max(
                    0,
                    Money::minorUnits($reservation->discount_amount ?? 0, true) - Money::minorUnits($voucherDiscount, true)
                )),
                lockOrders: true,
            );
        }
    }

    /**
     * --- HỦY ORDER ACTIVE ---
     * Hủy danh sách món ăn đang nấu/đang chờ thuộc về một Order
     */
    private function cancelActiveOrders($orders, ?int $actorUserId, Carbon $now): void
    {
        foreach ($orders as $order) {
            if ((string) ($order->status?->value ?? $order->status) !== ReservationOrderStatus::Active->value) {
                continue;
            }

            $items = ReservationOrderItem::query()
                ->where('order_id', $order->order_id)
                ->whereNotIn('status', [ReservationOrderItemStatus::Cancelled->value, ReservationOrderItemStatus::Served->value])
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $item->status = ReservationOrderItemStatus::Cancelled;
                $item->updated_by = $actorUserId;
                $item->updated_at = $now;
                $item->save();
            }

            $order->status = ReservationOrderStatus::Cancelled;
            $order->updated_by = $actorUserId;
            $order->updated_at = $now;
            $order->save();
        }
    }

    /**
     * --- GIẢI PHÓNG BÀN ---
     * Đổi lại trạng thái bàn thành "Trống" trên sơ đồ
     */
    private function releaseTables(array $tableIds): void
    {
        $this->tableStateService->releaseTablesSafely($tableIds, null, null, ['source' => 'reservation_service', 'reason' => 'reservation_release']);
    }

    /**
     * Lấy mảng ID Bàn. Nếu Request truyền Hold ID thì tra từ DB, nếu không thì dùng Table ID truyền lên.
     */
    private function resolveTableIdsFromPayloadOrHold(array $payload, ?string $holdId, ?string $sessionId, Carbon $start, Carbon $end): array
    {
        if (! is_string($holdId) || $holdId === '') {
            return array_values(array_map('intval', $payload['table_ids']));
        }

        if (! is_string($sessionId) || $sessionId === '') {
            throw ValidationException::withMessages([
                'session_id' => ['session_id là bắt buộc khi dùng hold_id.'],
            ]);
        }

        $this->tableHoldService->expireStaleHolds();

        $hold = DB::table('table_holds')
            ->where('hold_id', $holdId)
            ->where('session_id', $sessionId)
            ->first();

        if (! $hold) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không tồn tại hoặc không thuộc session_id.'],
            ]);
        }

        if (! in_array((string) $hold->hold_status, ['Holding', 'Pending'], true)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không ở trạng thái Holding/Pending.'],
            ]);
        }

        if (Carbon::parse($hold->expire_at)->utc()->lte(Carbon::now('UTC'))) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã hết hạn.'],
            ]);
        }

        $holdStart = Carbon::parse($hold->start_time)->utc();
        $holdEnd = isset($hold->end_time)
            ? Carbon::parse($hold->end_time)->utc()
            : $holdStart->copy()->addMinutes((int) ($hold->duration_minutes ?? 0));

        if ($holdStart->gt($start) || $holdEnd->lt($end)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không bao phủ đủ khoảng thời gian reservation.'],
            ]);
        }

        $tableIds = DB::table('table_hold_details')
            ->where('hold_id', $holdId)
            ->pluck('table_id')
            ->map(fn ($x) => (int) $x)
            ->values()
            ->all();

        if (empty($tableIds)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không có table_ids.'],
            ]);
        }

        if (isset($payload['table_ids']) && is_array($payload['table_ids'])) {
            $client = array_values(array_map('intval', $payload['table_ids']));
            sort($client);
            $fromHold = $tableIds;
            sort($fromHold);

            if ($client !== $fromHold) {
                throw ValidationException::withMessages([
                    'table_ids' => ['table_ids không khớp với hold_id.'],
                ]);
            }
        }

        return $tableIds;
    }

    /**
     * Lock cái Hold record lại trước để đảm bảo không ai cướp được
     */
    private function lockAndAssertActiveHoldForReservation(string $holdId, string $sessionId, Carbon $start, Carbon $end): int
    {
        $hold = DB::table('table_holds')
            ->where('hold_id', $holdId)
            ->where('session_id', $sessionId)
            ->lockForUpdate()
            ->first();

        if (! $hold) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không tồn tại hoặc không thuộc session_id.'],
            ]);
        }

        if (! in_array((string) $hold->hold_status, [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value], true)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã thay đổi trạng thái trong lúc tạo reservation.'],
            ]);
        }

        if (Carbon::parse((string) $hold->expire_at)->utc()->lte(Carbon::now('UTC'))) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã hết hạn trong lúc tạo reservation.'],
            ]);
        }

        $holdStart = Carbon::parse((string) $hold->start_time)->utc();
        $holdEnd = isset($hold->end_time)
            ? Carbon::parse((string) $hold->end_time)->utc()
            : $holdStart->copy()->addMinutes((int) ($hold->duration_minutes ?? 0));

        if ($holdStart->gt($start) || $holdEnd->lt($end)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không còn bao phủ đủ khoảng thời gian reservation.'],
            ]);
        }

        return $this->branchContextService->resolveBranchId($hold->branch_id ?? null, false);
    }

    /**
     * Tính toán xem N cái bàn gộp lại có chứa đủ số người không
     */
    private function assertCapacityEnough($tables, int $guestCount): void
    {
        $nullTemplate = $tables->whereNull('template_id')->pluck('table_id')->values()->all();
        if (! empty($nullTemplate)) {
            throw ValidationException::withMessages([
                'table_ids' => ['Các bàn thiếu template_id (không tính được seats): '.implode(',', $nullTemplate)],
            ]);
        }

        $templateIds = $tables->pluck('template_id')->unique()->values()->all();
        $seatsByTemplate = DB::table('table_templates')
            ->whereIn('template_id', $templateIds)
            ->pluck('seats', 'template_id');

        $missingTemplates = [];
        $totalSeats = 0;
        foreach ($tables as $t) {
            $tid = (int) $t->template_id;
            if (! $seatsByTemplate->has($tid)) {
                $missingTemplates[] = $tid;

                continue;
            }
            $totalSeats += (int) $seatsByTemplate->get($tid);
        }

        if (! empty($missingTemplates)) {
            $missingTemplates = array_values(array_unique($missingTemplates));
            throw ValidationException::withMessages([
                'table_ids' => ['Template không tồn tại để tính seats: '.implode(',', $missingTemplates)],
            ]);
        }

        if ($guestCount > $totalSeats) {
            throw ValidationException::withMessages([
                'guest_count' => ["Số khách ($guestCount) vượt quá sức chứa ($totalSeats seats) của các bàn đã chọn."],
            ]);
        }
    }

    /**
     * --- UTILS: LÀM SẠCH VÀ ĐỊNH DANH DỮ LIỆU ---
     */
    private function resolveReservationUserId(array $payload, ?int $actorUserId): ?int
    {
        $userId = isset($payload['user_id']) && $payload['user_id'] !== null
            ? (int) $payload['user_id']
            : null;

        if ($userId === null || $userId <= 0) {
            return null;
        }

        $user = User::query()
            ->where('user_id', $userId)
            ->where('is_deleted', 0)
            ->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => ['User khong ton tai hoac da bi xoa.'],
            ]);
        }

        return $userId;
    }

    /**
     * @return array{guest_name:?string,guest_phone:?string,guest_email:?string}
     */
    private function resolveGuestSnapshot(array $payload, ?int $userId): array
    {
        $guestName = $this->normalizeGuestField($payload['guest_name'] ?? null);
        $guestPhone = $this->normalizeGuestField($payload['guest_phone'] ?? null);
        $guestEmail = $this->normalizeGuestField($payload['guest_email'] ?? null);

        if ($userId !== null) {
            return [
                'guest_name' => null,
                'guest_phone' => null,
                'guest_email' => null,
            ];
        }

        if ($guestName === null || $guestPhone === null) {
            throw ValidationException::withMessages([
                'guest_name' => ['guest_name is required when user_id is omitted.'],
                'guest_phone' => ['guest_phone is required when user_id is omitted.'],
            ]);
        }

        return [
            'guest_name' => $guestName,
            'guest_phone' => $guestPhone,
            'guest_email' => $guestEmail,
        ];
    }

    private function normalizeGuestField(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
