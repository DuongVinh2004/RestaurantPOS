"use client";

const returnToActionKey = "restaurantpos.customer.return-to-action.v1";

export type CustomerReturnToAction = {
  href: string;
  label: string;
  createdAtUtc: string;
};

function browserStorage(): Storage | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    return window.sessionStorage;
  } catch {
    return null;
  }
}

export function storeCustomerReturnToAction(action: { href: string; label: string }): CustomerReturnToAction | null {
  const href = normalizeReturnHref(action.href);
  const label = action.label.trim() || "Continue";

  if (!href) {
    return null;
  }

  const storedAction: CustomerReturnToAction = {
    href,
    label,
    createdAtUtc: new Date().toISOString(),
  };

  browserStorage()?.setItem(returnToActionKey, JSON.stringify(storedAction));
  return storedAction;
}

export function peekCustomerReturnToAction(): CustomerReturnToAction | null {
  return parseStoredAction(browserStorage()?.getItem(returnToActionKey) ?? null);
}

export function consumeCustomerReturnToAction(): CustomerReturnToAction | null {
  const storage = browserStorage();
  const action = parseStoredAction(storage?.getItem(returnToActionKey) ?? null);

  storage?.removeItem(returnToActionKey);
  return action;
}

export function clearCustomerReturnToAction(): void {
  browserStorage()?.removeItem(returnToActionKey);
}

function parseStoredAction(raw: string | null): CustomerReturnToAction | null {
  if (!raw) {
    return null;
  }

  try {
    const payload: unknown = JSON.parse(raw);

    if (!payload || typeof payload !== "object" || Array.isArray(payload)) {
      return null;
    }

    const record = payload as Record<string, unknown>;
    const href = normalizeReturnHref(typeof record.href === "string" ? record.href : null);
    const label = typeof record.label === "string" && record.label.trim() !== "" ? record.label.trim() : "Continue";
    const createdAtUtc = typeof record.createdAtUtc === "string" ? record.createdAtUtc : new Date(0).toISOString();

    return href ? { href, label, createdAtUtc } : null;
  } catch {
    return null;
  }
}

function normalizeReturnHref(href: string | null | undefined): string | null {
  const candidate = (href ?? "").trim();

  if (!candidate || !candidate.startsWith("/") || candidate.startsWith("//") || candidate.startsWith("/\\")) {
    return null;
  }

  if (candidate === "/login" || candidate.startsWith("/login?")) {
    return null;
  }

  return candidate;
}
