import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

const toneByStatus: Record<string, string> = {
  Confirmed: "border-emerald-200 bg-emerald-50 text-emerald-700",
  Reserved: "border-sky-200 bg-sky-50 text-sky-700",
  Completed: "border-emerald-200 bg-emerald-50 text-emerald-700",
  Success: "border-emerald-200 bg-emerald-50 text-emerald-700",
  Succeeded: "border-emerald-200 bg-emerald-50 text-emerald-700",
  Paid: "border-emerald-200 bg-emerald-50 text-emerald-700",
  Refunded: "border-sky-200 bg-sky-50 text-sky-700",
  PartiallyRefunded: "border-sky-200 bg-sky-50 text-sky-700",
  Pending: "border-amber-200 bg-amber-50 text-amber-800",
  Created: "border-amber-200 bg-amber-50 text-amber-800",
  Holding: "border-amber-200 bg-amber-50 text-amber-800",
  Waiting: "border-amber-200 bg-amber-50 text-amber-800",
  Notified: "border-sky-200 bg-sky-50 text-sky-700",
  Submitted: "border-sky-200 bg-sky-50 text-sky-700",
  Active: "border-sky-200 bg-sky-50 text-sky-700",
  Failed: "border-red-200 bg-red-50 text-red-700",
  Cancelled: "border-zinc-200 bg-zinc-50 text-zinc-600",
  Expired: "border-zinc-200 bg-zinc-50 text-zinc-600",
  Revoked: "border-zinc-200 bg-zinc-50 text-zinc-600",
  Forfeited: "border-zinc-200 bg-zinc-50 text-zinc-600",
  NoShow: "border-zinc-200 bg-zinc-50 text-zinc-600",
};

const labelByStatus: Record<string, string> = {
  Active: "Đang hoạt động",
  Applied: "Đã áp dụng",
  Available: "Có thể dùng",
  Cancelled: "Đã hủy",
  Completed: "Hoàn tất",
  Confirmed: "Đã xác nhận",
  Created: "Đã tạo",
  Expired: "Đã hết hạn",
  Failed: "Không thành công",
  Forfeited: "Đã mất hiệu lực",
  Holding: "Đang giữ bàn",
  NoShow: "Không đến",
  Notified: "Đã thông báo",
  Paid: "Đã thanh toán",
  PartiallyRefunded: "Hoàn một phần",
  Pending: "Đang chờ",
  Refunded: "Đã hoàn tiền",
  Reserved: "Đang phục vụ",
  Revoked: "Đã thu hồi",
  Submitted: "Đã gửi",
  Succeeded: "Thành công",
  Success: "Thành công",
  Waiting: "Đang chờ",
};

export function formatStatusLabel(status: string | null | undefined): string {
  if (!status) {
    return "Chưa rõ";
  }

  return labelByStatus[status] ?? status.replace(/[_-]+/g, " ").replace(/([a-z])([A-Z])/g, "$1 $2");
}

export function StatusBadge({ status, className }: { status: string | null | undefined; className?: string }) {
  const label = formatStatusLabel(status);

  return (
    <Badge variant="outline" className={cn("rounded-md px-2 py-1 text-xs font-medium", toneByStatus[status ?? ""], className)}>
      {label}
    </Badge>
  );
}
