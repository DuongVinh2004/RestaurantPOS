import { asRecord, numberValue, stringValue } from "@/lib/contracts/loose";

export type CustomerOrderTrackingStatus =
  | "received"
  | "preparing"
  | "cooking"
  | "ready"
  | "served"
  | "completed"
  | "cancelled";

export type CustomerOrderTrackingItem = {
  orderItemId: number | null;
  name: string;
  quantity: number | null;
  status: CustomerOrderTrackingStatus;
  rawStatus: string | null;
};

export type CustomerOrderTrackingState = {
  present: boolean;
  orderId: number | null;
  status: CustomerOrderTrackingStatus;
  rawStatus: string | null;
  terminal: boolean;
  createdAt: string | null;
  estimatedRemainingMinutes: number | null;
  items: CustomerOrderTrackingItem[];
};

export const orderTrackingSteps: Array<{ status: CustomerOrderTrackingStatus; label: string }> = [
  { status: "received", label: "Đã nhận đơn" },
  { status: "preparing", label: "Đang chuẩn bị" },
  { status: "cooking", label: "Đang nấu" },
  { status: "ready", label: "Sẵn sàng" },
  { status: "served", label: "Đã phục vụ" },
  { status: "completed", label: "Hoàn tất" },
];

const orderTrackingRank: Record<CustomerOrderTrackingStatus, number> = {
  received: 0,
  preparing: 1,
  cooking: 2,
  ready: 3,
  served: 4,
  completed: 5,
  cancelled: -1,
};

export function getOrderTrackingState(value: unknown): CustomerOrderTrackingState {
  const record = asRecord(value);

  if (!record) {
    return {
      present: false,
      orderId: null,
      status: "received",
      rawStatus: null,
      terminal: false,
      createdAt: null,
      estimatedRemainingMinutes: null,
      items: [],
    };
  }

  const rawStatus = stringValue(record, ["status", "order_status"]);
  const status = normalizeOrderTrackingStatus(rawStatus);
  const rawItems = Array.isArray(record.items) ? record.items : [];

  return {
    present: true,
    orderId: numberValue(record, ["order_id", "active_order_id"]),
    status,
    rawStatus,
    terminal: isOrderTrackingTerminal(status),
    createdAt: stringValue(record, ["created_at", "ordered_at", "submitted_at"]),
    estimatedRemainingMinutes: numberValue(record, ["estimated_remaining_minutes", "eta_minutes", "remaining_minutes"]),
    items: rawItems.map((item) => parseOrderTrackingItem(item)),
  };
}

export function normalizeOrderTrackingStatus(value: string | null | undefined): CustomerOrderTrackingStatus {
  const normalized = (value ?? "").toLowerCase().replace(/[_-]/g, " ");

  if (normalized.includes("cancel")) return "cancelled";
  if (normalized.includes("complete") || normalized.includes("closed") || normalized.includes("paid") || normalized.includes("finished")) {
    return "completed";
  }
  if (normalized.includes("served")) return "served";
  if (normalized.includes("ready")) return "ready";
  if (normalized.includes("cook") || normalized.includes("fire") || normalized.includes("kitchen")) return "cooking";
  if (normalized.includes("prep")) return "preparing";

  return "received";
}

export function isOrderTrackingTerminal(status: CustomerOrderTrackingStatus): boolean {
  return status === "completed" || status === "cancelled";
}

export function isOrderTrackingStepComplete(
  currentStatus: CustomerOrderTrackingStatus,
  stepStatus: CustomerOrderTrackingStatus,
): boolean {
  if (currentStatus === "cancelled") {
    return stepStatus === "received";
  }

  return orderTrackingRank[stepStatus] <= orderTrackingRank[currentStatus];
}

function parseOrderTrackingItem(value: unknown): CustomerOrderTrackingItem {
  const record = asRecord(value);
  const rawStatus = stringValue(record, ["status", "item_status"]);
  const item = asRecord(record?.item);

  return {
    orderItemId: numberValue(record, ["order_item_id", "id"]),
    name:
      stringValue(record, ["item_name_snapshot", "item_name", "name"]) ??
      stringValue(item, ["name"]) ??
      "Món trong thực đơn",
    quantity: numberValue(record, ["quantity", "qty"]),
    status: normalizeOrderTrackingStatus(rawStatus),
    rawStatus,
  };
}
