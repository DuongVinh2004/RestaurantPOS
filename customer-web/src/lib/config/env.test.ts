import { describe, expect, it } from "vitest";
import { getFeatureFlags } from "./feature-flags";
import { readPublicEnv } from "./env";

describe("customer-web env parsing", () => {
  it("defaults supported customer flows on and dev mocks off", () => {
    const env = readPublicEnv({});
    const flags = getFeatureFlags(env);

    expect(env.apiBaseUrl).toBe("http://127.0.0.1:8000");
    expect(flags.devMocks).toBe(false);
    expect(flags.menuItemDetail).toBe(true);
    expect(flags.tableHolds).toBe(true);
    expect(flags.waitingList).toBe(true);
    expect(flags.privacyTools).toBe(true);
  });

  it("accepts explicit rollout flag overrides", () => {
    const flags = getFeatureFlags(
      readPublicEnv({
        NEXT_PUBLIC_ENABLE_DEV_MOCKS: "true",
        NEXT_PUBLIC_ENABLE_WAITING_LIST: "false",
        NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS: "0",
        NEXT_PUBLIC_FEATURE_DATA_EXPORT: "yes",
      }),
    );

    expect(flags.devMocks).toBe(true);
    expect(flags.waitingList).toBe(false);
    expect(flags.privacyTools).toBe(false);
    expect(flags.dataExport).toBe(true);
  });
});
