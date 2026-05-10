import { ensureCustomerSessionId, getCustomerSessionId } from "@/lib/auth/storage";
import {
  normalizePreorderCart,
  type PreorderCartItem,
} from "./cart";

export type ReservationPreorderFailureStage =
  | "snapshot"
  | "preview"
  | "replace";

export type StoredPendingReservationPreorderDraft = {
  reservation_id: number;
  browser_session_id: string;
  items: PreorderCartItem[];
  failure_stage: ReservationPreorderFailureStage;
  created_at_utc: string;
};

const pendingReservationPreorderStoragePrefix =
  "restaurantpos.customer.pending-preorder.v1";

function browserSessionStorage(): Storage | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    return window.sessionStorage;
  } catch {
    return null;
  }
}

function pendingReservationPreorderStorageKey(reservationId: number): string {
  return `${pendingReservationPreorderStoragePrefix}.${reservationId}`;
}

function isStoredPendingReservationPreorderDraft(
  value: unknown,
  reservationId: number,
  browserSessionId: string,
): value is StoredPendingReservationPreorderDraft {
  if (!value || typeof value !== "object") {
    return false;
  }

  const record = value as Record<string, unknown>;
  const items = normalizePreorderCart(
    Array.isArray(record.items) ? (record.items as PreorderCartItem[]) : [],
  );

  return (
    Number(record.reservation_id) === reservationId &&
    record.browser_session_id === browserSessionId &&
    (record.failure_stage === "snapshot" ||
      record.failure_stage === "preview" ||
      record.failure_stage === "replace") &&
    items.length > 0 &&
    typeof record.created_at_utc === "string" &&
    record.created_at_utc.trim() !== ""
  );
}

export function getReservationPreorderRecoveryMessage(
  stage: ReservationPreorderFailureStage,
): string {
  switch (stage) {
    case "snapshot":
      return "Lịch đặt đã được tạo nhưng chưa tải được phiên món đặt trước. Hệ thống đã giữ lại giỏ món để bạn tiếp tục lưu.";
    case "preview":
      return "Lịch đặt đã được tạo nhưng chưa xem trước được món đặt trước. Hệ thống đã giữ lại giỏ món để bạn tiếp tục lưu.";
    case "replace":
      return "Lịch đặt đã được tạo nhưng chưa lưu được món đặt trước. Hệ thống đã giữ lại giỏ món để bạn tiếp tục cập nhật.";
  }
}

export function readStoredPendingReservationPreorderDraft(
  reservationId: number,
): StoredPendingReservationPreorderDraft | null {
  const storage = browserSessionStorage();
  const browserSessionId = getCustomerSessionId();
  const storageKey = pendingReservationPreorderStorageKey(reservationId);
  const raw = storage?.getItem(storageKey);

  if (!raw) {
    return null;
  }

  if (!browserSessionId) {
    storage?.removeItem(storageKey);
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as unknown;

    if (
      isStoredPendingReservationPreorderDraft(
        parsed,
        reservationId,
        browserSessionId,
      )
    ) {
      return {
        ...parsed,
        reservation_id: Number(parsed.reservation_id),
        items: normalizePreorderCart(parsed.items),
      };
    }
  } catch {
    // Broken storage should not block the live retry path.
  }

  storage?.removeItem(storageKey);
  return null;
}

export function storePendingReservationPreorderDraft(
  reservationId: number,
  items: PreorderCartItem[],
  failureStage: ReservationPreorderFailureStage,
): void {
  const normalizedItems = normalizePreorderCart(items);

  if (!Number.isInteger(reservationId) || reservationId <= 0 || normalizedItems.length === 0) {
    return;
  }

  const storage = browserSessionStorage();

  if (!storage) {
    return;
  }

  storage.setItem(
    pendingReservationPreorderStorageKey(reservationId),
    JSON.stringify({
      reservation_id: reservationId,
      browser_session_id: ensureCustomerSessionId(),
      items: normalizedItems,
      failure_stage: failureStage,
      created_at_utc: new Date().toISOString(),
    } satisfies StoredPendingReservationPreorderDraft),
  );
}

export function clearStoredPendingReservationPreorderDraft(
  reservationId: number,
): void {
  browserSessionStorage()?.removeItem(
    pendingReservationPreorderStorageKey(reservationId),
  );
}
