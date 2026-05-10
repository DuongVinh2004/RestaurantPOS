import { beforeEach, describe, expect, it } from "vitest";
import {
  clearCustomerReturnToAction,
  consumeCustomerReturnToAction,
  peekCustomerReturnToAction,
  storeCustomerReturnToAction,
} from "./return-to-action";

describe("customer return-to-action storage", () => {
  beforeEach(() => {
    window.sessionStorage.clear();
  });

  it("stores, peeks, and consumes an internal return target", () => {
    const stored = storeCustomerReturnToAction({ href: "/reservations/new?hold_id=hold-1", label: "Finish booking" });

    expect(stored).toMatchObject({
      href: "/reservations/new?hold_id=hold-1",
      label: "Finish booking",
    });
    expect(peekCustomerReturnToAction()).toMatchObject({
      href: "/reservations/new?hold_id=hold-1",
      label: "Finish booking",
    });
    expect(consumeCustomerReturnToAction()).toMatchObject({
      href: "/reservations/new?hold_id=hold-1",
      label: "Finish booking",
    });
    expect(peekCustomerReturnToAction()).toBeNull();
  });

  it("rejects external and login return targets", () => {
    expect(storeCustomerReturnToAction({ href: "https://example.test", label: "Bad" })).toBeNull();
    expect(storeCustomerReturnToAction({ href: "//example.test", label: "Bad" })).toBeNull();
    expect(storeCustomerReturnToAction({ href: "/login?next=/account", label: "Bad" })).toBeNull();
    expect(peekCustomerReturnToAction()).toBeNull();
  });

  it("clears stored return targets", () => {
    storeCustomerReturnToAction({ href: "/account", label: "Account" });

    clearCustomerReturnToAction();

    expect(peekCustomerReturnToAction()).toBeNull();
  });
});
