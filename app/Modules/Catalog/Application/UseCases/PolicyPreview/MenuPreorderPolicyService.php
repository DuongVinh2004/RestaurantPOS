<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases\PolicyPreview;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

// Vai trò: Trình kiểm duyệt chính sách Đặt món trước (Pre-order).
// Đảm bảo khách hàng không gọi những món đã hết hạn đặt, vượt quá số lượng cho phép trong ngày, hoặc những món không cho phép mang về.
class MenuPreorderPolicyService
{
    // --- BƯỚC 1: KIỂM DUYỆT DANH SÁCH MÓN ĐẶT TRƯỚC ---
    /**
     * @param  array<int, array{item_id:int, quantity:int}>  $requestedItems
     * @return array{
     * rows: array<int, array{item_id:int, quantity:int}>,
     * menu_items: Collection<int, MenuItem>,
     * price_rows: Collection<int, MenuItemPrice>
     * }
     */
    public function prepareRequestedItems(array $requestedItems, Carbon $serviceStart, ?int $ignoreReservationId = null): array
    {
        // 1.1 Chuẩn hóa dữ liệu đầu vào (loại bỏ món rác, số lượng âm)
        $rows = $this->normalizeRequestedItems($requestedItems);
        if ($rows === []) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Danh sách pre-order không hợp lệ.'],
            ]);
        }

        $itemIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['item_id'],
            $rows,
        )));

        // 1.2 Lấy thông tin các món ăn từ Database
        /** @var Collection<int, MenuItem> $menuItems */
        $menuItems = MenuItem::query()
            ->whereIn('item_id', $itemIds)
            ->where('is_available', 1)
            ->get([
                'item_id',
                'code',
                'name',
                'is_available',
                'is_preorder_enabled',
                'preorder_quota_per_day',
                'preorder_cutoff_minutes',
            ])
            ->keyBy('item_id');

        if ($menuItems->count() !== count($itemIds)) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Có món không tồn tại hoặc đang unavailable.'],
            ]);
        }

        $nowUtc = Carbon::now('UTC');

        // Gộp số lượng nếu khách cố tình gửi 2 dòng trùng ID món ăn
        $requestedQuantityByItemId = [];
        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $requestedQuantityByItemId[$itemId] = ($requestedQuantityByItemId[$itemId] ?? 0) + (int) $row['quantity'];
        }

        // [BEST PRACTICE]: Inventory Quota Check (Kiểm tra giới hạn tồn kho/năng lực)
        // Nghiệp vụ: Bếp chỉ có thể quay tối đa 20 con vịt mỗi ngày. Cần truy vấn xem hôm nay
        // đã có bao nhiêu người đặt vịt quay rồi, để biết đường từ chối nếu khách này đặt quá số lượng còn lại.
        $existingQuantityByItemId = $this->existingDailyPreorderQuantities(
            itemIds: array_keys($requestedQuantityByItemId),
            serviceStart: $serviceStart,
            ignoreReservationId: $ignoreReservationId,
        );

        // 1.3 Rào chắn kiểm duyệt từng món một (Validation Gates)
        foreach ($requestedQuantityByItemId as $itemId => $requestedQuantity) {
            /** @var MenuItem|null $menuItem */
            $menuItem = $menuItems->get($itemId);
            if (! $menuItem) {
                throw ValidationException::withMessages([
                    'pre_order_items' => ['Có món không tồn tại hoặc đang unavailable.'],
                ]);
            }

            // Guard 1: Món này có cho phép đặt trước không? (Ví dụ Kem dễ chảy nên không cho đặt trước mang về)
            if (! (bool) $menuItem->is_preorder_enabled) {
                throw ValidationException::withMessages([
                    'pre_order_items' => [sprintf('Món "%s" hiện không cho phép pre-order.', (string) $menuItem->name)],
                ]);
            }

            // Guard 2: Cut-off Time (Thời gian chốt sổ)
            // Ví dụ: Món súp bào ngư cần hầm 4 tiếng. Khách đặt ăn lúc 19h00 thì hạn chót (cutoff) phải đặt trước 15h00.
            // Nếu bây giờ là 16h00 thì hệ thống sẽ từ chối.
            $cutoffMinutes = max(0, (int) ($menuItem->preorder_cutoff_minutes ?? 0));
            if ($cutoffMinutes > 0 && $nowUtc->copy()->addMinutes($cutoffMinutes)->greaterThan($serviceStart)) {
                throw ValidationException::withMessages([
                    'pre_order_items' => [sprintf('Món "%s" đã quá thời hạn pre-order trước giờ phục vụ.', (string) $menuItem->name)],
                ]);
            }

            // Guard 3: Daily Quota (Giới hạn bán trong ngày)
            $quotaPerDay = $menuItem->preorder_quota_per_day;
            if ($quotaPerDay !== null) {
                $quotaPerDay = (int) $quotaPerDay;
                $alreadyReserved = (int) ($existingQuantityByItemId[$itemId] ?? 0);
                if ($alreadyReserved + $requestedQuantity > $quotaPerDay) {
                    throw ValidationException::withMessages([
                        'pre_order_items' => [sprintf(
                            'Món "%s" vượt quota pre-order trong ngày (%d/%d trước khi thêm yêu cầu mới).',
                            (string) $menuItem->name,
                            $alreadyReserved,
                            $quotaPerDay,
                        )],
                    ]);
                }
            }
        }

        // 1.4 Lấy giá tiền áp dụng đúng vào thời điểm khách sẽ đến ăn
        /** @var Collection<int, MenuItemPrice> $priceRows */
        $priceRows = MenuItemPrice::query()
            ->whereIn('item_id', $itemIds)
            ->effectiveAt($serviceStart) // Hàm lấy giá theo thời gian thực (Temporal Pricing)
            ->orderBy('item_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('price_id')
            ->get()
            ->groupBy('item_id')
            ->map(static fn (Collection $rows): MenuItemPrice => $rows->first());

        if ($priceRows->count() !== count($itemIds)) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Có món chưa có giá hiệu lực tại thời điểm phục vụ.'],
            ]);
        }

        return [
            'rows' => $rows,
            'menu_items' => $menuItems,
            'price_rows' => $priceRows,
        ];
    }

    // --- BƯỚC 2: RÀ SOÁT LẠI KHI KHÁCH ĐỔI LỊCH (RESCHEDULE RE-EVALUATION) ---
    public function assertReservationPreordersRemainValid(int $reservationId, Carbon $serviceStart): void
    {
        // Nghiệp vụ: Khách đã đặt bàn và đặt món thành công cho Thứ Sáu.
        // Sau đó khách gọi điện xin dời lịch sang Thứ Bảy.
        // Hệ thống BẮT BUỘC phải chạy lại bộ kiểm duyệt (Cutoff, Quota, Giá cả) cho ngày Thứ Bảy.
        // Nhỡ đâu Thứ Bảy món đó bị giới hạn bán, hoặc nhà hàng không phục vụ món đó nữa thì sao?
        $rows = ReservationOrderItem::query()
            ->join('reservation_orders as ro', 'ro.order_id', '=', 'reservation_order_items.order_id')
            ->where('ro.reservation_id', $reservationId)
            ->where('ro.order_type', ReservationOrderType::PreOrder->value)
            ->whereIn('ro.status', [
                ReservationOrderStatus::Active->value,
                ReservationOrderStatus::Completed->value,
            ])
            ->where('reservation_order_items.status', '!=', 'Cancelled')
            ->selectRaw('reservation_order_items.item_id as item_id, SUM(reservation_order_items.quantity) as quantity')
            ->groupBy('reservation_order_items.item_id')
            ->get()
            ->map(static fn ($row): array => [
                'item_id' => (int) $row->item_id,
                'quantity' => (int) $row->quantity,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        try {
            // Chạy lại bộ kiểm duyệt, nhớ bỏ qua ID của chính Đơn đặt bàn này để không bị tính trùng (Double-counting quota)
            $this->prepareRequestedItems($rows, $serviceStart, $reservationId);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'start_time' => $e->errors()['pre_order_items'] ?? ['Existing pre-order items are no longer valid for the new service time.'],
            ]);
        }
    }

    // --- BƯỚC 3: CÁC TIỆN ÍCH CHUẨN HÓA VÀ GOM NHÓM DỮ LIỆU ---
    /**
     * @param  array<int, array<string, mixed>>  $requestedItems
     * @return array<int, array{item_id:int, quantity:int}>
     */
    public function normalizeRequestedItems(array $requestedItems): array
    {
        // Lọc bỏ những dữ liệu rác (ID món ăn bằng 0, số lượng âm)
        $normalized = [];

        foreach ($requestedItems as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);
            if ($itemId <= 0 || $quantity <= 0) {
                continue;
            }

            $normalized[] = [
                'item_id' => $itemId,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, int>  $itemIds
     * @return array<int, int>
     */
    private function existingDailyPreorderQuantities(array $itemIds, Carbon $serviceStart, ?int $ignoreReservationId = null): array
    {
        // Câu truy vấn tính tổng số lượng món ăn ĐÃ ĐƯỢC ĐẶT TRONG NGÀY (Từ 0h00 đến 23h59 của ngày khách chọn)
        if ($itemIds === []) {
            return [];
        }

        $dayStart = $serviceStart->copy()->startOfDay();
        $dayEnd = $serviceStart->copy()->endOfDay();

        return ReservationOrderItem::query()
            ->join('reservation_orders as ro', 'ro.order_id', '=', 'reservation_order_items.order_id')
            ->join('reservations as r', 'r.reservation_id', '=', 'ro.reservation_id')
            ->whereIn('reservation_order_items.item_id', $itemIds)
            ->where('ro.order_type', ReservationOrderType::PreOrder->value) // Chỉ đếm những đơn đặt trước, không đếm khách gọi tại bàn
            ->whereIn('ro.status', [
                ReservationOrderStatus::Active->value,
                ReservationOrderStatus::Completed->value,
            ])
            ->whereIn('r.status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::checkedInDbValue(),
                ReservationStatus::Completed->value,
            ])
            ->whereBetween('r.start_time', [$dayStart, $dayEnd])
            ->when($ignoreReservationId !== null, static function ($query) use ($ignoreReservationId) {
                $query->where('r.reservation_id', '!=', $ignoreReservationId);
            })
            ->selectRaw('reservation_order_items.item_id as item_id, COALESCE(SUM(reservation_order_items.quantity), 0) as total_quantity')
            ->groupBy('reservation_order_items.item_id')
            ->pluck('total_quantity', 'item_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
    }
}
