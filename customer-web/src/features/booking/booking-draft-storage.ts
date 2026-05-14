"use client";

import { ensureCustomerSessionId, getCustomerSessionId } from "@/lib/auth/storage";
import { normalizePreorderCart, type PreorderCartItem } from "@/features/preorder/cart";

export type CustomerBookingDraft = {
  browser_session_id: string;
  branch_id: number | null;
  start_time: string | null;
  duration_minutes: number | null;
  guest_count: number | null;
  guest_name: string;
  guest_phone: string;
  guest_email: string;
  notes: string;
  preorder_items: PreorderCartItem[];
  selected_table_ids: number[];
  hold_id: string | null;
  hold_expires_at: string | null;
  hold_row_version: number | null;
  updated_at_utc: string;
};

export type CustomerBookingDraftInput = Partial<Omit<CustomerBookingDraft, "browser_session_id" | "updated_at_utc" | "preorder_items" | "selected_table_ids">> & {
  preorder_items?: PreorderCartItem[] | null;
  selected_table_ids?: number[] | null;
};

const bookingDraftStorageKey = "restaurantpos.customer.booking-draft.v1";

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

function normalizeTableIds(value: unknown): number[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return [...new Set(value.map((entry) => Number(entry)).filter((entry) => Number.isInteger(entry) && entry > 0))]
    .sort((left, right) => left - right);
}

function positiveNumber(value: unknown): number | null {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function nullableString(value: unknown): string | null {
  return typeof value === "string" && value.trim() !== "" ? value : null;
}

function textValue(value: unknown): string {
  return typeof value === "string" ? value : "";
}

function emptyDraft(sessionId: string): CustomerBookingDraft {
  return {
    browser_session_id: sessionId,
    branch_id: null,
    start_time: null,
    duration_minutes: null,
    guest_count: null,
    guest_name: "",
    guest_phone: "",
    guest_email: "",
    notes: "",
    preorder_items: [],
    selected_table_ids: [],
    hold_id: null,
    hold_expires_at: null,
    hold_row_version: null,
    updated_at_utc: new Date().toISOString(),
  };
}

function normalizeDraft(value: unknown, sessionId: string): CustomerBookingDraft | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  const record = value as Partial<CustomerBookingDraft>;

  if (record.browser_session_id !== sessionId) {
    return null;
  }

  return {
    ...emptyDraft(sessionId),
    branch_id: positiveNumber(record.branch_id),
    start_time: nullableString(record.start_time),
    duration_minutes: positiveNumber(record.duration_minutes),
    guest_count: positiveNumber(record.guest_count),
    guest_name: textValue(record.guest_name),
    guest_phone: textValue(record.guest_phone),
    guest_email: textValue(record.guest_email),
    notes: textValue(record.notes),
    preorder_items: normalizePreorderCart(Array.isArray(record.preorder_items) ? record.preorder_items : []),
    selected_table_ids: normalizeTableIds(record.selected_table_ids),
    hold_id: nullableString(record.hold_id),
    hold_expires_at: nullableString(record.hold_expires_at),
    hold_row_version: positiveNumber(record.hold_row_version),
    updated_at_utc: nullableString(record.updated_at_utc) ?? new Date().toISOString(),
  };
}

export function readCustomerBookingDraft(sessionId = getCustomerSessionId()): CustomerBookingDraft | null {
  if (!sessionId) {
    return null;
  }

  const raw = browserSessionStorage()?.getItem(bookingDraftStorageKey);

  if (!raw) {
    return null;
  }

  try {
    return normalizeDraft(JSON.parse(raw) as unknown, sessionId);
  } catch {
    browserSessionStorage()?.removeItem(bookingDraftStorageKey);
    return null;
  }
}

export function storeCustomerBookingDraft(input: CustomerBookingDraftInput): CustomerBookingDraft | null {
  const storage = browserSessionStorage();

  if (!storage) {
    return null;
  }

  const sessionId = ensureCustomerSessionId();
  const current = readCustomerBookingDraft(sessionId) ?? emptyDraft(sessionId);
  const next: CustomerBookingDraft = {
    ...current,
    ...input,
    branch_id: input.branch_id === undefined ? current.branch_id : positiveNumber(input.branch_id),
    start_time: input.start_time === undefined ? current.start_time : nullableString(input.start_time),
    duration_minutes: input.duration_minutes === undefined ? current.duration_minutes : positiveNumber(input.duration_minutes),
    guest_count: input.guest_count === undefined ? current.guest_count : positiveNumber(input.guest_count),
    guest_name: input.guest_name === undefined ? current.guest_name : textValue(input.guest_name),
    guest_phone: input.guest_phone === undefined ? current.guest_phone : textValue(input.guest_phone),
    guest_email: input.guest_email === undefined ? current.guest_email : textValue(input.guest_email),
    notes: input.notes === undefined ? current.notes : textValue(input.notes),
    preorder_items: input.preorder_items === undefined ? current.preorder_items : normalizePreorderCart(input.preorder_items ?? []),
    selected_table_ids: input.selected_table_ids === undefined ? current.selected_table_ids : normalizeTableIds(input.selected_table_ids ?? []),
    hold_id: input.hold_id === undefined ? current.hold_id : nullableString(input.hold_id),
    hold_expires_at: input.hold_expires_at === undefined ? current.hold_expires_at : nullableString(input.hold_expires_at),
    hold_row_version: input.hold_row_version === undefined ? current.hold_row_version : positiveNumber(input.hold_row_version),
    browser_session_id: sessionId,
    updated_at_utc: new Date().toISOString(),
  };

  storage.setItem(bookingDraftStorageKey, JSON.stringify(next));

  return next;
}

export function clearCustomerBookingDraftHold(): void {
  const current = readCustomerBookingDraft();

  if (!current) {
    return;
  }

  storeCustomerBookingDraft({
    selected_table_ids: [],
    hold_id: null,
    hold_expires_at: null,
    hold_row_version: null,
  });
}

export function clearCustomerBookingDraft(): void {
  browserSessionStorage()?.removeItem(bookingDraftStorageKey);
}
