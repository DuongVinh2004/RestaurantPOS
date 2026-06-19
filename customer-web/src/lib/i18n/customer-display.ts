const STATUS_LABELS: Record<string, string> = {
  active: "Đang hoạt động",
  applied: "Đã áp dụng",
  available: "Sẵn bàn",
  blocked: "Tạm khóa",
  cancelled: "Đã hủy",
  canceled: "Đã hủy",
  checked_in: "Đã nhận bàn",
  checkedin: "Đã nhận bàn",
  closed: "Đã đóng",
  completed: "Hoàn tất",
  confirmed: "Đã xác nhận",
  created: "Đã tạo",
  error: "Lỗi",
  expired: "Đã hết hạn",
  failed: "Không thành công",
  forfeited: "Đã mất hiệu lực",
  held_in_range: "Đang giữ bàn",
  holding: "Đang giữ bàn",
  maintenance: "Bảo trì",
  no_show: "Không đến",
  noshow: "Không đến",
  occupied: "Đang phục vụ",
  occupied_now: "Đang phục vụ",
  open: "Đang mở",
  paid: "Đã thanh toán",
  partially_refunded: "Hoàn tiền một phần",
  partiallyrefunded: "Hoàn tiền một phần",
  pending: "Đang chờ",
  ready: "Sẵn sàng",
  refunded: "Đã hoàn tiền",
  reserved: "Đã đặt",
  reserved_in_range: "Đã đặt",
  revoked: "Đã thu hồi",
  seated: "Đã vào bàn",
  submitted: "Đã gửi",
  succeeded: "Thành công",
  success: "Thành công",
  warning: "Cảnh báo",
};

const MENU_TEXT_OVERRIDES: Record<string, string> = {
  "beef noodle soup": "Phở bò với nước dùng trong, ăn kèm rau thơm.",
  "bo luc lac": "Bò lúc lắc",
  "cac mon chinh": "Các món chính",
  "cac mon phuc vu trong ngay": "Các món phục vụ trong ngày",
  "chef specials": "Món bếp gợi ý",
  "chicken rice with herbs": "Cơm gà lá sen",
  "com ga rau thom": "Cơm gà lá sen",
  "do uong": "Đồ uống",
  "drinks": "Đồ uống",
  "fried rice": "Cơm chiên",
  "garden salad": "Gỏi rau Đà Lạt",
  "iced tea": "Trà sen lạnh",
  "main": "Món chính",
  "main dish": "Món chính",
  "main dishes": "Món chính",
  "mi xao me": "Mì xào bò rau củ",
  "milk tea": "Trà sữa",
  "mon an": "Món ăn",
  "mon chinh": "Món chính",
  "noodles": "Món nước",
  "pepper steak": "Bò lúc lắc sốt tiêu",
  "pho bo": "Phở bò",
  "seafood fried rice": "Cơm chiên hải sản",
  "sesame fried noodles": "Mì xào bò rau củ",
  "sesame noodles": "Mì xào bò rau củ",
  "spring roll": "Chả giò",
  "stir fried noodles": "Mì xào",
  "starter": "Món khai vị",
  "starters": "Món khai vị",
  "thuc don": "Thực đơn",
  "thuc uong": "Thức uống",
  "tra dao": "Trà sen lạnh",
  "vietnamese iced tea": "Trà sen lạnh",
};

function normalizeKey(value: string): string {
  return value
    .trim()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/([a-z])([A-Z])/g, "$1_$2")
    .replace(/[^a-zA-Z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .toLowerCase();
}

function normalizeDisplayToken(value: string): string {
  return value
    .trim()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-zA-Z0-9]+/g, " ")
    .trim()
    .toUpperCase();
}

function titleizeCode(value: string): string {
  return value
    .replace(/_/g, " ")
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function trailingTableNumber(tableCode: string): number | null {
  const match = tableCode.trim().match(/(\d+)\s*$/);
  if (!match) {
    return null;
  }

  const parsed = Number.parseInt(match[1], 10);

  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function hasVietnameseText(value: string): boolean {
  return /[À-ỹĐđ]/.test(value);
}

function looksLikeMojibake(value: string): boolean {
  return /[ÃÄÂÆ]|[\u0080-\u009f]|áº|á»/.test(value);
}

export function repairVietnameseText(value: string | null | undefined): string | null {
  if (!value) {
    return null;
  }

  const trimmed = value.trim();
  if (!trimmed || !looksLikeMojibake(trimmed)) {
    return trimmed || null;
  }

  const canDecodeAsBytes = Array.from(trimmed).every((char) => char.charCodeAt(0) <= 255);
  if (!canDecodeAsBytes) {
    return trimmed;
  }

  try {
    const bytes = Uint8Array.from(Array.from(trimmed, (char) => char.charCodeAt(0)));
    const decoded = new TextDecoder("utf-8", { fatal: true }).decode(bytes).trim();

    return decoded && hasVietnameseText(decoded) ? decoded : trimmed;
  } catch {
    return trimmed;
  }
}

export function translateCustomerStatus(value: string | null | undefined, fallback = "Không rõ"): string {
  const repaired = repairVietnameseText(value);
  if (!repaired) {
    return fallback;
  }

  return STATUS_LABELS[normalizeKey(repaired)] ?? titleizeCode(repaired);
}

export function formatCustomerZone(zone: string | null | undefined): string {
  const raw = repairVietnameseText(zone);
  if (!raw) {
    return "Chưa có khu";
  }

  const normalized = normalizeDisplayToken(raw);
  if (["A", "KHU A", "MAIN", "TANG TRET"].includes(normalized)) {
    return "Khu A";
  }

  if (["B", "KHU B", "PATIO", "SAN VUON"].includes(normalized)) {
    return "Khu B";
  }

  if (normalized === "VIP") {
    return "VIP";
  }

  if (normalized.startsWith("VIP ")) {
    return raw.replace(/^vip/i, "VIP");
  }

  if (/^[A-Z]$/.test(normalized)) {
    return `Khu ${normalized}`;
  }

  return raw;
}

export function formatCustomerTableName(
  tableCode: string | null | undefined,
  zone: string | null | undefined,
  tableId?: number | null,
): string {
  const raw = repairVietnameseText(tableCode);
  const zoneLabel = formatCustomerZone(zone);
  const hasZone = zoneLabel !== "Chưa có khu";

  if (raw) {
    const number = trailingTableNumber(raw);

    if (number !== null) {
      if (zoneLabel.toUpperCase().startsWith("VIP")) {
        return `VIP ${number}`;
      }

      return hasZone ? `${zoneLabel} - Bàn ${number}` : `Bàn ${number}`;
    }

    return raw;
  }

  if (typeof tableId === "number" && tableId > 0) {
    if (zoneLabel.toUpperCase().startsWith("VIP")) {
      return `VIP ${tableId}`;
    }
    return hasZone ? `${zoneLabel} - Bàn ${tableId}` : `Bàn ${tableId}`;
  }

  return "Bàn chưa đặt tên";
}

export function displayMenuText(value: string | null | undefined, fallback: string): string {
  const repaired = repairVietnameseText(value);
  if (!repaired) {
    return fallback;
  }

  return MENU_TEXT_OVERRIDES[normalizeDisplayToken(repaired).toLowerCase()] ?? repaired;
}
