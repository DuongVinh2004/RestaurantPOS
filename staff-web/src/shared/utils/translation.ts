const translationMap: Record<string, string> = {
  'n_a': 'Không có',
  'all': 'Tất cả',
  'none': 'Không có',
  'upcoming': 'Sắp tới',
  'today': 'Hôm nay',
  'history': 'Lịch sử',
  'active': 'Đang hoạt động',
  'open': 'Đang mở',
  'closed': 'Đã đóng',
  'pending': 'Đang chờ',
  'ready': 'Sẵn sàng',
  'available': 'Sẵn bàn',
  'occupied': 'Đang phục vụ',
  'occupied_now': 'Đang phục vụ',
  'ordered': 'Đã gọi món',
  'in_progress': 'Đang thực hiện',
  'queued': 'Chờ chế biến',
  'fired': 'Đang chế biến',
  'completed': 'Hoàn tất',
  'cancelled': 'Đã hủy',
  'waiting': 'Đang chờ',
  'notified': 'Đã báo khách',
  'seated': 'Đã vào bàn',
  'assigned': 'Đã giao',
  'unassigned': 'Chưa giao',
  'mine': 'Của tôi',
  'reserved': 'Đã đặt',
  'reserved_in_range': 'Đã đặt',
  'held_in_range': 'Đang giữ bàn',
  'blocked': 'Tạm khóa',
  'maintenance': 'Bảo trì',
  'confirmed': 'Đã xác nhận',
  'checkedin': 'Đã nhận bàn',
  'checked_in': 'Đã nhận bàn',
  'noshow': 'Không đến',
  'no_show': 'Không đến',
  'paid': 'Đã thanh toán',
  'settled': 'Đã quyết toán',
  'unpaid': 'Chưa thanh toán',
  'partial_paid': 'Thanh toán một phần',
  'partially_refunded': 'Hoàn tiền một phần',
  'partiallyrefunded': 'Hoàn tiền một phần',
  'not_required': 'Không bắt buộc',
  'notrequired': 'Không bắt buộc',
  'refunded': 'Đã hoàn tiền',
  'forfeited': 'Mất cọc',
  'processing': 'Đang xử lý',
  'success': 'Thành công',
  'warning': 'Cảnh báo',
  'error': 'Lỗi',
  'cash': 'Tiền mặt',
  'card': 'Thẻ',
  'banktransfer': 'Chuyển khoản',
  'other': 'Khác',
  'default': 'Mặc định',
  'unknown': 'Không rõ',
  'unknown_actor': 'Tác nhân chưa rõ',
  'unknown_customer': 'Khách chưa xác định',
  'walk_in': 'Khách vãng lai',
  'walk_in_unknown': 'Khách vãng lai / chưa rõ',
  'webchat': 'Web chat',
  'facebook': 'Facebook',
  'zalo': 'Zalo',
  'whatsapp': 'WhatsApp',
  'instagram': 'Instagram',
  'line': 'LINE',
  'email': 'Email',
  'real': 'Gửi thật',
  'spam': 'Tin rác',
  'staff_user': 'Nhân viên',
  'staff_api_key': 'Khóa API nhân viên',
  'customer_account': 'Tài khoản khách hàng',
  'customer_access_session': 'Phiên truy cập khách hàng',
  'customer_session': 'Phiên khách hàng',
  'webhook_provider': 'Nhà cung cấp webhook',
  'system': 'Hệ thống',
  'reservation': 'Đặt bàn',
  'order': 'Đơn hàng',
  'payment': 'Thanh toán',
  'table': 'Bàn',
  'restaurant_table': 'Bàn',
  'reservation_order': 'Đơn hàng',
  'cashier_shift': 'Ca thu ngân',
  'subject': 'Đối tượng tùy chỉnh',
  'primary': 'Chính',
  'table_count': 'Số bàn',
  'checked_in_at': 'Nhận bàn lúc',
  'arrival_confirmed': 'Đã xác nhận đến',
  'accepted': 'Đã nhận',
  'declined': 'Từ chối',
  'invite_expired': 'Lời mời hết hạn',
  'action_required': 'Cần xử lý',
  'assignment_candidate': 'Có thể xếp khách',
  'check_in': 'Nhận bàn',
  'move_table': 'Chuyển bàn',
  'not_applicable': 'Không áp dụng',
  'capability_missing': 'Thiếu quyền',
  'missing': 'Còn thiếu',
  'due_soon': 'Sắp đến giờ',
  'overdue': 'Quá giờ',
  'high': 'Cao',
  'low': 'Thấp',
  'normal': 'Bình thường',
  'mixed_currency': 'Lệch loại tiền',
  'discrepancy': 'Chênh lệch',
  'over_refund': 'Hoàn quá số tiền',
  'overpaid': 'Thu dư',
  'outstanding': 'Còn nợ',
  'healthy': 'Ổn định',
  'degraded': 'Cần kiểm tra',
  'empty_scope': 'Phạm vi trống',
  'reporting_snapshot_empty': 'Ph\u1ea1m vi tr\u1ed1ng',
  'reporting_snapshot_stale': 'Snapshot stale',
  'reporting_snapshot_scope_partial': 'Stale t\u1eebng ph\u1ea7n',
};

function normalizeTranslationKey(value: string): string {
  return value
    .trim()
    .replace(/([a-z])([A-Z])/g, '$1_$2')
    .replace(/[^a-zA-Z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .toLowerCase();
}

export function translateUiLabel(value: string | null | undefined, fallback = 'Không có'): string {
  if (!value) {
    return fallback;
  }

  const translated = translationMap[normalizeTranslationKey(value)];
  return translated ?? value;
}

export function translateUiCode(value: string | null | undefined, fallback = 'Không có'): string {
  if (!value) {
    return fallback;
  }

  const translated = translationMap[normalizeTranslationKey(value)];
  if (translated) {
    return translated;
  }

  return value
    .replace(/_/g, ' ')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}
