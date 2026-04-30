import { Badge } from "@/components/ui/badge";
import { translateCustomerStatus } from "@/lib/i18n/customer-display";
import { cn } from "@/lib/utils";

const toneByStatus: Record<string, string> = {
  active: "border-sky-200 bg-sky-50 text-sky-700",
  applied: "border-emerald-200 bg-emerald-50 text-emerald-700",
  available: "border-emerald-200 bg-emerald-50 text-emerald-700",
  cancelled: "border-zinc-200 bg-zinc-50 text-zinc-600",
  canceled: "border-zinc-200 bg-zinc-50 text-zinc-600",
  completed: "border-emerald-200 bg-emerald-50 text-emerald-700",
  confirmed: "border-emerald-200 bg-emerald-50 text-emerald-700",
  created: "border-amber-200 bg-amber-50 text-amber-800",
  expired: "border-zinc-200 bg-zinc-50 text-zinc-600",
  failed: "border-red-200 bg-red-50 text-red-700",
  forfeited: "border-zinc-200 bg-zinc-50 text-zinc-600",
  holding: "border-amber-200 bg-amber-50 text-amber-800",
  no_show: "border-zinc-200 bg-zinc-50 text-zinc-600",
  noshow: "border-zinc-200 bg-zinc-50 text-zinc-600",
  notified: "border-sky-200 bg-sky-50 text-sky-700",
  paid: "border-emerald-200 bg-emerald-50 text-emerald-700",
  partially_refunded: "border-sky-200 bg-sky-50 text-sky-700",
  partiallyrefunded: "border-sky-200 bg-sky-50 text-sky-700",
  pending: "border-amber-200 bg-amber-50 text-amber-800",
  refunded: "border-sky-200 bg-sky-50 text-sky-700",
  reserved: "border-sky-200 bg-sky-50 text-sky-700",
  revoked: "border-zinc-200 bg-zinc-50 text-zinc-600",
  submitted: "border-sky-200 bg-sky-50 text-sky-700",
  succeeded: "border-emerald-200 bg-emerald-50 text-emerald-700",
  success: "border-emerald-200 bg-emerald-50 text-emerald-700",
  waiting: "border-amber-200 bg-amber-50 text-amber-800",
};

function normalizeStatusKey(status: string | null | undefined): string {
  return (status ?? "")
    .trim()
    .replace(/([a-z])([A-Z])/g, "$1_$2")
    .replace(/[^a-zA-Z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .toLowerCase();
}

export function formatStatusLabel(status: string | null | undefined): string {
  return translateCustomerStatus(status, "Chưa rõ");
}

export function StatusBadge({ status, className }: { status: string | null | undefined; className?: string }) {
  const label = formatStatusLabel(status);

  return (
    <Badge variant="outline" className={cn("rounded-md px-2 py-1 text-xs font-medium", toneByStatus[normalizeStatusKey(status)], className)}>
      {label}
    </Badge>
  );
}
