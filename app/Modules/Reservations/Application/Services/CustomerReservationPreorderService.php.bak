<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\Catalog\Application\UseCases\PolicyPreview\MenuPreorderPolicyService;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use App\Support\ValidationExceptionFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service này đóng vai trò "Người phục vụ ảo" cho phép khách hàng tự xem,
 * tính tiền tạm tính (preview), cập nhật (replace) hoặc hủy (clear) danh sách các món đặt trước.
 */
class CustomerReservationPreorderService
{
    public function __construct(
        private readonly ReservationSessionAccessWorkflow $customerSessionAccessService,
        private readonly MenuPreorderPolicyService $menuPreorderPolicyService,
        private readonly ReservationLockService $locks,
    ) {}

    /**
     * --- HÀM 1: XEM DANH SÁCH MÓN ĐẶT TRƯỚC ---
     *
     * @return array{reservation:Reservation,pre_order:array<string,mixed>,management_policy:array<string,mixed>}
     */
    public function showAccessiblePreorder(int $reservationId, ?int $customerUserId, ?string $sessionId): array
    {
        // Bước 1: Tải đơn đặt bàn và kiểm duyệt quyền truy cập (Chống IDOR)
        $reservation = $this->loadAccessibleReservation(
            reservationId: $reservationId,
            customerUserId: $customerUserId,
            sessionId: $sessionId,
            lockForUpdate: false, // Lệnh đọc nên không cần khóa DB
        );

        // Bước 2: Đóng gói dữ liệu trả về kèm theo Chính sách quản lý (Có được phép sửa tiếp không)
        return $this->buildResponse($reservation, $this->findCurrentPreorderOrder($reservationId));
    }

    /**
     * --- HÀM 2: XEM TRƯỚC HÓA ĐƠN TẠM TÍNH (PREVIEW) ---
     * Khách hàng tick chọn món mới trên giao diện, hàm này trả về hóa đơn tạm tính (tổng tiền, thuế...)
     * MÀ CHƯA LƯU VÀO DB. Giúp khách xác nhận trước khi thực sự ấn "Lưu thay đổi".
     *
     * @param  array<int, array<string,mixed>>  $requestedItems
     * @return array{reservation:Reservation,current_pre_order:array<string,mixed>,management_policy:array<string,mixed>,preview:array<string,mixed>}
     */
    public function previewAccessiblePreorderUpdate(int $reservationId, ?int $customerUserId, ?string $sessionId, array $requestedItems): array
    {
        $reservation = $this->loadAccessibleReservation(
            reservationId: $reservationId,
            customerUserId: $customerUserId,
            sessionId: $sessionId,
            lockForUpdate: false,
        );

        // Chặn ngay lập tức nếu đơn đã Check-in hoặc quá sát giờ (Cut-off time)
        $this->assertReservationPreorderMutable($reservation);

        // Xây dựng bản Preview (Kiểm tra quota, lấy giá mới nhất của món ăn)
        $preview = $this->buildRequestedPreorderPreview(
            requestedItems: $requestedItems,
            serviceStart: Carbon::parse((string) $reservation->start_time)->utc(),
            ignoreReservationId: (int) $reservation->reservation_id,
        );

        $currentOrder = $this->findCurrentPreorderOrder((int) $reservation->reservation_id);

        return [
            'reservation' => $reservation,
            'current_pre_order' => $this->buildCurrentPreorderSnapshot($reservation, $currentOrder),
            'management_policy' => $this->buildManagementPolicy($reservation),
            'preview' => $preview,
        ];
    }

    /**
     * --- HÀM 3: GHI NHẬN THAY ĐỔI MÓN (REPLACE) ---
     * Xóa toàn bộ món đặt trước cũ (nếu có) và ghi đè bằng danh sách món mới.
     *
     * @param  array<string,mixed>  $payload
     * @return array{reservation:Reservation,pre_order:array<string,mixed>,management_policy:array<string,mixed>}
     */
    public function replaceAccessiblePreorder(int $reservationId, ?int $customerUserId, ?string $sessionId, array $payload): array
    {
        // Khóa phân tán (Distributed Lock): Đảm bảo 2 người (VD: vợ và chồng dùng chung link)
        // không bấm "Lưu" cùng một phần nghìn giây.
        return $this->locks->withReservationLock($reservationId, function () use ($reservationId, $customerUserId, $sessionId, $payload) {
            return DB::transaction(function () use ($reservationId, $customerUserId, $sessionId, $payload) {

                // Khóa CSDL (Pessimistic Lock): Tránh việc Nhân viên (Staff) đang thao tác hóa đơn
                // thì Khách hàng (Customer) lại đổi món.
                $reservation = $this->loadAccessibleReservation(
                    reservationId: $reservationId,
                    customerUserId: $customerUserId,
                    sessionId: $sessionId,
                    lockForUpdate: true,
                );

                // Khóa Lạc quan (Optimistic Lock): Phát hiện dữ liệu cũ (Stale Data).
                $this->assertReservationRowVersion($reservation, (int) $payload['row_version']);
                $this->assertReservationPreorderMutable($reservation);

                // Thẩm định lại 1 lần nữa danh sách món (đề phòng khách sửa request bằng Postman)
                $prepared = $this->menuPreorderPolicyService->prepareRequestedItems(
                    (array) $payload['pre_order_items'],
                    Carbon::parse((string) $reservation->start_time)->utc(),
                    (int) $reservation->reservation_id,
                );

                $order = $this->findActivePreorderOrderForUpdate((int) $reservation->reservation_id);
                if ($order instanceof ReservationOrder) {
                    // Nếu đã có Order cũ: Kiểm tra version, hủy các món cũ (Soft Cancel)
                    $this->assertPreorderRowVersion($order, $payload['pre_order_row_version'] ?? null);
                    $this->cancelExistingPreorderItems($order, $customerUserId);
                    $this->incrementOrderRowVersion($order);
                    $order->status = ReservationOrderStatus::Active;
                    $order->updated_by = $customerUserId;
                    $order->save();
                } else {
                    // Nếu chưa có Order nào: Tạo hóa đơn Pre-order mới tinh
                    $order = new ReservationOrder;
                    $order->reservation_id = (int) $reservation->reservation_id;
                    $order->order_type = ReservationOrderType::PreOrder;
                    $order->status = ReservationOrderStatus::Active;
                    $order->notes = 'Customer managed pre-order';
                    $order->created_by = $customerUserId;
                    $order->updated_by = $customerUserId;
                    $order->save();
                }

                // Ghi danh sách món mới vào DB
                $this->persistPreparedRows($order, $prepared, $customerUserId);

                // Ghi vết Hệ thống (Audit Trail)
                AuditEvent::info('customer.reservation.preorder.replaced', [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'preorder_order_id' => (int) $order->order_id,
                    'customer_user_id' => $customerUserId,
                    'customer_session_id' => $customerUserId === null ? trim((string) $sessionId) : null,
                    'line_count' => count((array) $prepared['rows']),
                ]);

                // Query lại dữ liệu mới nhất để trả về cho Client render giao diện
                $freshReservation = $this->loadAccessibleReservation(
                    reservationId: $reservationId,
                    customerUserId: $customerUserId,
                    sessionId: $sessionId,
                    lockForUpdate: false,
                );

                return $this->buildResponse($freshReservation, $this->findCurrentPreorderOrder($reservationId));
            });
        });
    }

    /**
     * --- HÀM 4: HỦY TOÀN BỘ MÓN ĐẶT TRƯỚC (CLEAR) ---
     *
     * @param  array<string,mixed>  $payload
     * @return array{reservation:Reservation,pre_order:array<string,mixed>,management_policy:array<string,mixed>}
     */
    public function clearAccessiblePreorder(int $reservationId, ?int $customerUserId, ?string $sessionId, array $payload): array
    {
        return $this->locks->withReservationLock($reservationId, function () use ($reservationId, $customerUserId, $sessionId, $payload) {
            return DB::transaction(function () use ($reservationId, $customerUserId, $sessionId, $payload) {
                $reservation = $this->loadAccessibleReservation(
                    reservationId: $reservationId,
                    customerUserId: $customerUserId,
                    sessionId: $sessionId,
                    lockForUpdate: true,
                );

                $this->assertReservationRowVersion($reservation, (int) $payload['row_version']);
                $this->assertReservationPreorderMutable($reservation);

                $order = $this->findActivePreorderOrderForUpdate((int) $reservation->reservation_id);
                if ($order instanceof ReservationOrder) {
                    $this->assertPreorderRowVersion($order, $payload['pre_order_row_version'] ?? null);
                    // Hủy món bên trong
                    $this->cancelExistingPreorderItems($order, $customerUserId);
                    $this->incrementOrderRowVersion($order);
                    // Đánh dấu cả Hóa đơn là Cancelled
                    $order->status = ReservationOrderStatus::Cancelled;
                    $order->updated_by = $customerUserId;
                    $order->save();

                    AuditEvent::info('customer.reservation.preorder.cleared', [
                        'reservation_id' => (int) $reservation->reservation_id,
                        'preorder_order_id' => (int) $order->order_id,
                        'customer_user_id' => $customerUserId,
                        'customer_session_id' => $customerUserId === null ? trim((string) $sessionId) : null,
                    ]);
                }

                $freshReservation = $this->loadAccessibleReservation(
                    reservationId: $reservationId,
                    customerUserId: $customerUserId,
                    sessionId: $sessionId,
                    lockForUpdate: false,
                );

                return $this->buildResponse($freshReservation, $this->findCurrentPreorderOrder($reservationId));
            });
        });
    }

    /**
     * Build response trả về chung cho mọi API
     *
     * @return array{reservation:Reservation,pre_order:array<string,mixed>,management_policy:array<string,mixed>}
     */
    private function buildResponse(Reservation $reservation, ?ReservationOrder $currentOrder): array
    {
        return [
            'reservation' => $reservation,
            'pre_order' => $this->buildCurrentPreorderSnapshot($reservation, $currentOrder),
            'management_policy' => $this->buildManagementPolicy($reservation),
        ];
    }

    /**
     * Tải Reservation và kiểm tra an ninh mạng (Chống IDOR - Truy cập trái phép)
     */
    private function loadAccessibleReservation(int $reservationId, ?int $customerUserId, ?string $sessionId, bool $lockForUpdate): Reservation
    {
        // Dành cho khách có tài khoản
        if ($customerUserId !== null) {
            $query = Reservation::query()
                ->with(['orders.items.item'])
                ->whereKey($reservationId)
                ->where('user_id', $customerUserId);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $reservation = $query->first();
            if ($reservation instanceof Reservation) {
                return $reservation;
            }

            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        // Dành cho khách vãng lai dùng link ẩn danh (Session ID)
        $trimmedSessionId = trim((string) $sessionId);
        if ($trimmedSessionId === '') {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        $query = Reservation::query()
            ->with(['orders.items.item'])
            ->whereKey($reservationId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $reservation = $query->first();
        // Workflow này chứa thuật toán đối chiếu Session token an toàn
        if (! $reservation instanceof Reservation || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $trimmedSessionId)) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
    }

    private function findCurrentPreorderOrder(int $reservationId): ?ReservationOrder
    {
        return ReservationOrder::query()
            ->with(['items.item'])
            ->where('reservation_id', $reservationId)
            ->where('order_type', ReservationOrderType::PreOrder->value)
            ->where('status', ReservationOrderStatus::Active->value)
            ->orderByDesc('order_id')
            ->first();
    }

    private function findActivePreorderOrderForUpdate(int $reservationId): ?ReservationOrder
    {
        return ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->where('order_type', ReservationOrderType::PreOrder->value)
            ->where('status', ReservationOrderStatus::Active->value)
            ->orderByDesc('order_id')
            ->lockForUpdate() // Chống ghi đè đồng thời
            ->first();
    }

    private function assertReservationPreorderMutable(Reservation $reservation): void
    {
        $policy = $this->buildManagementPolicy($reservation);
        if ((bool) ($policy['can_manage'] ?? false)) {
            return;
        }

        throw ValidationExceptionFactory::make([
            'reservation' => (array) ($policy['reasons'] ?? ['Reservation pre-order is not currently mutable.']),
        ]);
    }

    /**
     * --- TRÁI TIM NGHIỆP VỤ (DOMAIN RULES) ---
     * Nơi định nghĩa "Luật chơi" của nhà hàng: Khi nào khách không được sửa món nữa?
     *
     * @return array<string,mixed>
     */
    private function buildManagementPolicy(Reservation $reservation): array
    {
        $reservationStatus = (string) ($reservation->status?->value ?? $reservation->status ?? '');
        // Lấy cấu hình Cut-off time từ hệ thống (Mặc định 60 phút)
        $cutoffMinutes = max(0, (int) config('booking.customer_preorder_management_cutoff_minutes', 60));
        $serviceStart = Carbon::parse((string) $reservation->start_time)->utc();
        $manageUntil = $serviceStart->copy()->subMinutes($cutoffMinutes);
        $now = Carbon::now('UTC');

        $reasons = [];
        // Luật 1: Đơn đã hủy, đã NoShow thì không cho sửa món
        if ($reservationStatus !== ReservationStatus::Confirmed->value) {
            $reasons[] = 'Pre-order chỉ có thể được chỉnh sửa khi reservation còn ở trạng thái Confirmed.';
        }

        // Luật 2: Khách đã bước vào quán ngồi (Check-in) thì muốn gọi món phải dùng Menu tại bàn, không dùng web tự phục vụ nữa
        if ($reservation->checked_in_at !== null || ReservationStatus::isCheckedInDbValue($reservationStatus)) {
            $reasons[] = 'Reservation đã check-in nên không còn được chỉnh sửa pre-order từ self-service.';
        }

        // Luật 3: Bếp cần thời gian chuẩn bị. Phải chốt món trước X phút. Sát giờ quá bếp không làm kịp.
        if ($now->gte($manageUntil)) {
            $reasons[] = sprintf('Pre-order chỉ có thể chỉnh sửa trước giờ đến ít nhất %d phút.', $cutoffMinutes);
        }

        return [
            'can_manage' => $reasons === [],
            'reservation_status' => $reservationStatus,
            'cutoff_minutes' => $cutoffMinutes,
            'service_start' => $serviceStart->toIso8601String(),
            'manage_until' => $manageUntil->toIso8601String(),
            'reasons' => $reasons,
        ];
    }

    /**
     * Trích xuất thông tin Đơn hàng thành mảng Data Transfer Object (DTO) cho Frontend
     *
     * @return array<string,mixed>
     */
    private function buildCurrentPreorderSnapshot(Reservation $reservation, ?ReservationOrder $order): array
    {
        $serviceTime = Carbon::parse((string) $reservation->start_time)->utc();
        if (! $order instanceof ReservationOrder) {
            return [
                'present' => false,
                'order_id' => null,
                'order_row_version' => null,
                'order_status' => null,
                'service_time' => $serviceTime->toIso8601String(),
                'currency' => (string) ($reservation->bill_currency ?? 'VND'),
                'lines' => [],
                'totals' => [
                    'item_count' => 0,
                    'quantity' => 0,
                    'subtotal' => number_format(0, 2, '.', ''),
                ],
                'normalized_pre_order_items' => [],
            ];
        }

        // Lọc bỏ các món đã bị Hủy
        $activeItems = $order->relationLoaded('items')
            ? $order->items->filter(static fn (ReservationOrderItem $item): bool => (string) ($item->status?->value ?? $item->status) !== ReservationOrderItemStatus::Cancelled->value)->values()
            : collect();

        $currency = (string) ($reservation->bill_currency ?? 'VND');
        $subtotalMinor = 0;
        $quantityTotal = 0;
        $lines = [];

        foreach ($activeItems as $item) {
            $unitPriceMinor = Money::minorUnits($item->unit_price ?? 0, true);
            $lineTotalMinor = $item->line_total !== null
                ? Money::minorUnits($item->line_total, true)
                : $unitPriceMinor * (int) $item->quantity;
            $subtotalMinor += $lineTotalMinor;
            $quantityTotal += (int) $item->quantity;
            $currency = (string) ($item->currency ?: $currency);

            /** @var MenuItem|null $menuItem */
            $menuItem = $item->relationLoaded('item') ? $item->item : null;

            $lines[] = [
                'order_item_id' => (int) $item->order_item_id,
                'item_id' => (int) $item->item_id,
                'quantity' => (int) $item->quantity,
                'status' => $item->status?->value ?? (string) $item->status,
                // Lấy Tên món lúc bán (Snapshot) để đề phòng sau này nhà hàng đổi tên món trong Catalog
                'name' => (string) ($item->item_name_snapshot ?: ($menuItem?->name ?? '')),
                'code' => $menuItem?->code,
                'unit_price' => Money::formatMinor($unitPriceMinor),
                'line_total' => Money::formatMinor($lineTotalMinor),
                'currency' => (string) ($item->currency ?: $currency),
                'notes' => $item->notes,
                'updated_at' => optional($item->updated_at)->utc()->toIso8601String(),
            ];
        }

        return [
            'present' => $activeItems->isNotEmpty(),
            'order_id' => (int) $order->order_id,
            'order_row_version' => (int) ($order->row_version ?? 1),
            'order_status' => $order->status?->value ?? (string) $order->status,
            'service_time' => $serviceTime->toIso8601String(),
            'currency' => $currency,
            'lines' => $lines,
            'totals' => [
                'item_count' => count($lines),
                'quantity' => $quantityTotal,
                'subtotal' => Money::formatMinor($subtotalMinor),
            ],
            'normalized_pre_order_items' => array_map(static fn (array $line): array => [
                'item_id' => (int) $line['item_id'],
                'quantity' => (int) $line['quantity'],
            ], $lines),
        ];
    }

    /**
     * Dựng bản Xem trước (Preview) cho mảng JSON gửi lên từ người dùng
     *
     * @param  array<int, array<string,mixed>>  $requestedItems
     * @return array<string,mixed>
     */
    private function buildRequestedPreorderPreview(array $requestedItems, Carbon $serviceStart, ?int $ignoreReservationId = null): array
    {
        $prepared = $this->menuPreorderPolicyService->prepareRequestedItems(
            requestedItems: $requestedItems,
            serviceStart: $serviceStart,
            ignoreReservationId: $ignoreReservationId,
        );

        /** @var Collection<int, MenuItem> $menuItems */
        $menuItems = $prepared['menu_items'];
        /** @var Collection<int, MenuItemPrice> $priceRows */
        $priceRows = $prepared['price_rows'];
        $rows = $prepared['rows'];

        $currency = 'VND';
        $subtotalMinor = 0;
        $quantityTotal = 0;
        $lines = [];

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $quantity = (int) $row['quantity'];
            /** @var MenuItem $menuItem */
            $menuItem = $menuItems->get($itemId);
            /** @var MenuItemPrice $priceRow */
            $priceRow = $priceRows->get($itemId);

            $unitPriceMinor = Money::minorUnits($priceRow->price, true);
            $lineTotalMinor = $unitPriceMinor * $quantity;
            $subtotalMinor += $lineTotalMinor;
            $quantityTotal += $quantity;
            $currency = (string) ($priceRow->currency ?: $currency);

            $lines[] = [
                'item_id' => $itemId,
                'code' => (string) ($menuItem->code ?? ''),
                'name' => (string) $menuItem->name,
                'quantity' => $quantity,
                'unit_price' => Money::formatMinor($unitPriceMinor),
                'line_total' => Money::formatMinor($lineTotalMinor),
                'currency' => (string) ($priceRow->currency ?: $currency),
                'preorder_cutoff_minutes' => (int) ($menuItem->preorder_cutoff_minutes ?? 0),
                'preorder_quota_per_day' => $menuItem->preorder_quota_per_day !== null
                    ? (int) $menuItem->preorder_quota_per_day
                    : null,
            ];
        }

        return [
            'service_time' => $serviceStart->toIso8601String(),
            'currency' => $currency,
            'lines' => $lines,
            'totals' => [
                'item_count' => count($lines),
                'quantity' => $quantityTotal,
                'subtotal' => Money::formatMinor($subtotalMinor),
            ],
            'normalized_pre_order_items' => array_map(static fn (array $row): array => [
                'item_id' => (int) $row['item_id'],
                'quantity' => (int) $row['quantity'],
            ], $rows),
        ];
    }

    /**
     * @param  array{rows:array<int, array{item_id:int, quantity:int}>,menu_items:Collection<int, MenuItem>,price_rows:Collection<int, MenuItemPrice>}  $prepared
     */
    private function persistPreparedRows(ReservationOrder $order, array $prepared, ?int $customerUserId): void
    {
        /** @var Collection<int, MenuItem> $menuItems */
        $menuItems = $prepared['menu_items'];
        /** @var Collection<int, MenuItemPrice> $priceRows */
        $priceRows = $prepared['price_rows'];

        foreach ($prepared['rows'] as $row) {
            $itemId = (int) $row['item_id'];
            $quantity = (int) $row['quantity'];
            /** @var MenuItem $menuItem */
            $menuItem = $menuItems->get($itemId);
            /** @var MenuItemPrice $priceRow */
            $priceRow = $priceRows->get($itemId);

            // Xử lý tiền tệ chuẩn Enterprise qua thư viện Money
            $unitPriceMinor = Money::minorUnits($priceRow->price, true);
            $item = new ReservationOrderItem;
            $item->order_id = (int) $order->order_id;
            $item->item_id = $itemId;
            $item->quantity = $quantity;
            $item->unit_price = Money::formatMinor($unitPriceMinor);
            $item->line_total = Money::formatMinor($unitPriceMinor * $quantity);
            $item->currency = (string) ($priceRow->currency ?: 'VND');

            // Lưu Snapshot tên món ăn vào Hóa đơn (Chống việc món bị đổi tên trong DB sau này)
            $item->item_name_snapshot = (string) $menuItem->name;
            $item->status = ReservationOrderItemStatus::Ordered;
            $item->updated_by = $customerUserId;
            $item->save();
        }
    }

    /**
     * Hủy mềm (Soft Cancel) thay vì Hard Delete để giữ lại lịch sử khách đã từng đặt những gì
     */
    private function cancelExistingPreorderItems(ReservationOrder $order, ?int $customerUserId): void
    {
        $items = ReservationOrderItem::query()
            ->where('order_id', (int) $order->order_id)
            ->where('status', '!=', ReservationOrderItemStatus::Cancelled->value)
            ->lockForUpdate() // Khóa DB không cho ai đụng vào khi đang vòng lặp
            ->get();

        foreach ($items as $item) {
            $item->status = ReservationOrderItemStatus::Cancelled;
            $item->updated_by = $customerUserId;
            // Tăng row_version để đánh dấu dữ liệu đã có sự thay đổi
            $item->row_version = max(1, (int) ($item->row_version ?? 1)) + 1;
            $item->save();
        }
    }

    /**
     * --- KIỂM SOÁT ĐỒNG THỜI (OPTIMISTIC LOCKING) ---
     */
    private function assertReservationRowVersion(Reservation $reservation, int $expectedRowVersion): void
    {
        if ((int) ($reservation->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationExceptionFactory::make([
                'row_version' => ['Reservation row version does not match the latest state.'],
            ]);
        }
    }

    private function assertPreorderRowVersion(ReservationOrder $order, mixed $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            throw ValidationExceptionFactory::make([
                'pre_order_row_version' => ['Pre-order row version is required when an existing pre-order is being updated.'],
            ]);
        }

        if ((int) ($order->row_version ?? 1) !== (int) $expectedRowVersion) {
            throw ValidationExceptionFactory::make([
                'pre_order_row_version' => ['Pre-order row version does not match the latest state.'],
            ]);
        }
    }

    private function incrementOrderRowVersion(ReservationOrder $order): void
    {
        $order->row_version = max(1, (int) ($order->row_version ?? 1)) + 1;
    }
}
