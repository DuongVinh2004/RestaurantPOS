<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\Catalog\Application\UseCases\PolicyPreview\MenuPreorderPolicyService;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service quản lý vòng đời của Hóa đơn Đặt món trước (Pre-order).
 * Thường được sử dụng bởi Lễ tân/Thu ngân (Staff) để trích xuất hóa đơn tạm tính
 * hoặc cập nhật/thay thế danh sách món ăn mà khách đã gọi trước khi đến quán.
 */
class ReservationPreorderService
{
    public function __construct(
        private readonly MenuPreorderPolicyService $menuPreorderPolicyService,
    ) {}

    /**
     * --- BƯỚC 1: TRÍCH XUẤT ẢNH CHỤP HÓA ĐƠN (SNAPSHOT) ---
     * Lấy toàn bộ thông tin món gọi trước của đơn đặt bàn và tính toán tổng tiền.
     *
     * @return array<string, mixed>
     */
    public function snapshotForReservation(Reservation $reservation): array
    {
        // 1.1 Tìm kiếm Order loại "PreOrder" phù hợp nhất
        $order = ReservationOrder::query()
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->where('order_type', ReservationOrderType::PreOrder->value)
            // KỸ THUẬT SQL NÂNG CAO (Custom Sorting):
            // Ưu tiên lấy Hóa đơn đang Active (0) lên đầu, sau đó đến Completed (1), còn lại (Cancelled) xếp bét (2).
            // Đảm bảo không bao giờ lấy nhầm một hóa đơn cũ đã bị hủy nếu có nhiều hóa đơn cùng loại.
            ->orderByRaw(
                'CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END',
                [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value]
            )
            ->orderByDesc('order_id')
            ->with([
                // Kỹ thuật Eager Loading Constraint:
                // Chỉ lấy các món chưa bị hủy (tránh tính tiền nhầm món khách đã bớt)
                'items' => fn ($query) => $query
                    ->where('status', '!=', ReservationOrderItemStatus::Cancelled->value)
                    ->orderBy('order_item_id'),
                'items.item',
            ])
            ->first();

        // 1.2 Trả về Data rỗng an toàn nếu khách chưa đặt món nào
        if (! $order instanceof ReservationOrder) {
            return [
                'reservation_id' => (int) $reservation->reservation_id,
                'pre_order' => [
                    'present' => false,
                    'order_id' => null,
                    'status' => null,
                    'lines' => [],
                    'totals' => [
                        'subtotal' => '0.00',
                        'currency' => null,
                    ],
                ],
            ];
        }

        // 1.3 Map dữ liệu (DTO) để trả về cho Frontend
        $lines = $order->items->map(function (ReservationOrderItem $item): array {
            return [
                'order_item_id' => (int) $item->order_item_id,
                'item_id' => (int) $item->item_id,
                'quantity' => (int) $item->quantity,
                // Định dạng tiền tệ an toàn (VD: biến 150000 thành chuỗi "150000.00" hoặc định dạng phù hợp)
                'unit_price' => Money::format($item->unit_price ?? 0, true),
                'currency' => (string) ($item->currency ?? 'VND'),
                'line_total' => Money::format($item->line_total ?? 0, true),
                'status' => $item->status?->value ?? (string) $item->status,
                // Luôn xài Tên snapshot (tên tại thời điểm bán) thay vì tên hiện tại trong kho Catalog
                'item_name_snapshot' => $item->item_name_snapshot,
                'item' => $item->relationLoaded('item') && $item->item
                    ? [
                        'item_id' => (int) $item->item->item_id,
                        'code' => $item->item->code,
                        'name' => $item->item->name,
                    ]
                    : null,
            ];
        })->values();

        // 1.4 Tính tổng tiền (Sử dụng hàm sumMinor để cộng trừ trên số nguyên, chống sai số thập phân)
        $subtotalMinor = Money::sumMinor($order->items, fn (ReservationOrderItem $item): mixed => $item->line_total ?? 0, true);
        $currency = $order->items->first()?->currency;

        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'pre_order' => [
                'present' => $lines->isNotEmpty(),
                'order_id' => (int) $order->order_id,
                'status' => $order->status?->value ?? (string) $order->status,
                'lines' => $lines->all(),
                'totals' => [
                    'subtotal' => Money::formatMinor($subtotalMinor),
                    'currency' => $currency !== null ? (string) $currency : null,
                ],
            ],
        ];
    }

    /**
     * --- BƯỚC 2: THAY THẾ TOÀN BỘ MÓN ĐẶT TRƯỚC (REPLACE PRE-ORDER) ---
     * Kịch bản: Nhân viên nghe điện thoại khách muốn đổi "1 Gà nướng" thành "2 Vịt quay".
     * Hàm này sẽ đập đi xây lại hóa đơn cũ bằng danh sách mới.
     *
     * @param  array<int, array<string, mixed>>  $requestedItems
     * @return array<string, mixed>
     */
    public function replaceForReservation(Reservation $reservation, array $requestedItems, ?int $actorUserId = null): array
    {
        $reservationId = (int) $reservation->reservation_id;

        return DB::transaction(function () use ($reservationId, $requestedItems, $actorUserId): array {
            // 2.1 PESSIMISTIC LOCKING: Khóa cứng Đơn đặt bàn để không ai (Khách hoặc Nhân viên khác)
            // có thể thao tác đè lên trong lúc mình đang sửa đơn.
            /** @var Reservation|null $lockedReservation */
            $lockedReservation = Reservation::query()
                ->whereKey($reservationId)
                ->lockForUpdate()
                ->first();

            if (! $lockedReservation instanceof Reservation) {
                throw ValidationException::withMessages([
                    'reservation_id' => ['Reservation not found.'],
                ]);
            }

            // 2.2 THẨM ĐỊNH LOGIC (POLICY CHECK)
            $this->assertReservationAllowsPreorderMutation($lockedReservation);
            $serviceStart = Carbon::parse((string) $lockedReservation->start_time)->utc();

            if (! MenuItem::supportsPreorderColumns()) {
                throw ValidationException::withMessages([
                    'pre_order_items' => ['Hệ thống chưa được đồng bộ contract pre-order. Vui lòng áp dụng patch database mới nhất rồi thử lại.'],
                ]);
            }

            // Kiểm tra giá món, có tồn tại không, quota còn đủ không...
            $prepared = $this->menuPreorderPolicyService->prepareRequestedItems(
                $requestedItems,
                $serviceStart,
                $reservationId,
            );

            // 2.3 LẤY HÓA ĐƠN CŨ RA SỬA HOẶC TẠO MỚI
            /** @var ReservationOrder|null $order */
            $order = ReservationOrder::query()
                ->where('reservation_id', $reservationId)
                ->where('order_type', ReservationOrderType::PreOrder->value)
                ->where('status', ReservationOrderStatus::Active->value)
                ->orderByDesc('order_id')
                ->lockForUpdate()
                ->first();

            $now = Carbon::now('UTC');

            if (! $order instanceof ReservationOrder) {
                $order = new ReservationOrder;
                $order->reservation_id = $reservationId;
                $order->setAttribute('order_type', ReservationOrderType::PreOrder);
                $order->status = ReservationOrderStatus::Active;
                $order->notes = null;
                $order->created_by = $actorUserId;
                $order->updated_by = $actorUserId;
                $order->created_at = $now;
                $order->updated_at = $now;
                $order->save();
            } else {
                $order->updated_by = $actorUserId;
                $order->updated_at = $now;
                $order->save();
            }

            // 2.4 DỌN DẸP MÓN CŨ (CLEANUP)
            // Khác với luồng Customer (hủy mềm - Soft Cancel để lưu vết), ở luồng Staff này,
            // có vẻ như hệ thống đang Hard Delete (Xóa hẳn) các món cũ trong Order Items.
            // Điều này có thể hiểu là quy trình của Staff muốn dọn dẹp sạch sẽ DB,
            // coi như khách đã order lại từ đầu.
            ReservationOrderItem::query()
                ->where('order_id', (int) $order->order_id)
                ->lockForUpdate()
                ->get()
                ->each(static function (ReservationOrderItem $item): void {
                    $item->delete();
                });

            // 2.5 LƯU DANH SÁCH MÓN MỚI
            foreach ($prepared['rows'] as $row) {
                $itemId = (int) $row['item_id'];
                $quantity = (int) $row['quantity'];
                $menuItem = $prepared['menu_items']->get($itemId);
                $priceRow = $prepared['price_rows']->get($itemId);

                $item = new ReservationOrderItem;
                $item->order_id = (int) $order->order_id;
                $item->item_id = $itemId;
                $item->quantity = $quantity;

                // Tính toán tiền với Minor Units
                $unitPriceMinor = Money::minorUnits($priceRow->price, true);
                $item->unit_price = Money::formatMinor($unitPriceMinor);
                $item->currency = (string) $priceRow->currency;
                $item->line_total = Money::formatMinor($unitPriceMinor * $quantity);

                $item->item_name_snapshot = $menuItem ? (string) $menuItem->name : null;
                $item->status = ReservationOrderItemStatus::Ordered;
                $item->notes = null;
                $item->updated_by = $actorUserId;
                $item->created_at = $now;
                $item->updated_at = $now;
                $item->save();
            }

            // 2.6 TRẢ VỀ DỮ LIỆU ĐÃ CẬP NHẬT
            // Eager Load lại danh sách món ăn vừa được chèn vào DB trước khi trả về.
            $lockedReservation->loadMissing('orders.items.item');

            return $this->snapshotForReservation($lockedReservation);
        });
    }

    /**
     * --- BƯỚC 3: QUY TẮC BẢO VỆ DỮ LIỆU (MUTATION POLICY) ---
     * Chặn các thao tác phi logic trong vận hành nhà hàng.
     */
    private function assertReservationAllowsPreorderMutation(Reservation $reservation): void
    {
        $status = $reservation->status instanceof ReservationStatus
            ? $reservation->status
            : ReservationStatus::from((string) $reservation->getRawOriginal('status'));

        // Chỉ đơn đang nằm chờ (Confirmed) mới được phép sửa Pre-order
        if ($status !== ReservationStatus::Confirmed) {
            throw ValidationException::withMessages([
                'reservation_id' => ['Only Confirmed reservations can update pre-order items.'],
            ]);
        }

        // Khách đã bước vào quán -> Khóa luồng Pre-order, khách phải dùng luồng Normal Order tại bàn.
        if ($reservation->checked_in_at !== null) {
            throw ValidationException::withMessages([
                'reservation_id' => ['Checked-in reservations cannot update pre-order items.'],
            ]);
        }

        // Đơn đã ăn xong và thanh toán
        if ($reservation->checked_out_at !== null) {
            throw ValidationException::withMessages([
                'reservation_id' => ['Completed reservations cannot update pre-order items.'],
            ]);
        }

        // Đơn đã hủy
        if ($reservation->cancelled_at !== null) {
            throw ValidationException::withMessages([
                'reservation_id' => ['Cancelled reservations cannot update pre-order items.'],
            ]);
        }
    }
}
