import { useEffect, useMemo, useState } from "react";
import { AlertTriangle, Clock3, RefreshCw, XCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

type SummaryItem = {
  label: string;
  value: string;
};

function SummaryRow({
  label,
  value,
  valueClassName,
}: SummaryItem & {
  valueClassName?: string;
}) {
  return (
    <div className="grid min-w-0 grid-cols-[minmax(5.75rem,0.9fr)_minmax(0,1.1fr)] items-start gap-3 rounded-md bg-secondary/40 px-3 py-2">
      <dt className="min-w-0 text-muted-foreground">{label}</dt>
      <dd className={cn("min-w-0 break-words text-right font-medium leading-5", valueClassName)} title={value}>
        {value}
      </dd>
    </div>
  );
}

function formatRemaining(ms: number): string {
  const safeMs = Math.max(0, ms);
  const totalSeconds = Math.ceil(safeMs / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;

  return `${minutes}:${String(seconds).padStart(2, "0")}`;
}

function useRemainingTime(expiresAt?: string | null): number | null {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!expiresAt) {
      return;
    }

    const timer = window.setInterval(() => setNow(Date.now()), 1000);

    return () => window.clearInterval(timer);
  }, [expiresAt]);

  if (!expiresAt) {
    return null;
  }

  const expiresAtMs = Date.parse(expiresAt);

  if (!Number.isFinite(expiresAtMs)) {
    return null;
  }

  return expiresAtMs - now;
}

export function StickyBookingSummary({
  title = "Tóm tắt đặt bàn",
  items,
  holdCode,
  holdExpiresAt,
  holdStatusLabel,
  primaryAction,
  primaryActionDisabled,
  primaryActionLabel = "Tiếp tục",
  onPrimaryAction,
  onRefreshHold,
  refreshPending,
  onCancelHold,
  cancelPending,
  className,
}: {
  title?: string;
  items: SummaryItem[];
  holdCode?: string | null;
  holdExpiresAt?: string | null;
  holdStatusLabel?: string | null;
  primaryAction?: React.ReactNode;
  primaryActionDisabled?: boolean;
  primaryActionLabel?: string;
  onPrimaryAction?: () => void;
  onRefreshHold?: () => void;
  refreshPending?: boolean;
  onCancelHold?: () => void;
  cancelPending?: boolean;
  className?: string;
}) {
  const remainingMs = useRemainingTime(holdExpiresAt);
  const expired = remainingMs !== null && remainingMs <= 0;
  const nearlyExpired = remainingMs !== null && remainingMs > 0 && remainingMs <= 90_000;
  const countdownLabel = useMemo(() => {
    if (remainingMs === null) {
      return null;
    }

    return expired ? "Đã hết hạn" : `Còn ${formatRemaining(remainingMs)}`;
  }, [expired, remainingMs]);

  return (
    <aside className={cn("sticky bottom-3 z-20 w-full max-w-full rounded-lg border bg-card p-4 shadow-lg lg:top-24 lg:self-start", className)}>
      <div className="flex min-w-0 items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-semibold">{title}</p>
          <p className="mt-1 text-xs text-muted-foreground">Kiểm tra lại trước khi chuyển bước.</p>
        </div>
        {holdStatusLabel ? (
          <Badge variant={expired ? "destructive" : nearlyExpired ? "outline" : "default"} className="shrink-0 rounded-md">
            {holdStatusLabel}
          </Badge>
        ) : null}
      </div>

      <dl className="mt-4 grid gap-2 text-sm">
        {items.map((item) => <SummaryRow key={item.label} label={item.label} value={item.value} />)}
        {holdCode ? (
          <SummaryRow label="Mã giữ bàn" value={holdCode} valueClassName="truncate font-mono text-xs" />
        ) : null}
      </dl>

      {countdownLabel ? (
        <div
          className={cn(
            "mt-3 flex items-start gap-2 rounded-md border px-3 py-2 text-sm",
            expired && "border-destructive/40 bg-destructive/10 text-destructive",
            nearlyExpired && !expired && "border-amber-200 bg-amber-50 text-amber-800",
            !expired && !nearlyExpired && "border-emerald-200 bg-emerald-50 text-emerald-800",
          )}
          aria-live="polite"
        >
          {expired || nearlyExpired ? <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" /> : <Clock3 className="mt-0.5 h-4 w-4 shrink-0" />}
          <div>
            <p className="font-medium">{countdownLabel}</p>
            <p className="text-xs opacity-85">
              {expired ? "Bàn giữ đã hết hạn. Hãy tìm bàn khác." : nearlyExpired ? "Sắp hết thời gian giữ bàn." : "Bàn đang được giữ tạm thời."}
            </p>
          </div>
        </div>
      ) : null}

      <div className="mt-4 grid gap-2">
        {primaryAction ?? (
          <Button type="button" className="min-h-11 w-full rounded-lg" disabled={primaryActionDisabled || expired} onClick={onPrimaryAction}>
            {expired ? "Tìm bàn khác" : primaryActionLabel}
          </Button>
        )}
        {(onRefreshHold || onCancelHold) && !expired ? (
          <div className={cn("grid gap-2", onRefreshHold && onCancelHold && "sm:grid-cols-2")}>
            {onRefreshHold ? (
              <Button type="button" variant="outline" className="min-h-10 w-full rounded-lg px-3" disabled={refreshPending} onClick={onRefreshHold}>
                <RefreshCw className="mr-2 h-4 w-4 shrink-0" />
                <span className="truncate">{refreshPending ? "Đang gia hạn" : "Gia hạn giữ bàn"}</span>
              </Button>
            ) : null}
            {onCancelHold ? (
              <Button type="button" variant="outline" className="min-h-10 w-full rounded-lg px-3" disabled={cancelPending} onClick={onCancelHold}>
                <XCircle className="mr-2 h-4 w-4 shrink-0" />
                <span className="truncate">{cancelPending ? "Đang hủy" : "Hủy giữ bàn"}</span>
              </Button>
            ) : null}
          </div>
        ) : null}
      </div>
    </aside>
  );
}
