import { useEffect, useState } from "react";
import { Clock3 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const COMMON_SERVICE_TIMES = ["11:00", "11:30", "12:00", "12:30", "17:30", "18:00", "18:30", "19:00", "19:30", "20:00"];
const INITIAL_NOW_MS = Date.now();
const VI_WEEKDAY_LABELS = ["CN", "Th 2", "Th 3", "Th 4", "Th 5", "Th 6", "Th 7"] as const;

function localDateInputValue(value: Date): string {
  const year = value.getFullYear();
  const month = String(value.getMonth() + 1).padStart(2, "0");
  const day = String(value.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

function combineDateAndTime(dateValue: string, timeValue: string): string {
  return `${dateValue}T${timeValue}`;
}

export function formatSelectedDateLabel(value: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

  if (!match) {
    return "ngày đã chọn";
  }

  const [, yearValue, monthValue, dayValue] = match;
  const year = Number(yearValue);
  const month = Number(monthValue);
  const day = Number(dayValue);
  const utcDate = new Date(Date.UTC(year, month - 1, day));

  if (
    !Number.isInteger(year) ||
    !Number.isInteger(month) ||
    !Number.isInteger(day) ||
    utcDate.getUTCFullYear() !== year ||
    utcDate.getUTCMonth() !== month - 1 ||
    utcDate.getUTCDate() !== day
  ) {
    return "ngày đã chọn";
  }

  return `${VI_WEEKDAY_LABELS[utcDate.getUTCDay()]}, ${dayValue}/${monthValue}`;
}

export function TimeSlotGrid({
  selectedDate,
  selectedValue,
  onSelect,
  serviceTimes = COMMON_SERVICE_TIMES,
}: {
  selectedDate: string;
  selectedValue: string;
  onSelect: (nextValue: string) => void;
  serviceTimes?: string[];
}) {
  const [nowMs, setNowMs] = useState(INITIAL_NOW_MS);

  useEffect(() => {
    const timer = window.setInterval(() => setNowMs(Date.now()), 60_000);

    return () => window.clearInterval(timer);
  }, []);

  return (
    <section className="space-y-3 rounded-lg border bg-secondary/25 p-3" aria-label="Gợi ý giờ đặt bàn">
      <div className="flex items-start gap-2">
        <Clock3 className="mt-0.5 h-4 w-4 text-primary" />
        <div>
          <p className="text-sm font-semibold">Giờ phổ biến tại nhà hàng</p>
          <p className="text-xs text-muted-foreground">Chọn nhanh khung giờ trưa hoặc tối cho {formatSelectedDateLabel(selectedDate)}.</p>
        </div>
      </div>
      <div className="grid grid-cols-3 gap-2 sm:grid-cols-5">
        {serviceTimes.map((timeValue) => {
          const nextValue = combineDateAndTime(selectedDate, timeValue);
          const slotTime = new Date(nextValue).getTime();
          const disabled = Number.isFinite(slotTime) && slotTime <= nowMs;
          const active = selectedValue === nextValue;

          return (
            <Button
              key={timeValue}
              type="button"
              variant={active ? "default" : "outline"}
              className={cn("min-h-10 rounded-md px-2 text-sm", active && "shadow-none")}
              disabled={disabled}
              aria-pressed={active}
              onClick={() => onSelect(nextValue)}
            >
              {timeValue}
            </Button>
          );
        })}
      </div>
    </section>
  );
}

export function selectedDateFromLocalDateTime(value: string | null | undefined): string {
  if (value && /^\d{4}-\d{2}-\d{2}T/.test(value)) {
    return value.slice(0, 10);
  }

  return localDateInputValue(new Date());
}
