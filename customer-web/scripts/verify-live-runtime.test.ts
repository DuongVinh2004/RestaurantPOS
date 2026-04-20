import { describe, expect, it } from "vitest";
import { createLiveRuntimeConfig } from "./verify-live-runtime.mjs";

const manifestPath = "C:\\repo\\storage\\app\\uat\\scenario-pack.json";
const freshNow = new Date("2026-04-19T09:00:00Z");

function createManifest(overrides: Record<string, unknown> = {}) {
  return {
    pack: {
      generated_at_utc: "2026-04-19T08:55:00Z",
      base_url: "http://127.0.0.1:8000",
    },
    auth: {
      staff: {
        api_key: "spk_live_staff_fixture",
      },
      customer_primary: {
        username: "uat.customer.primary",
        password: "UatDemo!123",
      },
    },
    reservations: {
      deposit_pending: {
        reservation_id: 28,
      },
      dine_in_checkin: {
        reservation_id: 29,
        row_version: 1,
      },
      benefits_pending: {
        reservation_id: 30,
      },
    },
    scenarios: {
      deposit_self_pay: {
        payment_amount: "100000.00",
        provider_code: "simulated",
      },
      dine_in_checkout: {
        menu_item_ids: [24],
        table_id: 70,
      },
      waiting_list_lifecycle: {
        branch_id: 5,
        customer_user_id: 18,
        table_id: 70,
      },
      benefits: {
        reservation_id: 30,
        user_voucher_id: 10,
        loyalty_points: 50,
      },
    },
    waiting_list: {
      seeded_waiting_entry: {
        waiting_id: 44,
      },
    },
    ...overrides,
  };
}

function presentManifest(data = createManifest()) {
  return {
    path: manifestPath,
    exists: true,
    error: null,
    data,
  };
}

function liveEnv(overrides: Record<string, string> = {}): NodeJS.ProcessEnv {
  return {
    CUSTOMER_WEB_LIVE_IDENTIFIER: "uat.customer.primary",
    CUSTOMER_WEB_LIVE_PASSWORD: "UatDemo!123",
    CUSTOMER_WEB_LIVE_API_BASE_URL: "http://127.0.0.1:8000",
    NEXT_PUBLIC_ENABLE_DEV_MOCKS: "false",
    ...overrides,
    NODE_ENV: "test",
  } as NodeJS.ProcessEnv;
}

describe("createLiveRuntimeConfig", () => {
  it("requires live env vars and a valid UAT manifest", () => {
    const config = createLiveRuntimeConfig({
      env: {
        NODE_ENV: "test",
      } as NodeJS.ProcessEnv,
      manifestStatus: {
        path: manifestPath,
        exists: false,
        data: null,
        error: null,
      },
    });

    expect(config.issues).toEqual([
      expect.stringContaining("UAT manifest not found"),
      "CUSTOMER_WEB_LIVE_IDENTIFIER is required for live verification.",
      "CUSTOMER_WEB_LIVE_PASSWORD is required for live verification.",
    ]);
  });

  it("accepts env vars when the canonical UAT manifest is present and fresh", () => {
    const config = createLiveRuntimeConfig({
      env: liveEnv(),
      now: freshNow,
      manifestStatus: presentManifest(),
    });

    expect(config.issues).toEqual([]);
    expect(config.healthUrl).toBe("http://127.0.0.1:8000/api/v1/health");
    expect(config.appHealthUrl).toBe("http://127.0.0.1:3000/login");
    expect(config.identifier).toBe("uat.customer.primary");
    expect(config.proof.depositPaymentSession.status).toBe("simulated-local-uat");
    expect(config.proof.billPaymentSession.status).toBe("runtime-prerequisites-present");
    expect(config.proof.waitingList.status).toBe("runtime-prerequisites-present");
    expect(config.proof.accountBenefits.status).toBe("runtime-prerequisites-present");
    expect(config.proof.privacyTools.status).toBe("runtime-prerequisites-present");
    expect(config.proof.dataExport.status).toBe("runtime-prerequisites-present");
  });

  it("fails stale or mismatched UAT manifests before live proof", () => {
    const config = createLiveRuntimeConfig({
      env: liveEnv({
        CUSTOMER_WEB_LIVE_API_BASE_URL: "http://uat.example.test",
        CUSTOMER_WEB_LIVE_MAX_MANIFEST_AGE_MINUTES: "30",
      }),
      now: new Date("2026-04-19T09:45:00Z"),
      manifestStatus: presentManifest(),
    });

    expect(config.issues).toEqual([
      expect.stringContaining("older than 30 minutes"),
      expect.stringContaining("was generated for http://127.0.0.1:8000"),
    ]);
  });

  it("rejects mock fallback and live-skip flags for the npm live lane", () => {
    const config = createLiveRuntimeConfig({
      env: liveEnv({
        NEXT_PUBLIC_ENABLE_DEV_MOCKS: "true",
        CUSTOMER_WEB_LIVE_E2E_ALLOW_SKIP: "true",
      }),
      now: freshNow,
      manifestStatus: presentManifest(),
    });

    expect(config.issues).toEqual([
      "Live verification requires NEXT_PUBLIC_ENABLE_DEV_MOCKS=false so the browser cannot fall back to mock adapters.",
      "CUSTOMER_WEB_LIVE_E2E_ALLOW_SKIP is not supported by npm run test:e2e:live or npm run verify:release:live. Use npm run verify:release for the CI-safe lane.",
    ]);
  });

  it("fails clearly when the UAT manifest cannot support positive bill live proof", () => {
    const config = createLiveRuntimeConfig({
      env: liveEnv(),
      now: freshNow,
      manifestStatus: presentManifest(
        createManifest({
          auth: {
            customer_primary: {
              username: "uat.customer.primary",
              password: "UatDemo!123",
            },
          },
          reservations: {
            deposit_pending: {},
          },
          scenarios: {
            deposit_self_pay: {
              payment_amount: "0",
              provider_code: "",
            },
            dine_in_checkout: {
              menu_item_ids: [],
            },
          },
          waiting_list: undefined,
        }),
      ),
    });

    expect(config.issues).toEqual([
      `Canonical UAT manifest at ${manifestPath} is missing auth.staff.api_key. Live bill proof requires the canonical UAT staff key.`,
      `Canonical UAT manifest at ${manifestPath} is missing scenarios.dine_in_checkout.menu_item_ids. Refresh the pack before running live verification.`,
      `Canonical UAT manifest at ${manifestPath} is missing reservations.deposit_pending.reservation_id. Refresh the pack before running live verification.`,
      `Canonical UAT manifest at ${manifestPath} is missing scenarios.deposit_self_pay.payment_amount. Refresh the pack before running live verification.`,
      `Canonical UAT manifest at ${manifestPath} is missing scenarios.deposit_self_pay.provider_code. Refresh the pack before running live verification.`,
      `Canonical UAT manifest at ${manifestPath} is missing reservations.dine_in_checkin.reservation_id. Refresh the pack before running live verification.`,
      `Canonical UAT manifest at ${manifestPath} is missing reservations.dine_in_checkin.row_version. Refresh the pack before running live verification.`,
      `Canonical UAT manifest at ${manifestPath} is missing scenarios.dine_in_checkout.table_id. Refresh the pack before running live verification.`,
    ]);
  });

  it("classifies requested payment and Wave 2 diagnostics without fake runtime success", () => {
    const config = createLiveRuntimeConfig({
      env: liveEnv({
        CUSTOMER_WEB_LIVE_EXERCISE_DEPOSIT_PAYMENT_SESSION: "true",
        CUSTOMER_WEB_LIVE_EXERCISE_BILL_PAYMENT_SESSION: "true",
        CUSTOMER_WEB_LIVE_EXERCISE_WAITING_LIST: "true",
        CUSTOMER_WEB_LIVE_EXERCISE_ACCOUNT_BENEFITS: "true",
        CUSTOMER_WEB_LIVE_EXERCISE_PRIVACY_TOOLS: "true",
        CUSTOMER_WEB_LIVE_EXERCISE_DATA_EXPORT: "true",
        NEXT_PUBLIC_FEATURE_WAITING_LIST: "true",
        NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS: "true",
        NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS: "true",
        NEXT_PUBLIC_FEATURE_DATA_EXPORT: "true",
      }),
      now: freshNow,
      manifestStatus: presentManifest(
        createManifest({
          scenarios: {
            deposit_self_pay: {
              payment_amount: "100000.00",
              provider_code: "stripe",
            },
            dine_in_checkout: {
              menu_item_ids: [24],
              table_id: 70,
            },
          },
          waiting_list: undefined,
          reservations: {
            deposit_pending: {
              reservation_id: 28,
            },
            dine_in_checkin: {
              reservation_id: 29,
              row_version: 1,
            },
          },
        }),
      ),
    });

    expect(config.proof.depositPaymentSession.status).toBe("enabled-runtime-support");
    expect(config.proof.billPaymentSession.status).toBe("enabled-runtime-support");
    expect(config.proof.waitingList.status).toBe("enabled-missing-data");
    expect(config.proof.accountBenefits.status).toBe("enabled-missing-data");
    expect(config.proof.privacyTools.status).toBe("enabled-runtime-prerequisites-present");
    expect(config.proof.dataExport.status).toBe("enabled-runtime-prerequisites-present");
    expect(config.issues).toContain(
      "Waiting-list Wave 2 diagnostics were requested, but the canonical UAT manifest is missing waiting_list_lifecycle invite or seating prerequisites.",
    );
    expect(config.issues).toContain(
      "Account benefits diagnostics were requested, but the canonical UAT manifest is missing benefits reservation, voucher, or loyalty prerequisites.",
    );
  });
});
