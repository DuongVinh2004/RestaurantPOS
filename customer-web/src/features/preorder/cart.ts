import { formatMoney, stringValue } from "@/lib/contracts/format";
import { asRecord, numberValue, recordValue } from "@/lib/contracts/loose";
import type {
  CustomerMenuItem,
  CustomerMenuPreorderPreviewEnvelope,
  CustomerReservationPreorderPayload,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export type PreorderCartItem = {
  item_id: number;
  quantity: number;
};

export type ParsedMenuPreorderPreview = {
  quantity: number | null;
  itemCount: number | null;
  subtotal: string | null;
  currency: string | null;
  warnings: string[];
  policyMessage: string | null;
};

export function normalizePreorderCart(items: PreorderCartItem[]): PreorderCartItem[] {
  return items
    .filter(
      (item) =>
        Number.isInteger(item.item_id) &&
        item.item_id > 0 &&
        Number.isInteger(item.quantity) &&
        item.quantity > 0,
    )
    .sort((left, right) => left.item_id - right.item_id);
}

export function preorderCartSignature(items: PreorderCartItem[]): string {
  return normalizePreorderCart(items)
    .map((item) => `${item.item_id}:${item.quantity}`)
    .join("|");
}

export function preorderCartFromReservation(
  payload: CustomerReservationPreorderPayload,
): PreorderCartItem[] {
  return normalizePreorderCart(payload.pre_order.normalized_pre_order_items);
}

export function preorderCartQuantity(
  cart: PreorderCartItem[],
  itemId: number,
): number {
  return cart.find((item) => item.item_id === itemId)?.quantity ?? 0;
}

export function updatePreorderCartItem(
  cart: PreorderCartItem[],
  itemId: number,
  rawQuantity: number,
): PreorderCartItem[] {
  const quantity = Number.isFinite(rawQuantity)
    ? Math.max(0, Math.floor(rawQuantity))
    : 0;

  return normalizePreorderCart([
    ...cart.filter((item) => item.item_id !== itemId),
    ...(quantity > 0 ? [{ item_id: itemId, quantity }] : []),
  ]);
}

export function preorderCartTotalQuantity(items: PreorderCartItem[]): number {
  return normalizePreorderCart(items).reduce(
    (total, item) => total + item.quantity,
    0,
  );
}

export function menuItemPrice(item: CustomerMenuItem): string {
  return formatMoney(item.price.amount ?? "0.00", item.price.currency ?? "USD");
}

export function parseMenuPreorderPreview(
  preview: CustomerMenuPreorderPreviewEnvelope["data"],
): ParsedMenuPreorderPreview {
  const record = asRecord(preview);
  const totals = recordValue(record, ["totals"]);
  const policy = recordValue(record, ["policy"]);
  const warnings = Array.isArray(record?.warnings)
    ? record.warnings.filter(
        (warning): warning is string =>
          typeof warning === "string" && warning.trim() !== "",
      )
    : [];

  return {
    quantity: numberValue(totals, ["quantity"]),
    itemCount: numberValue(totals, ["item_count", "itemCount"]),
    subtotal: stringValue(totals, ["subtotal", "subtotal_amount", "amount", "total"]),
    currency: stringValue(totals, ["currency"]),
    warnings,
    policyMessage: stringValue(policy, ["message", "summary", "description"]),
  };
}
