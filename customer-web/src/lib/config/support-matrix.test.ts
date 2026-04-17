import { describe, expect, it } from "vitest";
import { customerWebSupportMatrix } from "./support-matrix";

describe("customer-web support matrix", () => {
  it("documents every requested customer surface without blocked production paths", () => {
    const features = customerWebSupportMatrix.map((entry) => entry.feature);

    expect(features).toContain("Auth session");
    expect(features).toContain("Menu catalog");
    expect(features).toContain("Table availability and holds");
    expect(features).toContain("Reservations");
    expect(features).toContain("Preorder");
    expect(features).toContain("Deposit self-pay");
    expect(features).toContain("Bill and active order");
    expect(features).toContain("Waiting list");
    expect(features).toContain("Account benefits");
    expect(features).toContain("Privacy and data export");
    expect(customerWebSupportMatrix.some((entry) => entry.status === "blocked")).toBe(false);
  });
});
