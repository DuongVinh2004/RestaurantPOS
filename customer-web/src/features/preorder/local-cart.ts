"use client";

import { startTransition, useCallback, useEffect, useMemo, useState } from "react";
import type { CustomerMenuItem } from "@/lib/contracts/generated/restaurantpos-sdk";
import { ensureCustomerSessionId } from "@/lib/auth/storage";

const cartVersion = 1;
const cartKeyPrefix = "restaurantpos.customer.preorder-cart.v1";
const cartChangedEventName = "restaurantpos:preorder-cart-changed";

export type LocalPreorderServeTiming = "when_arrived" | "after_seated" | "custom_note";

export type LocalPreorderCartItem = {
  item_id: number;
  name: string;
  quantity: number;
  note: string;
  price_amount: string | null;
  currency: string | null;
  image_url: string | null;
  is_available: boolean;
  preorder_enabled: boolean;
  updated_at: string;
};

export type LocalPreorderCart = {
  version: 1;
  session_id: string;
  branch_id: number | null;
  serve_timing: LocalPreorderServeTiming;
  serve_note: string;
  items: LocalPreorderCartItem[];
  updated_at: string;
};

export type LocalPreorderSubmitItem = {
  item_id: number;
  quantity: number;
};

type LocalPreorderCartChangedDetail = {
  sessionId: string;
  branchId: number | null;
  storageKey: string;
};

function browserStorage(): Storage | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    return window.localStorage;
  } catch {
    return null;
  }
}

function cartStorageKey(sessionId: string, branchId: number | null | undefined): string {
  return `${cartKeyPrefix}.${sessionId}.${branchId ?? "default"}`;
}

function notifyCartChanged(sessionId: string, branchId: number | null | undefined): void {
  if (typeof window === "undefined") {
    return;
  }

  window.dispatchEvent(
    new CustomEvent<LocalPreorderCartChangedDetail>(cartChangedEventName, {
      detail: {
        sessionId,
        branchId: branchId ?? null,
        storageKey: cartStorageKey(sessionId, branchId),
      },
    }),
  );
}

function emptyCart(sessionId: string, branchId: number | null): LocalPreorderCart {
  return {
    version: cartVersion,
    session_id: sessionId,
    branch_id: branchId,
    serve_timing: "when_arrived",
    serve_note: "",
    items: [],
    updated_at: new Date().toISOString(),
  };
}

function normalizeQuantity(value: number): number {
  return Number.isFinite(value) ? Math.max(0, Math.min(99, Math.floor(value))) : 0;
}

function normalizeNote(value: string | null | undefined): string {
  return (value ?? "").trim().slice(0, 240);
}

function isCartRecord(value: unknown): value is LocalPreorderCart {
  if (!value || typeof value !== "object") {
    return false;
  }

  const record = value as Partial<LocalPreorderCart>;

  return record.version === cartVersion && typeof record.session_id === "string" && Array.isArray(record.items);
}

export function readLocalPreorderCart(sessionId: string, branchId: number | null | undefined): LocalPreorderCart {
  const key = cartStorageKey(sessionId, branchId);
  const raw = browserStorage()?.getItem(key);

  if (!raw) {
    return emptyCart(sessionId, branchId ?? null);
  }

  try {
    const parsed = JSON.parse(raw) as unknown;

    if (!isCartRecord(parsed)) {
      return emptyCart(sessionId, branchId ?? null);
    }

    return {
      ...emptyCart(sessionId, branchId ?? null),
      ...parsed,
      branch_id: branchId ?? null,
      items: parsed.items
        .filter((item) => Number.isInteger(item.item_id) && item.item_id > 0 && item.quantity > 0)
        .map((item) => ({
          ...item,
          quantity: normalizeQuantity(item.quantity),
          note: normalizeNote(item.note),
        })),
    };
  } catch {
    return emptyCart(sessionId, branchId ?? null);
  }
}

export function writeLocalPreorderCart(cart: LocalPreorderCart): void {
  browserStorage()?.setItem(cartStorageKey(cart.session_id, cart.branch_id), JSON.stringify(cart));
  notifyCartChanged(cart.session_id, cart.branch_id);
}

export function clearLocalPreorderCart(sessionId: string, branchId: number | null | undefined): void {
  browserStorage()?.removeItem(cartStorageKey(sessionId, branchId));
  notifyCartChanged(sessionId, branchId);
}

export function localCartSubtotal(cart: LocalPreorderCart): { amount: number; currency: string } {
  const firstCurrency = cart.items.find((item) => item.currency)?.currency ?? "VND";
  const amount = cart.items.reduce((total, item) => {
    const price = Number(item.price_amount ?? 0);

    return total + (Number.isFinite(price) ? price * item.quantity : 0);
  }, 0);

  return { amount, currency: firstCurrency };
}

export function localCartQuantity(cart: LocalPreorderCart): number {
  return cart.items.reduce((total, item) => total + item.quantity, 0);
}

export function localCartSubmitItems(cart: LocalPreorderCart): LocalPreorderSubmitItem[] {
  return cart.items
    .filter((item) => item.quantity > 0 && item.is_available && item.preorder_enabled)
    .map((item) => ({ item_id: item.item_id, quantity: item.quantity }));
}

export function localCartHasUnsupportedNotes(cart: LocalPreorderCart): boolean {
  return cart.serve_timing !== "when_arrived" || cart.serve_note.trim() !== "" || cart.items.some((item) => item.note.trim() !== "");
}

function itemSnapshot(item: CustomerMenuItem, quantity: number, note = ""): LocalPreorderCartItem {
  return {
    item_id: item.item_id,
    name: item.name,
    quantity: normalizeQuantity(quantity),
    note: normalizeNote(note),
    price_amount: item.price.amount,
    currency: item.price.currency,
    image_url: item.img_url,
    is_available: item.is_available,
    preorder_enabled: item.preorder.enabled,
    updated_at: new Date().toISOString(),
  };
}

export function useLocalPreorderCart(branchId: number | null | undefined) {
  const [sessionId, setSessionId] = useState<string | null>(null);
  const [cart, setCart] = useState<LocalPreorderCart | null>(null);
  const effectiveBranchId = branchId ?? null;

  useEffect(() => {
    const nextSessionId = ensureCustomerSessionId();

    startTransition(() => {
      setSessionId(nextSessionId);
      setCart(readLocalPreorderCart(nextSessionId, effectiveBranchId));
    });
  }, [effectiveBranchId]);

  useEffect(() => {
    if (!sessionId || typeof window === "undefined") {
      return;
    }

    const storageKey = cartStorageKey(sessionId, effectiveBranchId);
    const refreshCart = () => {
      startTransition(() => {
        setCart(readLocalPreorderCart(sessionId, effectiveBranchId));
      });
    };
    const handleCartChanged = (event: Event) => {
      const detail = (event as CustomEvent<LocalPreorderCartChangedDetail>).detail;

      if (detail?.storageKey === storageKey) {
        refreshCart();
      }
    };
    const handleStorage = (event: StorageEvent) => {
      if (event.key === storageKey) {
        refreshCart();
      }
    };

    window.addEventListener(cartChangedEventName, handleCartChanged);
    window.addEventListener("storage", handleStorage);

    return () => {
      window.removeEventListener(cartChangedEventName, handleCartChanged);
      window.removeEventListener("storage", handleStorage);
    };
  }, [effectiveBranchId, sessionId]);

  const commit = useCallback((updater: (current: LocalPreorderCart) => LocalPreorderCart) => {
    const nextSessionId = sessionId ?? ensureCustomerSessionId();
    const current = readLocalPreorderCart(nextSessionId, effectiveBranchId);
    const next = {
      ...updater(current),
      session_id: nextSessionId,
      branch_id: effectiveBranchId,
      updated_at: new Date().toISOString(),
    };

    writeLocalPreorderCart(next);
    setSessionId(nextSessionId);
    setCart(next);
    return next;
  }, [effectiveBranchId, sessionId]);

  const addItem = useCallback((item: CustomerMenuItem, quantity = 1, note = "") => {
    return commit((current) => {
      const existing = current.items.find((entry) => entry.item_id === item.item_id);
      const nextQuantity = normalizeQuantity((existing?.quantity ?? 0) + quantity);
      const nextItem = itemSnapshot(item, nextQuantity, note || existing?.note || "");

      return {
        ...current,
        items: [
          ...current.items.filter((entry) => entry.item_id !== item.item_id),
          ...(nextQuantity > 0 ? [nextItem] : []),
        ].sort((left, right) => left.item_id - right.item_id),
      };
    });
  }, [commit]);

  const updateQuantity = useCallback((itemId: number, quantity: number) => {
    commit((current) => ({
      ...current,
      items: current.items
        .map((item) => (item.item_id === itemId ? { ...item, quantity: normalizeQuantity(quantity), updated_at: new Date().toISOString() } : item))
        .filter((item) => item.quantity > 0),
    }));
  }, [commit]);

  const updateNote = useCallback((itemId: number, note: string) => {
    commit((current) => ({
      ...current,
      items: current.items.map((item) => (item.item_id === itemId ? { ...item, note: normalizeNote(note), updated_at: new Date().toISOString() } : item)),
    }));
  }, [commit]);

  const removeItem = useCallback((itemId: number) => {
    commit((current) => ({
      ...current,
      items: current.items.filter((item) => item.item_id !== itemId),
    }));
  }, [commit]);

  const clear = useCallback(() => {
    const nextSessionId = sessionId ?? ensureCustomerSessionId();

    clearLocalPreorderCart(nextSessionId, effectiveBranchId);
    setSessionId(nextSessionId);
    setCart(emptyCart(nextSessionId, effectiveBranchId));
  }, [effectiveBranchId, sessionId]);

  const setServeTiming = useCallback((serveTiming: LocalPreorderServeTiming, serveNote = "") => {
    commit((current) => ({
      ...current,
      serve_timing: serveTiming,
      serve_note: normalizeNote(serveNote),
    }));
  }, [commit]);

  const resolvedCart = useMemo(
    () => cart ?? emptyCart(sessionId ?? "", effectiveBranchId),
    [cart, effectiveBranchId, sessionId],
  );

  return {
    cart: resolvedCart,
    sessionId,
    quantity: localCartQuantity(resolvedCart),
    subtotal: localCartSubtotal(resolvedCart),
    submitItems: localCartSubmitItems(resolvedCart),
    hasUnsupportedNotes: localCartHasUnsupportedNotes(resolvedCart),
    addItem,
    updateQuantity,
    updateNote,
    removeItem,
    clear,
    setServeTiming,
  };
}
