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
    <aside className={cn("w-full max-w-full rounded-3xl border bg-card/95 backdrop-blur-xl shadow-2xl sticky bottom-[5.5rem] z-40 xl:top-20 xl:self-start", className)}>
      <div className="flex flex-col gap-4 p-4 sm:p-5">
        <div className="flex items-start justify-between gap-4">
          <div className="min-w-0 flex-1">
            <p className="text-base font-bold leading-tight mb-2.5 text-foreground tracking-tight">{title}</p>
            <ul className="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px] text-muted-foreground">
              {items.map(i => (
                <li key={i.label} className="flex items-center gap-1.5">
                  <span className="w-1.5 h-1.5 rounded-full bg-primary/40 shrink-0" />
                  <span className="font-medium text-foreground/80">{i.value}</span>
                </li>
              ))}
            </ul>
          </div>
          {countdownLabel ? (
             <div className={cn("shrink-0 flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium border", 
               expired ? "border-destructive/40 bg-destructive/10 text-destructive" : 
               nearlyExpired ? "border-amber-200 bg-amber-50 text-amber-800" : 
               "border-emerald-200 bg-emerald-50 text-emerald-800"
             )}>
               <Clock3 className="h-3 w-3" />
               <span>{countdownLabel}</span>
             </div>
          ) : holdStatusLabel ? (
            <Badge variant={expired ? "destructive" : nearlyExpired ? "outline" : "default"} className="shrink-0 rounded-full px-2 py-0.5 text-[10px]">
              {holdStatusLabel}
            </Badge>
          ) : null}
        </div>

        {primaryAction ?? (
          <Button type="button" className="min-h-12 w-full rounded-full shadow-lg" disabled={primaryActionDisabled || expired} onClick={onPrimaryAction}>
            {expired ? "Tìm bàn khác" : primaryActionLabel}
          </Button>
        )}
      </div>
    </aside>
  );
}
