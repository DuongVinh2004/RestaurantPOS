import { describe, expect, it } from "vitest";
import { allowsCustomerSessionAccess, getCustomerRouteAccess } from "./route-access";

describe("customer route access policy", () => {
  it("classifies reservation self-service routes as customer-session capable", () => {
    expect(getCustomerRouteAccess("/reservations").mode).toBe("customer_session");
    expect(getCustomerRouteAccess("/reservations/new?hold_id=hold-123").mode).toBe("customer_session");
    expect(getCustomerRouteAccess("/reservations/501").mode).toBe("customer_session");
    expect(allowsCustomerSessionAccess(getCustomerRouteAccess("/reservations/501/"))).toBe(true);
  });

  it("keeps account-only customer routes out of the session-backed policy", () => {
    expect(getCustomerRouteAccess("/account").mode).toBe("account");
    expect(getCustomerRouteAccess("/waiting-list").mode).toBe("account");
    expect(getCustomerRouteAccess("/reservations/not-a-number").mode).toBe("account");
  });
});
