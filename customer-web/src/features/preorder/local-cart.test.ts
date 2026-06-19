import { beforeEach, describe, expect, it } from "vitest";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import type { CustomerMenuItem } from "@/lib/contracts/generated/restaurantpos-sdk";
import {
  localCartHasUnsupportedNotes,
  localCartQuantity,
  localCartSubmitItems,
  localCartSubtotal,
  readLocalPreorderCart,
  writeLocalPreorderCart,
  type LocalPreorderCart,
} from "./local-cart";

function cart(overrides: Partial<LocalPreorderCart> = {}): LocalPreorderCart {
  return {
    version: 1,
    session_id: ensureCustomerSessionId(),
    branch_id: 10,
    serve_timing: "when_arrived",
    serve_note: "",
    items: [],
    updated_at: "2026-01-01T00:00:00.000Z",
    ...overrides,
  };
}

function item(overrides: Partial<CustomerMenuItem> = {}): CustomerMenuItem {
  return {
    item_id: 101,
    category_id: 5,
    category_name: "Mains",
    code: "ITEM-101",
    name: "Noodle bowl",
    description: "Warm noodles",
    img_url: null,
    is_available: true,
    is_combo: false,
    is_best_seller: false,
    price: {
      price_id: 1,
      amount: "45000",
      currency: "VND",
      effective_from: null,
      effective_to: null,
    },
    preorder: {
      enabled: true,
      cutoff_minutes: 30,
      quota_per_day: null,
      requires_preview_validation: true,
    },
    created_at: null,
    updated_at: null,
    ...overrides,
  };
}

describe("local preorder cart", () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
    ensureCustomerSessionId();
  });

  it("stores carts separately by session and branch", () => {
    const sessionId = ensureCustomerSessionId();

    writeLocalPreorderCart(cart({ session_id: sessionId, branch_id: 10, items: [{ ...snapshotItem(item()), quantity: 2 }] }));
    writeLocalPreorderCart(cart({ session_id: sessionId, branch_id: 11, items: [{ ...snapshotItem(item({ item_id: 202 })), quantity: 1 }] }));

    expect(readLocalPreorderCart(sessionId, 10).items).toHaveLength(1);
    expect(readLocalPreorderCart(sessionId, 10).items[0]?.item_id).toBe(101);
    expect(readLocalPreorderCart(sessionId, 11).items[0]?.item_id).toBe(202);
  });

  it("creates the backend-safe submit shape from available preorder items only", () => {
    const value = cart({
      items: [
        { ...snapshotItem(item({ item_id: 101 })), quantity: 2 },
        { ...snapshotItem(item({ item_id: 102, is_available: false })), quantity: 1 },
        { ...snapshotItem(item({ item_id: 103, preorder: { ...item().preorder, enabled: false } })), quantity: 1 },
      ],
    });

    expect(localCartSubmitItems(value)).toEqual([{ item_id: 101, quantity: 2 }]);
    expect(localCartQuantity(value)).toBe(4);
    expect(localCartSubtotal(value)).toEqual({ amount: 180000, currency: "VND" });
  });

  it("flags local notes and serve timing as unsupported by the preorder submit contract", () => {
    expect(localCartHasUnsupportedNotes(cart())).toBe(false);
    expect(localCartHasUnsupportedNotes(cart({ serve_timing: "after_seated" }))).toBe(true);
    expect(localCartHasUnsupportedNotes(cart({ items: [{ ...snapshotItem(item()), note: "Less spicy" }] }))).toBe(true);
  });
});

function snapshotItem(menuItem: CustomerMenuItem) {
  return {
    item_id: menuItem.item_id,
    name: menuItem.name,
    quantity: 1,
    note: "",
    price_amount: menuItem.price.amount,
    currency: menuItem.price.currency,
    image_url: menuItem.img_url,
    is_available: menuItem.is_available,
    preorder_enabled: menuItem.preorder.enabled,
    updated_at: "2026-01-01T00:00:00.000Z",
  };
}
