<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Workflows;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceTaxProfileWorkflow
{
    private const SETTING_KEY = 'finance.tax_invoice_profile';

    // --- BƯỚC 1: TRUY XUẤT VÀ TỔNG HỢP TRẠNG THÁI (DESCRIBE) ---
    // Nghiệp vụ: Cung cấp bức tranh toàn cảnh về cấu hình thuế hiện tại của nhà hàng.
    // Hệ thống ưu tiên lấy cấu hình đang chạy (runtime_profile) từ Database.
    // Nếu nhà hàng mới thành lập, chưa từng thiết lập thuế, nó sẽ tự động fallback về
    // cấu hình mặc định (default_profile) trong mã nguồn để đảm bảo luồng thanh toán không bị sập.
    /**
     * @return array<string,mixed>
     */
    public function describe(): array
    {
        $row = DB::table('settings')->where('setting_key', self::SETTING_KEY)->first();
        $runtimeProfile = $row !== null ? $this->decodeStoredProfile($row->value_json) : null;
        $defaultProfile = $this->defaultProfile();
        $effectiveProfile = $runtimeProfile !== null ? $runtimeProfile : $defaultProfile;

        return [
            'setting_key' => self::SETTING_KEY,
            'default_profile' => $defaultProfile,
            'runtime_profile' => $runtimeProfile,
            'effective_profile' => $effectiveProfile,
            'source' => $runtimeProfile !== null ? 'runtime' : 'default',
            'updated_by' => $row?->updated_by !== null ? (int) $row->updated_by : null,
            'updated_at' => $row?->updated_at,
        ];
    }

    // --- BƯỚC 2: TRẢ VỀ CẤU HÌNH CÓ HIỆU LỰC (EFFECTIVE PROFILE) ---
    // Nghiệp vụ: Bất kỳ module nào cần tính tiền (Cashier, Order, Refund) chỉ cần gọi hàm này
    // là biết ngay nhà hàng đang áp dụng VAT 8% hay 10%, và giá món ăn đã bao gồm thuế chưa
    // (prices_include_tax) để bóc tách chính xác.
    /**
     * @return array<string,mixed>
     */
    public function effectiveProfile(): array
    {
        /** @var array<string,mixed> $profile */
        $profile = $this->describe()['effective_profile'];

        return $profile;
    }

    // --- BƯỚC 3: LƯU CẤU HÌNH VỚI CƠ CHẾ KHÓA KÉP (UPSERT) ---
    // Nghiệp vụ: Kế toán trưởng hoặc Quản lý cập nhật thông tin xuất hóa đơn tài chính.
    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function upsert(array $payload, ?int $actorUserId = null): array
    {
        $profile = $this->normalizeProfile($payload);

        DB::transaction(function () use ($payload, $profile, $actorUserId): void {
            // [BEST PRACTICE]: Pessimistic Locking (Khóa bi quan)
            // Ngăn chặn các tiến trình khác đụng vào dòng settings này trong lúc đang kiểm tra.
            $existing = DB::table('settings')
                ->where('setting_key', self::SETTING_KEY)
                ->lockForUpdate()
                ->first();

            $expectedUpdatedAt = $payload['expected_updated_at'] ?? null;
            if ($existing !== null) {
                // [BEST PRACTICE]: Optimistic Locking (Khóa lạc quan chống Blind Overwrite)
                // Kịch bản thực tế: Kế toán A và Quản lý B cùng mở giao diện đổi VAT.
                // A đổi VAT thành 8% và bấm Lưu. Sau đó B (vẫn đang nhìn màn hình cũ với VAT 10%)
                // sửa tên hóa đơn rồi bấm Lưu. Nếu không có cơ chế này, B sẽ vô tình đè VAT lại thành 10%.
                // Đoạn code dưới đây bắt buộc B phải truyền lên timestamp lúc B mở form,
                // nếu timestamp đó cũ hơn thời điểm A vừa lưu, B sẽ bị từ chối và phải tải lại trang!
                if ($expectedUpdatedAt === null || trim((string) $expectedUpdatedAt) === '') {
                    throw ValidationException::withMessages([
                        'expected_updated_at' => ['expected_updated_at is required when updating an existing finance tax profile.'],
                    ]);
                }

                $expectedIso = Carbon::parse((string) $expectedUpdatedAt)->utc()->toIso8601String();
                $currentIso = Carbon::parse((string) $existing->updated_at)->utc()->toIso8601String();
                if ($expectedIso !== $currentIso) {
                    throw ValidationException::withMessages([
                        'expected_updated_at' => ['Finance tax profile has been modified by another operation. Please reload and retry.'],
                    ]);
                }

                DB::table('settings')
                    ->where('setting_key', self::SETTING_KEY)
                    ->update([
                        'value_json' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_by' => $actorUserId,
                        'updated_at' => Carbon::now('UTC'),
                    ]);

                return;
            }

            // Nếu chưa có, tiến hành Insert (Lần thiết lập đầu tiên)
            DB::table('settings')->insert([
                'setting_key' => self::SETTING_KEY,
                'value_json' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_by' => $actorUserId,
                'updated_at' => Carbon::now('UTC'),
            ]);
        });

        return $this->describe();
    }

    // --- BƯỚC 4: TIỆN ÍCH CHUẨN HÓA VÀ BẢO VỆ TOÀN VẸN DỮ LIỆU ---
    /**
     * @return array<string,mixed>
     */
    private function defaultProfile(): array
    {
        return $this->normalizeProfile((array) config('booking.finance_tax_invoice_profile', []));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeStoredProfile(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $this->normalizeProfile($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $this->normalizeProfile($decoded);
    }

    /**
     * @param  array<string,mixed>  $profile
     * @return array<string,mixed>
     */
    private function normalizeProfile(array $profile): array
    {
        // [BEST PRACTICE]: Data Sanitization & Domain Invariants (Làm sạch & Bất biến nghiệp vụ)
        // 1. Ép kiểu an toàn (Safe Casting) để chống lại JSON payload độc hại hoặc sai chuẩn.
        // 2. Chặn lỗi logic kế toán nguy hiểm: VAT ($rate) bị ép buộc phải nằm trong giới hạn
        //    từ 0.0% đến 100.0% (hàm max() và min()), đồng thời được làm tròn tới 3 chữ số thập phân,
        //    ngăn chặn triệt để lỗi làm tròn dấu phẩy động (Floating point precision) trong tính toán tài chính.
        $taxCode = strtoupper(trim((string) ($profile['tax_code'] ?? 'VAT10')));
        $taxName = trim((string) ($profile['tax_name'] ?? 'VAT 10%'));
        $rate = round(max(0.0, min(100.0, (float) ($profile['tax_rate_percentage'] ?? 0.0))), 3);
        $invoicePrefix = strtoupper(trim((string) ($profile['invoice_prefix'] ?? 'INV')));
        $sellerName = trim((string) ($profile['seller_name'] ?? 'RestaurantPOS'));

        if ($taxCode === '' || $taxName === '' || $invoicePrefix === '' || $sellerName === '') {
            throw ValidationException::withMessages([
                'profile' => ['Finance tax profile contains empty required fields.'],
            ]);
        }

        return [
            'tax_code' => $taxCode,
            'tax_name' => $taxName,
            'tax_rate_percentage' => $rate,
            'prices_include_tax' => (bool) ($profile['prices_include_tax'] ?? true),
            'invoice_prefix' => $invoicePrefix,
            'seller_name' => $sellerName,
            'seller_tax_id' => $this->nullableTrimmedString($profile['seller_tax_id'] ?? null),
            'seller_address' => $this->nullableTrimmedString($profile['seller_address'] ?? null),
        ];
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
