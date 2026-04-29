const CUSTOMER_LOCALE = "vi-VN";

export function formatMoney(amount: string | number | null | undefined, currency = "USD"): string {
  if (amount === null || amount === undefined || amount === "") {
    return "Chưa có";
  }

  const numeric = Number(amount);

  if (Number.isNaN(numeric)) {
    return `${amount} ${currency}`;
  }

  return new Intl.NumberFormat(CUSTOMER_LOCALE, {
    style: "currency",
    currency,
  }).format(numeric);
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return "Chưa lên lịch";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat(CUSTOMER_LOCALE, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}

export function stringValue(record: Record<string, unknown> | null | undefined, keys: string[]): string | null {
  if (!record) return null;

  for (const key of keys) {
    const value = record[key];
    if (typeof value === "string" && value.trim() !== "") return value;
    if (typeof value === "number") return String(value);
  }

  return null;
}
