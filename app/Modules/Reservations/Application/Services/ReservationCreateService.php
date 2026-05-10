<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\TableHoldService;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Support\AuditEvent;
use App\Support\AvailabilityCacheVersion;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationCreateService
{
    public function __construct(
        private readonly TableHoldService $tableHoldService,
        private readonly ReservationLockService $lockService,
        private readonly ReservationCodeGenerator $codeGenerator,
        private readonly NotificationOutboxService $notificationOutboxService,
        private readonly ReservationConflictValidator $conflictValidator,
        private readonly ReservationTableAssignmentService $tableAssignmentService,
    ) {}

    /**
     * --- HÀM CHÍNH: TẠO MỚI ĐƠN ĐẶT BÀN ---
     * Đây là Nhạc trưởng (Orchestrator) điều phối toàn bộ quy trình tạo đơn.
     */
    public function createReservation(array $payload, ?int $actorUserId = null, array $options = []): Reservation
    {
        // --- BƯỚC 1: TIỀN XỬ LÝ & CHUẨN HÓA THỜI GIAN ---
        // Best Practice: Luôn parse thời gian về UTC trước khi đưa vào logic để tránh lỗi do lệch múi giờ giữa Server và Client.
        $startUtc = Carbon::parse((string) $payload['start_time'])->utc();
        $endUtc = Carbon::parse((string) $payload['end_time'])->utc();

        $holdId = isset($payload['hold_id']) ? (string) $payload['hold_id'] : null;
        $sessionId = isset($payload['session_id']) ? (string) $payload['session_id'] : null;
        $skipLocking = (bool) ($options['skip_locking'] ?? false);

        // Thu thập danh sách các phiên Giữ bàn (Hold) được cấp phép để pass qua bước validate
        $trustedHoldIds = array_values(array_unique(array_filter(
            array_map('strval', (array) ($options['trusted_hold_ids'] ?? [])),
            static fn (string $value) => $value !== ''
        )));

        // Trích xuất ID bàn từ Request truyền lên hoặc từ dữ liệu Giữ bàn tạm thời (Hold)
        $tableIds = $this->tableAssignmentService->resolveTableIdsFromPayloadOrHold($payload, $holdId, $sessionId, $startUtc, $endUtc);
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        sort($tableIds);

        if (is_string($holdId) && $holdId !== '') {
            $trustedHoldIds[] = $holdId;
            $trustedHoldIds = array_values(array_unique($trustedHoldIds));
        }

        // --- BƯỚC 2: KHAI BÁO TRANSACTION RUNNER ---
        // Đóng gói toàn bộ logic ghi DB vào một Closure. Sẽ được thực thi bên trong hệ thống Lock.
        $runner = function () use ($payload, $actorUserId, $startUtc, $endUtc, $tableIds, $holdId, $sessionId, $trustedHoldIds) {

            // Đảm bảo All-or-Nothing (Thành công hết hoặc Rollback toàn bộ nếu có bất kỳ Exception nào)
            return DB::transaction(function () use ($payload, $actorUserId, $startUtc, $endUtc, $tableIds, $holdId, $sessionId, $trustedHoldIds) {

                // Quét rác: Hủy bỏ các phiên giữ bàn đã hết thời gian (VD: Khách giữ bàn nhưng quá 10 phút không chốt)
                $this->tableHoldService->expireStaleHolds();

                // Xác định danh tính: Khách đăng nhập (User) hay Khách vãng lai (Guest)
                $userId = $this->resolveReservationUserId($payload, $actorUserId);
                $guestSnapshot = $this->resolveGuestSnapshot($payload, $userId);

                $user = User::query()
                    ->where('user_id', $userId)
                    ->where('is_deleted', 0)
                    ->first();
                if ($userId !== null && ! $user) {
                    throw ValidationException::withMessages([
                        'user_id' => ['User does not exist or was deleted.'],
                    ]);
                }

                // Nếu có dùng Hold (Giữ chỗ), phải Lock cái phiên Hold này lại để không bị thao tác đúp
                if (is_string($holdId) && $holdId !== '' && is_string($sessionId) && $sessionId !== '') {
                    $this->tableAssignmentService->lockAndAssertActiveHoldForReservation($holdId, $sessionId, $startUtc, $endUtc);
                }

                // --- BƯỚC 3: GỌI CHUYÊN GIA THẨM ĐỊNH (VALIDATION) ---
                $guestCount = (int) $payload['guest_count'];

                // Khóa Pessimistic (forUpdate) các dòng dữ liệu bàn trong DB
                $tables = $this->conflictValidator->lockAndLoadTables($tableIds);

                // Kiểm tra trạng thái bàn (có bị hỏng không) và Sức chứa (đủ ghế không)
                $this->conflictValidator->assertTablesAllocatableAndCapacity($tables, $tableIds, $guestCount);

                // Kiểm tra trùng giờ với các bàn đã đặt trước đó
                $this->conflictValidator->assertNoCreateConflicts($tableIds, $startUtc, $endUtc, $trustedHoldIds);

                // --- BƯỚC 4: LƯU THỰC THỂ ĐẶT BÀN ---
                $reservation = new Reservation;
                $reservation->user_id = $userId;
                $reservation->guest_name = $guestSnapshot['guest_name'];
                $reservation->guest_phone = $guestSnapshot['guest_phone'];
                $reservation->guest_email = $guestSnapshot['guest_email'];

                // Cấp mã thân thiện cho Khách (VD: RES-V9X2)
                $reservation->reservation_code = $this->codeGenerator->generate($startUtc);
                $now = Carbon::now('UTC');
                $reservation->reserved_at = $now;
                $reservation->start_time = $startUtc;
                $reservation->end_time = $endUtc;
                $reservation->guest_count = $guestCount;
                $reservation->status = ReservationStatus::Confirmed;

                // Đánh dấu nguồn gốc: Do nhân viên thao tác giùm (Offline) hay khách tự đặt (Online)
                $reservation->source = $actorUserId !== null
                    && $actorUserId > 0
                    && ($userId === null || $actorUserId !== $userId)
                    ? 'Offline'
                    : 'Online';
                $reservation->notes = $payload['notes'] ?? null;
                $reservation->created_by = $actorUserId;
                $reservation->updated_by = $actorUserId;
                $reservation->save();

                // Ràng buộc (N-N) Đơn đặt bàn với các Bàn thực tế
                $reservation->tables()->attach($tableIds);

                // --- BƯỚC 5: XỬ LÝ GỌI MÓN TRƯỚC (PRE-ORDER) ---
                // Gọi sang hàm nội bộ để tạo Hóa đơn (Order) nếu khách có chọn món trước
                $this->createPreorderIfPresent($reservation, $payload['pre_order_items'] ?? null, $startUtc, $actorUserId);

                // Chuyển đổi trạng thái Giữ bàn (Holding) -> Thành công (Confirmed)
                if (is_string($holdId) && $holdId !== '' && is_string($sessionId) && $sessionId !== '') {
                    $this->tableAssignmentService->confirmHoldForReservation($holdId, $sessionId, (int) $reservation->reservation_id, $actorUserId, $now);
                }

                // --- BƯỚC 6: LƯU VẾT & GỬI THÔNG BÁO ---
                // Lưu Audit Trail để đối soát lịch sử hệ thống
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

                // Best Practice: Outbox Pattern. Không gửi SMS/Email ở đây để tránh nghẽn API.
                // Chỉ ném một Event vào bảng Outbox (hộp thư đi), sẽ có Job chạy ngầm phía sau mang đi gửi.
                $this->notificationOutboxService->enqueueReservationCreated($reservation);

                return (int) $reservation->reservation_id;
            });
        };

        // --- BƯỚC 7: THỰC THI (EXECUTION VỚI LOCKING) ---
        try {
            // Chạy Closure $runner bên trong hệ thống TableLocks.
            // Nếu có 2 Request cùng chạm vào $tableIds, hệ thống sẽ block 1 cái lại.
            $reservationId = (int) ($skipLocking
                ? $runner()
                : $this->lockService->withTableLocks($tableIds, $runner));
        } catch (QueryException $e) {
            // Biến các lỗi SQL bẩn (như Deadlock) thành ValidationException gọn gàng
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }

        // Tăng phiên bản Cache để các máy trạm/màn hình bếp biết có sự thay đổi dữ liệu
        AvailabilityCacheVersion::bump();

        // Eager load các relations cần thiết trước khi trả Entity về cho Controller
        return Reservation::query()
            ->with(['user', 'tables', 'orders.items.item', 'payments'])
            ->where('reservation_id', $reservationId)
            ->firstOrFail();
    }

    /**
     * --- MODULE GỌI MÓN TRƯỚC (PRE-ORDER) ---
     * Nơi xử lý logic tạo Hóa đơn nếu khách đặt sẵn món ăn từ ở nhà.
     */
    private function createPreorderIfPresent(Reservation $reservation, mixed $preOrderItems, Carbon $startUtc, ?int $actorUserId): void
    {
        if (! is_array($preOrderItems) || count($preOrderItems) === 0) {
            return;
        }

        // 1. Chuẩn hóa payload đầu vào
        $normalizedPreOrderItems = [];
        foreach ($preOrderItems as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }
            $normalizedPreOrderItems[] = [
                'item_id' => $itemId,
                'quantity' => $qty,
            ];
        }

        if (count($normalizedPreOrderItems) === 0) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Danh sách pre-order không hợp lệ.'],
            ]);
        }

        $itemIds = array_values(array_unique(array_map(
            fn ($x) => (int) $x['item_id'],
            $normalizedPreOrderItems
        )));

        if (! MenuItem::supportsPreorderColumns()) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Hệ thống chưa được đồng bộ contract pre-order. Vui lòng áp dụng patch database mới nhất rồi thử lại.'],
            ]);
        }

        // 2. Thẩm định Món ăn (Món có đang mở bán không? Có cho phép Pre-order không?)
        $menuItems = MenuItem::query()
            ->whereIn('item_id', $itemIds)
            ->where('is_available', 1)
            ->get(['item_id', 'name', 'is_preorder_enabled', 'preorder_quota_per_day', 'preorder_cutoff_minutes'])
            ->keyBy('item_id');

        if ($menuItems->count() !== count($itemIds)) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Có món không tồn tại hoặc đang unavailable.'],
            ]);
        }

        // 3. Tra cứu Giá bán (Dựa trên lịch sử giá)
        $reservationDate = $startUtc->copy()->toDateString();
        $priceRows = MenuItemPrice::query()
            ->whereIn('item_id', $itemIds)
            ->effectiveAt($startUtc) // Lấy đúng giá trị áp dụng cho ngày đặt bàn đó
            ->orderBy('item_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('price_id')
            ->get()
            ->groupBy('item_id')
            ->map(fn ($rows) => $rows->first());

        // 4. Thẩm định Luật Pre-order
        foreach ($normalizedPreOrderItems as $row) {
            $menuItem = $menuItems->get((int) $row['item_id']);
            if (! $menuItem) {
                throw ValidationException::withMessages([
                    'pre_order_items' => ['Có món không tồn tại hoặc đang unavailable.'],
                ]);
            }

            if (! (bool) ($menuItem->is_preorder_enabled ?? false)) {
                throw ValidationException::withMessages([
                    'pre_order_items' => [sprintf('Món %s không cho phép pre-order.', (string) $menuItem->name)],
                ]);
            }

            // Logic Cut-off: Món yêu cầu phải đặt trước X phút (VD: Cua hoàng đế phải báo trước 2 tiếng = 120 phút)
            $cutoffMinutes = (int) ($menuItem->preorder_cutoff_minutes ?? 0);
            if ($cutoffMinutes > 0 && Carbon::now('UTC')->addMinutes($cutoffMinutes)->gt($startUtc)) {
                throw ValidationException::withMessages([
                    'pre_order_items' => [sprintf('Món %s đã quá hạn pre-order.', (string) $menuItem->name)],
                ]);
            }

            // Logic Quota: Nhà hàng chỉ nhận làm tối đa Y món này một ngày
            $quotaPerDay = (int) ($menuItem->preorder_quota_per_day ?? 0);
            if ($quotaPerDay > 0) {
                // Đếm tổng số lượng món này đã được Pre-order trong cùng ngày hôm đó
                $existingQty = (int) DB::table('reservation_order_items as roi')
                    ->join('reservation_orders as ro', 'ro.order_id', '=', 'roi.order_id')
                    ->join('reservations as r', 'r.reservation_id', '=', 'ro.reservation_id')
                    ->where('ro.order_type', ReservationOrderType::PreOrder->value)
                    ->where('roi.item_id', (int) $row['item_id'])
                    ->whereDate('r.start_time', '=', $reservationDate)
                    ->whereIn('r.status', [
                        ReservationStatus::Confirmed->value,
                        ReservationStatus::checkedInDbValue(),
                        ReservationStatus::Completed->value,
                    ])
                    ->sum('roi.quantity');

                if ($existingQty + (int) $row['quantity'] > $quotaPerDay) {
                    throw ValidationException::withMessages([
                        'pre_order_items' => [sprintf('Món %s đã vượt quota pre-order trong ngày.', (string) $menuItem->name)],
                    ]);
                }
            }
        }

        // 5. Khởi tạo Order (Hóa đơn)
        $order = new ReservationOrder;
        $order->reservation_id = $reservation->reservation_id;
        $order->order_type = ReservationOrderType::PreOrder;
        $order->status = ReservationOrderStatus::Active;
        $order->created_by = $actorUserId;
        $order->updated_by = $actorUserId;
        $order->notes = null;
        $order->save();

        // 6. Ghi Chi tiết Món ăn (Order Items)
        foreach ($normalizedPreOrderItems as $row) {
            $menuItem = $menuItems->get((int) $row['item_id']);
            $priceRow = $priceRows->get((int) $row['item_id']);
            $unitPrice = $priceRow ? (float) $priceRow->price : 0.0;
            $currency = $priceRow ? (string) $priceRow->currency : 'VND';
            $quantity = (int) $row['quantity'];

            $item = new ReservationOrderItem;
            $item->order_id = $order->order_id;
            $item->item_id = (int) $row['item_id'];
            $item->quantity = $quantity;
            $item->unit_price = $unitPrice;
            $item->currency = $currency;
            $item->line_total = $unitPrice * $quantity;
            $item->item_name_snapshot = $menuItem ? (string) $menuItem->name : null; // Lưu cứng tên món ngay lúc bán
            $item->status = ReservationOrderItemStatus::Ordered;
            $item->notes = null;
            $item->updated_by = $actorUserId;
            $item->save();
        }
    }

    /**
     * --- TIỆN ÍCH: GIẢI QUYẾT DANH TÍNH ---
     * Kiểm tra user_id có tồn tại thật trong DB không
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
                'user_id' => ['User does not exist or was deleted.'],
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

        // Nếu đã gán user_id thì bỏ qua thông tin Guest (Tránh dư thừa dữ liệu Redundancy)
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

    /**
     * Gọt khoảng trắng dư thừa ở đầu cuối chuỗi
     */
    private function normalizeGuestField(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
