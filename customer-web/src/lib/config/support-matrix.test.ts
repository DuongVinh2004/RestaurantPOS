import { describe, expect, it } from "vitest";
import {
  createUnknownSupportMatrixDecision,
  customerWebSupportMatrix,
  getSupportMatrixByReleaseWave,
  getSupportMatrixEntry,
  getSupportMatrixEntryById,
  resolveSupportMatrixDecisions,
} from "./support-matrix";

describe("customer-web support matrix", () => {
  it("documents every requested customer surface without blocked wave 1 or wave 2 paths", () => {
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
    expect(features).toContain("Privacy requests");
    expect(features).toContain("Data export");
    expect(
      customerWebSupportMatrix.some(
        (entry) => (entry.releaseWave === "wave-1" || entry.releaseWave === "wave-2") && entry.status === "blocked",
      ),
    ).toBe(false);
  });

  it("locks the wave 1 go-live scope to the booking core", () => {
    expect(getSupportMatrixByReleaseWave("wave-1").map((entry) => entry.feature)).toEqual([
      "Auth session",
      "Menu catalog",
      "Table availability and holds",
      "Reservations",
    ]);
  });

  it("keeps payment-backed customer surfaces contract-visible but out of the wave 1 launch promise", () => {
    const deferred = getSupportMatrixByReleaseWave("deferred");

    expect(deferred.map((entry) => entry.feature)).toEqual([
      "Preorder",
      "Deposit self-pay",
      "Bill and active order",
    ]);
    expect(getSupportMatrixEntryById("deposit-self-pay")?.status).toBe("live-conditional");
    expect(getSupportMatrixEntryById("bill-and-active-order")?.status).toBe("live-conditional");
    expect(getSupportMatrixEntryById("deposit-self-pay")?.liveProofSummary).toMatch(/day-1 launch promise/i);
    expect(getSupportMatrixEntryById("bill-and-active-order")?.liveProofSummary).toMatch(/customer bill self-pay remains off/i);
  });

  it("keeps every wave 2 feature explicitly flag-gated and disabled by default", () => {
    const wave2 = getSupportMatrixByReleaseWave("wave-2");
    const decisions = resolveSupportMatrixDecisions({});
    const waitingList = getSupportMatrixEntryById("waiting-list");
    const accountBenefits = getSupportMatrixEntryById("account-benefits");

    expect(wave2.map((entry) => entry.feature)).toEqual([
      "Waiting list",
      "Account benefits",
      "Privacy requests",
      "Data export",
    ]);
    expect(wave2.every((entry) => entry.status === "live-conditional" && entry.exposure === "env-flag" && (entry.envFlags?.length ?? 0) > 0))
      .toBe(true);
    expect(decisions["waiting-list"].enabled).toBe(false);
    expect(decisions["account-benefits"].enabled).toBe(false);
    expect(decisions["privacy-requests"].enabled).toBe(false);
    expect(decisions["data-export"].enabled).toBe(false);
    expect(waitingList?.requiredHeaders).toEqual(["X-Customer-Token", "Idempotency-Key"]);
    expect(waitingList?.requiredHeaders).not.toContain("X-Session-Id");
    expect(waitingList?.frontendDecision).toMatch(/manual refresh/i);
    expect(accountBenefits?.requiredHeaders).toEqual(["X-Customer-Token", "Idempotency-Key"]);
    expect(accountBenefits?.frontendDecision).toMatch(/latest row_version/i);
    expect(accountBenefits?.liveProofSummary).toMatch(/voucher apply\/remove/i);
  });

  it("keeps preorder outside live launch proof and dev mocks outside production rollout", () => {
    const preorder = getSupportMatrixEntry("Preorder");
    const devMockAdapter = getSupportMatrixEntryById("dev-mock-adapter");

    expect(preorder?.releaseWave).toBe("deferred");
    expect(preorder?.status).toBe("ci-safe-only");
    expect(preorder?.exposure).toBe("env-flag");
    expect(resolveSupportMatrixDecisions({}).preorder.enabled).toBe(false);
    expect(devMockAdapter?.status).toBe("local-uat-only");
    expect(devMockAdapter?.exposure).toBe("local-only");
  });

  it("fails closed for unknown surfaces", () => {
    const unknown = createUnknownSupportMatrixDecision("Unknown surface");

    expect(unknown.enabled).toBe(false);
    expect(unknown.blocked).toBe(true);
    expect(unknown.disabledDescription).toMatch(/fail closed/i);
  });
});
