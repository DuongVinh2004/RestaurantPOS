import { describe, expect, it } from "vitest";
import { buildCustomerAuthNext, buildCustomerLoginHref, sanitizeCustomerAuthRedirect } from "./navigation";

describe("customer auth navigation helpers", () => {
  it("keeps internal next paths and rejects external redirects", () => {
    expect(sanitizeCustomerAuthRedirect("/reservations?bucket=upcoming")).toBe("/reservations?bucket=upcoming");
    expect(sanitizeCustomerAuthRedirect("https://example.test/escape")).toBe("/reservations");
    expect(sanitizeCustomerAuthRedirect("//example.test/escape")).toBe("/reservations");
    expect(sanitizeCustomerAuthRedirect("/login?next=/reservations")).toBe("/reservations");
  });

  it("preserves the active query string when building a protected-route next path", () => {
    expect(buildCustomerAuthNext("/reservations", new URLSearchParams("bucket=upcoming&view=calendar"))).toBe(
      "/reservations?bucket=upcoming&view=calendar",
    );
  });

  it("builds login hrefs from sanitized next paths", () => {
    expect(buildCustomerLoginHref("/reservations?bucket=upcoming")).toBe(
      "/login?next=%2Freservations%3Fbucket%3Dupcoming",
    );
    expect(buildCustomerLoginHref("https://example.test/escape")).toBe("/login?next=%2Freservations");
  });
});
