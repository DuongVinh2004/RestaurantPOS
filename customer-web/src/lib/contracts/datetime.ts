const localDateTimePattern = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/;

function padTwo(value: number): string {
  return String(value).padStart(2, "0");
}

export function parseLocalDateTimeInput(value: string): Date | null {
  const match = localDateTimePattern.exec(value);

  if (!match) {
    return null;
  }

  const [, year, month, day, hour, minute] = match;
  const parsed = new Date(
    Number(year),
    Number(month) - 1,
    Number(day),
    Number(hour),
    Number(minute),
    0,
    0,
  );

  if (
    Number.isNaN(parsed.getTime()) ||
    parsed.getFullYear() !== Number(year) ||
    parsed.getMonth() !== Number(month) - 1 ||
    parsed.getDate() !== Number(day) ||
    parsed.getHours() !== Number(hour) ||
    parsed.getMinutes() !== Number(minute)
  ) {
    return null;
  }

  return parsed;
}

export function formatLocalDateTimeInput(date: Date): string {
  return [
    date.getFullYear(),
    padTwo(date.getMonth() + 1),
    padTwo(date.getDate()),
  ].join("-") + `T${padTwo(date.getHours())}:${padTwo(date.getMinutes())}`;
}

export function createRoundedFutureLocalDateTimeInput(daysFromNow = 1): string {
  const date = new Date(Date.now() + daysFromNow * 24 * 60 * 60 * 1000);
  date.setMinutes(0, 0, 0);

  return formatLocalDateTimeInput(date);
}

export function toUtcIsoFromLocalDateTimeInput(value: string): string {
  const parsed = parseLocalDateTimeInput(value);

  if (!parsed) {
    throw new Error(`Invalid local date time input: ${value}`);
  }

  return parsed.toISOString();
}

export function localDateTimeRangeToUtc(value: string, durationMinutes: number) {
  const parsed = parseLocalDateTimeInput(value);

  if (!parsed) {
    throw new Error(`Invalid local date time input: ${value}`);
  }

  if (!Number.isFinite(durationMinutes)) {
    throw new Error(`Invalid duration: ${durationMinutes}`);
  }

  const end = new Date(parsed.getTime() + durationMinutes * 60_000);

  return {
    start_time: parsed.toISOString(),
    end_time: end.toISOString(),
  };
}
