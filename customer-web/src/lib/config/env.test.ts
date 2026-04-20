import { describe, expect, it } from "vitest";
import { getCustomerWebRollout, getFeatureFlags } from "./feature-flags";
import { getApiBaseUrlRuntimeDiagnostics, readPublicEnv, readPublicEnvDiagnostics } from "./env";

describe("customer-web env parsing", () => {
  it("defaults wave 1 on, wave 2 off, and dev mocks off", () => {
    const env = readPublicEnv({});
    const rollout = getCustomerWebRollout(env);
    const flags = getFeatureFlags(env, rollout);

    expect(env.apiBaseUrl).toBe("http://127.0.0.1:8000");
    expect(flags.devMocks).toBe(false);
    expect(flags.preorder).toBe(false);
    expect(flags.menuItemDetail).toBe(true);
    expect(flags.tableHolds).toBe(true);
    expect(flags.waitingList).toBe(false);
    expect(flags.accountBenefits).toBe(false);
    expect(flags.privacyTools).toBe(false);
    expect(flags.dataExport).toBe(false);
    expect(rollout.waitingList.enabled).toBe(false);
    expect(rollout.accountBenefits.enabled).toBe(false);
  });

  it("accepts preferred rollout flag overrides without widening support beyond the matrix", () => {
    const env = readPublicEnv({
      NEXT_PUBLIC_ENABLE_DEV_MOCKS: "true",
      NEXT_PUBLIC_FEATURE_PREORDER: "true",
      NEXT_PUBLIC_FEATURE_WAITING_LIST: "true",
      NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS: "true",
      NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS: "1",
      NEXT_PUBLIC_FEATURE_DATA_EXPORT: "yes",
    });
    const rollout = getCustomerWebRollout(env);
    const flags = getFeatureFlags(env, rollout);

    expect(flags.devMocks).toBe(true);
    expect(flags.preorder).toBe(true);
    expect(flags.waitingList).toBe(true);
    expect(flags.accountBenefits).toBe(true);
    expect(flags.privacyTools).toBe(true);
    expect(flags.dataExport).toBe(true);
    expect(rollout.devMockAdapter.localUatOnly).toBe(true);
    expect(rollout.dataExport.liveConditional).toBe(true);
  });

  it("keeps data export disabled in the UI until privacy tools are also enabled", () => {
    const env = readPublicEnv({
      NEXT_PUBLIC_FEATURE_DATA_EXPORT: "true",
    });
    const rollout = getCustomerWebRollout(env);
    const flags = getFeatureFlags(env, rollout);

    expect(rollout.dataExport.enabled).toBe(true);
    expect(rollout.privacyRequests.enabled).toBe(false);
    expect(flags.dataExport).toBe(false);
  });

  it("keeps legacy aliases working while exposing alias diagnostics for cleanup", () => {
    const env = readPublicEnv({
      NEXT_PUBLIC_ENABLE_WAITING_LIST: "true",
      NEXT_PUBLIC_FEATURE_VOUCHERS: "true",
      NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS: "true",
    });
    const diagnostics = readPublicEnvDiagnostics({
      NEXT_PUBLIC_ENABLE_WAITING_LIST: "true",
      NEXT_PUBLIC_FEATURE_VOUCHERS: "true",
      NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS: "true",
    });
    const rollout = getCustomerWebRollout(env);
    const flags = getFeatureFlags(env, rollout);

    expect(flags.waitingList).toBe(true);
    expect(flags.accountBenefits).toBe(true);
    expect(flags.privacyTools).toBe(true);
    expect(diagnostics.waitingList.usedAlias).toBe(true);
    expect(diagnostics.accountBenefits.usedAlias).toBe(true);
    expect(diagnostics.privacyTools.usedAlias).toBe(true);
    expect(diagnostics.rolloutFlagsUsingAliases).toEqual([
      "NEXT_PUBLIC_ENABLE_WAITING_LIST",
      "NEXT_PUBLIC_FEATURE_VOUCHERS",
      "NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS",
    ]);
  });

  it("forces dev-only toggles off in production", () => {
    const env = readPublicEnv({
      NODE_ENV: "production",
      NEXT_PUBLIC_ENABLE_DEV_MOCKS: "true",
      NEXT_PUBLIC_SHOW_DEV_BACKEND_STATUS: "true",
    });
    const rollout = getCustomerWebRollout(env);
    const flags = getFeatureFlags(env, rollout);

    expect(flags.devMocks).toBe(false);
    expect(flags.showDevBackendStatus).toBe(false);
  });

  it("flags a local API base URL as suspicious when the app host is not local", () => {
    const diagnostics = getApiBaseUrlRuntimeDiagnostics("http://127.0.0.1:8000", "uat.customer-web.example");

    expect(diagnostics.apiLooksLocal).toBe(true);
    expect(diagnostics.appLooksLocal).toBe(false);
    expect(diagnostics.likelyWrongForCurrentHost).toBe(true);
  });
});
