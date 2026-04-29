"use client";

import { AlertTriangle, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { getApiErrorDisplay } from "@/lib/api/errors";

export function LoadingBlock({ label = "Đang tải" }: { label?: string }) {
  return (
    <div className="space-y-4" aria-busy="true" aria-label={label} aria-live="polite">
      <div className="flex items-center gap-3">
        <Skeleton className="h-10 w-10 rounded-lg" />
        <div className="flex-1 space-y-2">
          <Skeleton className="h-4 w-28 rounded-md" />
          <Skeleton className="h-4 w-40 rounded-md" />
        </div>
      </div>
      <Skeleton className="h-28 w-full rounded-lg" />
      <div className="grid gap-3 sm:grid-cols-2">
        <Skeleton className="h-20 w-full rounded-lg" />
        <Skeleton className="h-20 w-full rounded-lg" />
      </div>
    </div>
  );
}

export function EmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="rounded-lg border border-dashed bg-secondary/30 p-6 text-center">
      <h3 className="text-base font-semibold">{title}</h3>
      <p className="mx-auto mt-2 max-w-sm text-sm text-muted-foreground">{description}</p>
      {action ? <div className="mt-4">{action}</div> : null}
    </div>
  );
}

export function ErrorState({
  error,
  title = "Chưa tải được nội dung",
  onRetry,
}: {
  error: unknown;
  title?: string;
  onRetry?: () => void;
}) {
  const errorDisplay = getApiErrorDisplay(error);

  return (
    <Alert variant="destructive" className="rounded-lg border-destructive/30 bg-destructive/5">
      <AlertTriangle className="h-4 w-4" />
      <AlertTitle>{title}</AlertTitle>
      <AlertDescription className="mt-2 space-y-3">
        <div className="space-y-1.5">
          <p>{errorDisplay.message}</p>
          {errorDisplay.retryHint ? <p className="text-sm">{errorDisplay.retryHint}</p> : null}
          {errorDisplay.statusLabel || errorDisplay.requestIdLabel || errorDisplay.errorCodeLabel ? (
            <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium opacity-90">
              {errorDisplay.statusLabel ? <span>{errorDisplay.statusLabel}</span> : null}
              {errorDisplay.requestIdLabel ? <span>{errorDisplay.requestIdLabel}</span> : null}
              {errorDisplay.errorCodeLabel ? <span>{errorDisplay.errorCodeLabel}</span> : null}
            </div>
          ) : null}
        </div>
        {onRetry ? (
          <Button type="button" variant="outline" size="sm" className="w-fit rounded-lg bg-background" onClick={onRetry}>
            <RefreshCw className="mr-2 h-4 w-4" />
            Thử lại
          </Button>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}
