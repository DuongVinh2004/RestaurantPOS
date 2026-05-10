<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Support\Persistence\HasRowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// --- BƯỚC 1: KHAI BÁO THỰC THỂ NGHIỆP VỤ (DOMAIN MODEL) ---
// Nghiệp vụ: Lớp này đại diện cho một Hóa đơn tài chính (VAT Invoice) chính thức được xuất ra cho khách hàng.
// Nó tách biệt hoàn toàn với ReservationOrder (Đơn gọi món). Đơn gọi món có thể bị sửa,
// nhưng Hóa đơn một khi đã xuất (hoặc hủy) thì lưu vết vĩnh viễn vì mục đích kiểm toán và thuế.
class BillingInvoice extends Model
{
    // [BEST PRACTICE]: Optimistic Locking (Khóa lạc quan)
    // Kế thừa trait HasRowVersion. Mỗi khi hóa đơn bị tác động (ví dụ: bị Void/Hủy),
    // hệ thống sẽ kiểm tra cột row_version. Ngăn chặn triệt để lỗi ghi đè dữ liệu nếu
    // có 2 nhân viên cùng thao tác hủy 1 hóa đơn cùng một phần nghìn giây.
    use HasRowVersion;

    protected $table = 'billing_invoices';

    // Xác định khóa chính rành mạch, không dùng 'id' mặc định của Laravel để tránh nhầm lẫn
    // khi JOIN nhiều bảng trong các file Repository/Service.
    protected $primaryKey = 'billing_invoice_id';

    // [BEST PRACTICE]: Mass Assignment Protection
    // Khai báo tường minh các cột được phép insert/update hàng loạt.
    protected $fillable = [
        'reservation_id',
        'invoice_number',
        'invoice_status',
        'subtotal_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'tax_code',
        'tax_name',
        'tax_rate_percentage',
        'prices_include_tax',
        'taxable_amount',
        'tax_amount',
        'seller_name',
        'seller_tax_id',
        'seller_address',
        'issued_at',
        'issued_by',
        'voided_at',
        'voided_by',
        'metadata_json',
    ];

    // --- BƯỚC 2: ÉP KIỂU DỮ LIỆU AN TOÀN (SAFE TYPE CASTING) ---
    protected $casts = [
        'billing_invoice_id' => 'int',
        'reservation_id' => 'int',
        'invoice_number' => 'string',
        'invoice_status' => 'string',

        // [BEST PRACTICE]: Exact Financial Precision (Độ chính xác tài chính)
        // Laravel Eloquent khi lấy data từ MySQL lên đôi khi sẽ hiểu nhầm số Decimal thành String.
        // Ép kiểu 'decimal:2' cho tiền tệ và 'decimal:3' cho phần trăm thuế đảm bảo
        // dữ liệu khi nạp vào bộ nhớ PHP luôn đúng định dạng toán học, tránh lỗi khi làm tròn số.
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'currency' => 'string',
        'tax_code' => 'string',
        'tax_name' => 'string',
        'tax_rate_percentage' => 'decimal:3',
        'prices_include_tax' => 'boolean',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'seller_name' => 'string',
        'seller_tax_id' => 'string',
        'seller_address' => 'string',
        'issued_at' => 'datetime',
        'issued_by' => 'int',
        'voided_at' => 'datetime',
        'voided_by' => 'int',

        // Tự động encode/decode array PHP sang JSON string khi lưu xuống MySQL
        'metadata_json' => 'array',
        'row_version' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // --- BƯỚC 3: ĐỊNH NGHĨA CÁC MỐI QUAN HỆ (RELATIONSHIPS) ---

    // Hóa đơn này thuộc về Bàn/Phiên phục vụ nào.
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    // Ai là người bấm nút xuất hóa đơn (phục vụ mục đích Audit Trail - Truy vết).
    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by', 'user_id');
    }

    // Nếu hóa đơn bị hủy, ai là người có thẩm quyền hủy nó.
    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by', 'user_id');
    }
}
