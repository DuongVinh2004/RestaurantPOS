import { useEffect, useMemo, useState } from "react";
import { AlertTriangle, Clock3 } from "lucide-react";
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
    <div className="min-w-0 rounded-md border bg-background/80 px-2.5 py-2">
      <dt className="min-w-0 truncate text-[11px] font-medium leading-4 text-muted-foreground">{label}</dt>
      <dd className={cn("mt-0.5 min-w-0 truncate text-sm font-semibold leading-5", valueClassName)} title={value}>
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
    <aside className={cn("w-full max-w-full rounded-lg border bg-card shadow-[var(--restaurant-shadow)] xl:sticky xl:top-20 xl:self-start", className)}>
      <div className="space-y-3 p-3">
        <div className="flex min-w-0 items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-sm font-semibold leading-5">{title}</p>
            <p className="mt-0.5 text-xs text-muted-foreground">Tóm tắt trước khi giữ chỗ.</p>
          </div>
          {holdStatusLabel ? (
            <Badge variant={expired ? "destructive" : nearlyExpired ? "outline" : "default"} className="shrink-0 rounded-md px-2 py-1 text-xs">
              {holdStatusLabel}
            </Badge>
          ) : null}
        </div>

        <dl className="grid min-w-0 grid-cols-2 gap-2">
          {items.map((item) => <SummaryRow key={item.label} label={item.label} value={item.value} />)}
          {holdCode ? (
            <SummaryRow label="Mã giữ bàn" value={holdCode} valueClassName="font-mono text-xs" />
          ) : null}
        </dl>

        {countdownLabel ? (
          <div
            className={cn(
              "flex items-center gap-2 rounded-md border px-2.5 py-2 text-sm",
              expired && "border-destructive/40 bg-destructive/10 text-destructive",
              nearlyExpired && !expired && "border-amber-200 bg-amber-50 text-amber-800",
              !expired && !nearlyExpired && "border-emerald-200 bg-emerald-50 text-emerald-800",
            )}
            aria-live="polite"
          >
            {expired || nearlyExpired ? <AlertTriangle className="h-4 w-4 shrink-0" /> : <Clock3 className="h-4 w-4 shrink-0" />}
            <div className="min-w-0">
              <p className="truncate font-medium">{countdownLabel}</p>
              <p className="truncate text-xs opacity-85">
                {expired ? "Bàn giữ đã hết hạn." : nearlyExpired ? "Sắp hết thời gian giữ bàn." : "Bàn đang được giữ tạm thời."}
              </p>
            </div>
          </div>
        ) : null}
      </div>

      <div className="border-t bg-secondary/30 p-3">
        {primaryAction ?? (
          <Button type="button" className="min-h-10 w-full rounded-lg" disabled={primaryActionDisabled || expired} onClick={onPrimaryAction}>
            {expired ? "Tìm bàn khác" : primaryActionLabel}
          </Button>
        )}
      </div>
    </aside>
  );
}
