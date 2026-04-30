import { CheckCircle2, Circle, Clock3 } from "lucide-react";
import { cn } from "@/lib/utils";

export type ReservationTimelineItem = {
  key: string;
  title: string;
  description: string;
  state?: "done" | "current" | "pending" | "blocked";
  meta?: string | null;
};

function iconForState(state: ReservationTimelineItem["state"]) {
  if (state === "done") {
    return <CheckCircle2 className="h-4 w-4" />;
  }

  if (state === "current") {
    return <Clock3 className="h-4 w-4" />;
  }

  return <Circle className="h-4 w-4" />;
}

export function ReservationTimeline({ items }: { items: ReservationTimelineItem[] }) {
  return (
    <section className="rounded-lg border bg-card p-4" aria-label="Dòng thời gian đặt bàn">
      <div className="mb-4">
        <h2 className="text-lg font-semibold">Dòng thời gian</h2>
        <p className="text-sm text-muted-foreground">Theo dõi những bước đã hoàn tất và việc cần làm tiếp theo.</p>
      </div>
      <ol className="space-y-3">
        {items.map((item, index) => {
          const state = item.state ?? "pending";

          return (
            <li key={item.key} className="grid grid-cols-[28px_1fr] gap-3">
              <div className="relative flex justify-center">
                <span
                  className={cn(
                    "z-10 flex h-7 w-7 items-center justify-center rounded-full border bg-background",
                    state === "done" && "border-emerald-500 bg-emerald-50 text-emerald-700",
                    state === "current" && "border-primary bg-primary/10 text-primary",
                    state === "blocked" && "border-destructive bg-destructive/10 text-destructive",
                    state === "pending" && "border-border text-muted-foreground",
                  )}
                >
                  {iconForState(state)}
                </span>
                {index < items.length - 1 ? <span className="absolute top-7 h-[calc(100%+0.75rem)] w-px bg-border" /> : null}
              </div>
              <div className="min-w-0 rounded-md bg-secondary/30 px-3 py-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-medium">{item.title}</p>
                  {item.meta ? <span className="text-xs font-medium text-muted-foreground">{item.meta}</span> : null}
                </div>
                <p className="mt-1 text-sm text-muted-foreground">{item.description}</p>
              </div>
            </li>
          );
        })}
      </ol>
    </section>
  );
}
