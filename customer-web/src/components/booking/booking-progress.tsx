import { CheckCircle2 } from "lucide-react";
import { cn } from "@/lib/utils";

export type BookingProgressStep = {
  key: string;
  label: string;
  description: string;
};

export const bookingProgressSteps: BookingProgressStep[] = [
  { key: "time", label: "Thời gian", description: "Ngày, giờ và số khách" },
  { key: "table", label: "Chọn bàn", description: "Bàn phù hợp" },
  { key: "preorder", label: "Chọn món", description: "Món đặt trước" },
  { key: "guest", label: "Liên hệ", description: "Xác nhận đặt bàn" },
];

export function BookingProgress({
  currentStep,
  steps = bookingProgressSteps,
}: {
  currentStep: string;
  steps?: BookingProgressStep[];
}) {
  const currentIndex = Math.max(0, steps.findIndex((step) => step.key === currentStep));

  return (
    <nav aria-label="Tiến trình đặt bàn" className="rounded-lg border bg-card p-3">
      <ol className="grid gap-2 sm:grid-cols-4">
        {steps.map((step, index) => {
          const complete = index < currentIndex;
          const active = index === currentIndex;

          return (
            <li
              key={step.key}
              className={cn(
                "flex min-w-0 items-center gap-3 rounded-md border px-3 py-2",
                active && "border-primary bg-primary/5",
                complete && "border-emerald-200 bg-emerald-50",
                !active && !complete && "border-border bg-secondary/30",
              )}
              aria-current={active ? "step" : undefined}
            >
              <span
                className={cn(
                  "flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold",
                  active && "bg-primary text-primary-foreground",
                  complete && "bg-emerald-600 text-white",
                  !active && !complete && "bg-background text-muted-foreground",
                )}
              >
                {complete ? <CheckCircle2 className="h-4 w-4" /> : index + 1}
              </span>
              <span className="min-w-0">
                <span className="block truncate text-sm font-semibold">{step.label}</span>
                <span className="block truncate text-xs text-muted-foreground">{step.description}</span>
              </span>
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
