import { ensureCustomerSessionId, getCustomerSessionId } from "@/lib/auth/storage";

export type CustomerPaymentSurface = "deposit" | "bill";

export type StoredCustomerPaymentSessionSnapshot = {
  surface: CustomerPaymentSurface;
  reservation_id: number;
  session_id: number;
  browser_session_id: string;
};

const paymentSessionStoragePrefix = "restaurantpos.customer.payment-session.v1";

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

function paymentSessionStorageKey(surface: CustomerPaymentSurface, reservationId: number): string {
  return `${paymentSessionStoragePrefix}.${surface}.${reservationId}`;
}

function isStoredSnapshot(
  value: unknown,
  surface: CustomerPaymentSurface,
  reservationId: number,
  browserSessionId: string,
): value is StoredCustomerPaymentSessionSnapshot {
  if (!value || typeof value !== "object") {
    return false;
  }

  const record = value as Record<string, unknown>;

  return (
    record.surface === surface &&
    Number(record.reservation_id) === reservationId &&
    Number.isInteger(record.session_id) &&
    Number(record.session_id) > 0 &&
    record.browser_session_id === browserSessionId
  );
}

export function readStoredCustomerPaymentSession(
  surface: CustomerPaymentSurface,
  reservationId: number,
): StoredCustomerPaymentSessionSnapshot | null {
  const storage = browserSessionStorage();
  const browserSessionId = getCustomerSessionId();
  const storageKey = paymentSessionStorageKey(surface, reservationId);
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

    if (isStoredSnapshot(parsed, surface, reservationId, browserSessionId)) {
      return {
        ...parsed,
        session_id: Number(parsed.session_id),
      };
    }
  } catch {
    // Broken storage should not block a fresh payment flow.
  }

  storage?.removeItem(storageKey);
  return null;
}

export function storeCustomerPaymentSession(
  surface: CustomerPaymentSurface,
  reservationId: number,
  sessionId: number,
): void {
  if (!Number.isInteger(sessionId) || sessionId <= 0) {
    return;
  }

  const storage = browserSessionStorage();

  if (!storage) {
    return;
  }

  storage.setItem(
    paymentSessionStorageKey(surface, reservationId),
    JSON.stringify({
      surface,
      reservation_id: reservationId,
      session_id: sessionId,
      browser_session_id: ensureCustomerSessionId(),
    } satisfies StoredCustomerPaymentSessionSnapshot),
  );
}

export function clearStoredCustomerPaymentSession(surface: CustomerPaymentSurface, reservationId: number): void {
  browserSessionStorage()?.removeItem(paymentSessionStorageKey(surface, reservationId));
}
