"use client";

import type { ComponentProps, CSSProperties, ReactNode } from "react";
import { useId } from "react";
import { AlertTriangle, Minus, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { getApiErrorDisplay } from "@/lib/api/errors";
import { cn } from "@/lib/utils";

type FieldSupportProps = {
  label?: ReactNode;
  helperText?: ReactNode;
  error?: ReactNode;
};

export function AppButton({ className, size = "lg", ...props }: ComponentProps<typeof Button>) {
  const iconOnly = typeof size === "string" && size.startsWith("icon");

  return (
    <Button
      size={size}
      className={cn(
        "min-h-11 rounded-lg font-semibold shadow-none",
        iconOnly ? "min-w-11 p-0" : "px-4",
        className,
      )}
      {...props}
    />
  );
}

export function AppCard({ className, ...props }: ComponentProps<typeof Card>) {
  return <Card className={cn("rounded-lg border bg-card shadow-[var(--restaurant-shadow)]", className)} {...props} />;
}

export function AppBadge({ className, variant = "outline", ...props }: ComponentProps<typeof Badge>) {
  return <Badge variant={variant} className={cn("rounded-md px-2 py-1", className)} {...props} />;
}

export function AppInput({
  id,
  label,
  helperText,
  error,
  className,
  ...props
}: ComponentProps<typeof Input> & FieldSupportProps) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const descriptionId = `${inputId}-description`;

  return (
    <div className="space-y-2">
      {label ? <Label htmlFor={inputId}>{label}</Label> : null}
      <Input
        id={inputId}
        aria-describedby={helperText || error ? descriptionId : undefined}
        aria-invalid={Boolean(error) || undefined}
        className={cn("min-h-11 rounded-lg", className)}
        {...props}
      />
      {helperText || error ? (
        <p id={descriptionId} className={cn("text-sm", error ? "text-destructive" : "text-muted-foreground")}>
          {error ?? helperText}
        </p>
      ) : null}
    </div>
  );
}

export type AppSelectOption = {
  value: string;
  label: ReactNode;
  disabled?: boolean;
};

export function AppSelect({
  id,
  label,
  helperText,
  error,
  value,
  onValueChange,
  placeholder,
  options,
  disabled,
}: FieldSupportProps & {
  id?: string;
  value?: string;
  onValueChange: (value: string) => void;
  placeholder?: string;
  options: AppSelectOption[];
  disabled?: boolean;
}) {
  const generatedId = useId();
  const selectId = id ?? generatedId;
  const descriptionId = `${selectId}-description`;

  return (
    <div className="space-y-2">
      {label ? <Label htmlFor={selectId}>{label}</Label> : null}
      <Select value={value} onValueChange={onValueChange} disabled={disabled}>
        <SelectTrigger
          id={selectId}
          className="min-h-11 w-full rounded-lg"
          aria-describedby={helperText || error ? descriptionId : undefined}
          aria-invalid={Boolean(error) || undefined}
        >
          <SelectValue placeholder={placeholder} />
        </SelectTrigger>
        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value} disabled={option.disabled}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {helperText || error ? (
        <p id={descriptionId} className={cn("text-sm", error ? "text-destructive" : "text-muted-foreground")}>
          {error ?? helperText}
        </p>
      ) : null}
    </div>
  );
}

export function AppTextarea({
  id,
  label,
  helperText,
  error,
  className,
  ...props
}: ComponentProps<typeof Textarea> & FieldSupportProps) {
  const generatedId = useId();
  const textareaId = id ?? generatedId;
  const descriptionId = `${textareaId}-description`;

  return (
    <div className="space-y-2">
      {label ? <Label htmlFor={textareaId}>{label}</Label> : null}
      <Textarea
        id={textareaId}
        aria-describedby={helperText || error ? descriptionId : undefined}
        aria-invalid={Boolean(error) || undefined}
        className={cn("min-h-28 rounded-lg", className)}
        {...props}
      />
      {helperText || error ? (
        <p id={descriptionId} className={cn("text-sm", error ? "text-destructive" : "text-muted-foreground")}>
          {error ?? helperText}
        </p>
      ) : null}
    </div>
  );
}

export function AppSkeleton({ className, ...props }: ComponentProps<typeof Skeleton>) {
  return <Skeleton className={cn("rounded-lg", className)} {...props} />;
}

export function EmptyState({
  title,
  description,
  action,
  className,
}: {
  title: string;
  description: string;
  action?: ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("rounded-lg border border-dashed bg-secondary/45 p-6 text-center", className)}>
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
  className,
}: {
  error: unknown;
  title?: string;
  onRetry?: () => void;
  className?: string;
}) {
  const errorDisplay = getApiErrorDisplay(error);

  return (
    <div className={cn("rounded-lg border border-destructive/30 bg-destructive/5 p-4", className)} role="alert">
      <div className="flex gap-3">
        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-destructive" />
        <div className="space-y-3">
          <div className="space-y-1">
            <h3 className="font-medium text-destructive">{title}</h3>
            <p className="text-sm text-muted-foreground">{errorDisplay.message}</p>
            {errorDisplay.retryHint ? <p className="text-sm text-muted-foreground">{errorDisplay.retryHint}</p> : null}
          </div>
          {onRetry ? (
            <AppButton type="button" variant="outline" size="sm" className="bg-background" onClick={onRetry}>
              Thử lại
            </AppButton>
          ) : null}
        </div>
      </div>
    </div>
  );
}

export function SectionHeader({
  eyebrow,
  title,
  description,
  action,
  className,
}: {
  eyebrow?: string;
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between", className)}>
      <div className="space-y-1">
        {eyebrow ? <p className="text-sm font-semibold text-primary">{eyebrow}</p> : null}
        <h2 className="text-2xl font-semibold leading-tight tracking-normal">{title}</h2>
        {description ? <p className="max-w-2xl text-sm text-muted-foreground">{description}</p> : null}
      </div>
      {action ? <div className="shrink-0">{action}</div> : null}
    </div>
  );
}

export function PriceText({
  amount,
  currency = "VND",
  className,
}: {
  amount: number | string | null | undefined;
  currency?: string | null;
  className?: string;
}) {
  if (amount === null || amount === undefined || amount === "") {
    return <span className={cn("font-semibold tabular-nums", className)}>Chưa có</span>;
  }

  const numericAmount = typeof amount === "string" ? Number(amount) : amount;
  if (!Number.isFinite(numericAmount)) {
    return <span className={cn("font-semibold tabular-nums", className)}>{`${amount} ${currency ?? "VND"}`}</span>;
  }

  const value = Number(numericAmount);

  return <span className={cn("font-semibold tabular-nums", className)}>{formatPrice(value, currency ?? "VND")}</span>;
}

export type StatusTone = "neutral" | "success" | "warning" | "danger" | "info";

const statusToneClasses: Record<StatusTone, string> = {
  neutral: "border-zinc-200 bg-zinc-50 text-zinc-700",
  success: "border-teal-200 bg-teal-50 text-teal-800",
  warning: "border-amber-200 bg-amber-50 text-amber-900",
  danger: "border-red-200 bg-red-50 text-red-700",
  info: "border-teal-200 bg-teal-50 text-teal-800",
};

export function StatusPill({
  label,
  tone = "neutral",
  className,
}: {
  label: string;
  tone?: StatusTone;
  className?: string;
}) {
  return (
    <span className={cn("inline-flex min-h-7 items-center rounded-md border px-2 text-xs font-medium", statusToneClasses[tone], className)}>
      {label}
    </span>
  );
}

export function StepIndicator({
  steps,
  currentStep,
  className,
}: {
  steps: Array<{ label: string; description?: string }>;
  currentStep: number;
  className?: string;
}) {
  return (
    <ol className={cn("grid gap-2 sm:grid-cols-[repeat(var(--step-count),minmax(0,1fr))]", className)} style={{ "--step-count": steps.length } as CSSProperties}>
      {steps.map((step, index) => {
        const stepNumber = index + 1;
        const active = stepNumber === currentStep;
        const complete = stepNumber < currentStep;

        return (
          <li key={step.label} className="flex items-start gap-3 rounded-lg border bg-card p-3">
            <span
              className={cn(
                "flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold",
                complete && "border-primary bg-primary text-primary-foreground",
                active && "border-primary text-primary",
              )}
            >
              {stepNumber}
            </span>
            <span className="min-w-0">
              <span className="block text-sm font-medium">{step.label}</span>
              {step.description ? <span className="block text-xs text-muted-foreground">{step.description}</span> : null}
            </span>
          </li>
        );
      })}
    </ol>
  );
}

export function QuantityStepper({
  value,
  min = 0,
  max = 99,
  label = "Số lượng",
  onChange,
  className,
}: {
  value: number;
  min?: number;
  max?: number;
  label?: string;
  onChange: (value: number) => void;
  className?: string;
}) {
  const decrementDisabled = value <= min;
  const incrementDisabled = value >= max;

  return (
    <div className={cn("inline-flex items-center gap-2 rounded-lg border bg-background p-1", className)}>
      <AppButton
        type="button"
        variant="ghost"
        size="icon"
        aria-label={`Giảm ${label}`}
        disabled={decrementDisabled}
        onClick={() => onChange(Math.max(min, value - 1))}
      >
        <Minus className="h-4 w-4" />
      </AppButton>
      <span className="min-w-8 text-center text-sm font-semibold tabular-nums" aria-label={`${label}: ${value}`}>
        {value}
      </span>
      <AppButton
        type="button"
        variant="ghost"
        size="icon"
        aria-label={`Tăng ${label}`}
        disabled={incrementDisabled}
        onClick={() => onChange(Math.min(max, value + 1))}
      >
        <Plus className="h-4 w-4" />
      </AppButton>
    </div>
  );
}

export function BottomSheet({
  open,
  onOpenChange,
  trigger,
  title,
  description,
  children,
  footer,
}: {
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  trigger?: ReactNode;
  title: string;
  description?: string;
  children: ReactNode;
  footer?: ReactNode;
}) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      {trigger ? <SheetTrigger asChild>{trigger}</SheetTrigger> : null}
      <SheetContent side="bottom" className="max-h-[85svh] rounded-t-lg">
        <SheetHeader className="text-left">
          <SheetTitle>{title}</SheetTitle>
          {description ? <SheetDescription>{description}</SheetDescription> : null}
        </SheetHeader>
        <div className="overflow-y-auto px-4 pb-4">{children}</div>
        {footer ? <SheetFooter>{footer}</SheetFooter> : null}
      </SheetContent>
    </Sheet>
  );
}

export function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel = "Xác nhận",
  cancelLabel = "Hủy",
  destructive = false,
  onConfirm,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description: string;
  confirmLabel?: string;
  cancelLabel?: string;
  destructive?: boolean;
  onConfirm: () => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="rounded-lg">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <AppButton type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {cancelLabel}
          </AppButton>
          <AppButton
            type="button"
            variant={destructive ? "destructive" : "default"}
            onClick={() => {
              onConfirm();
              onOpenChange(false);
            }}
          >
            {confirmLabel}
          </AppButton>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function formatPrice(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency,
      maximumFractionDigits: currency === "VND" ? 0 : 2,
    }).format(amount);
  } catch {
    return `${amount.toLocaleString("vi-VN")} ${currency}`;
  }
}
