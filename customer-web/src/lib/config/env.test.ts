import { afterEach, describe, expect, it } from "vitest";
import { readPublicEnv, readPublicEnvDiagnostics } from "./env";

const mutableEnv = process.env as Record<string, string | undefined>;
const originalPreorder = mutableEnv.NEXT_PUBLIC_FEATURE_PREORDER;
const originalWaitingList = mutableEnv.NEXT_PUBLIC_FEATURE_WAITING_LIST;
const originalAccountBenefits = mutableEnv.NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS;
const originalPrivacyTools = mutableEnv.NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS;
const originalDataExport = mutableEnv.NEXT_PUBLIC_FEATURE_DATA_EXPORT;
const originalNodeEnv = mutableEnv.NODE_ENV;

function restoreEnv(key: string, originalValue: string | undefined): void {
  if (originalValue === undefined) {
    delete mutableEnv[key];
  } else {
    mutableEnv[key] = originalValue;
  }
}

describe("public env", () => {
  afterEach(() => {
    restoreEnv("NEXT_PUBLIC_FEATURE_PREORDER", originalPreorder);
    restoreEnv("NEXT_PUBLIC_FEATURE_WAITING_LIST", originalWaitingList);
    restoreEnv("NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS", originalAccountBenefits);
    restoreEnv("NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS", originalPrivacyTools);
    restoreEnv("NEXT_PUBLIC_FEATURE_DATA_EXPORT", originalDataExport);
    restoreEnv("NODE_ENV", originalNodeEnv);
  });

  it("reads preorder rollout from direct NEXT_PUBLIC env values", () => {
    mutableEnv.NODE_ENV = "production";
    mutableEnv.NEXT_PUBLIC_FEATURE_PREORDER = "true";

    expect(readPublicEnv().enablePreorder).toBe(true);
    expect(readPublicEnvDiagnostics().preorder.value).toBe(true);
    expect(readPublicEnvDiagnostics().preorder.sourceKey).toBe(
      "NEXT_PUBLIC_FEATURE_PREORDER",
    );
  });

  it("enables preorder by default for local development", () => {
    mutableEnv.NODE_ENV = "development";
    delete mutableEnv.NEXT_PUBLIC_FEATURE_PREORDER;

    const env = readPublicEnv();

    expect(env.enablePreorder).toBe(true);
    expect(readPublicEnvDiagnostics().preorder.value).toBe(true);
    expect(readPublicEnvDiagnostics().preorder.sourceKey).toBe("default");
  });

  it("keeps rollout-only features off by default in production", () => {
    mutableEnv.NODE_ENV = "production";
    delete mutableEnv.NEXT_PUBLIC_FEATURE_PREORDER;
    delete mutableEnv.NEXT_PUBLIC_FEATURE_WAITING_LIST;
    delete mutableEnv.NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS;
    delete mutableEnv.NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS;
    delete mutableEnv.NEXT_PUBLIC_FEATURE_DATA_EXPORT;

    const env = readPublicEnv();

    expect(env.enablePreorder).toBe(false);
    expect(env.enableWaitingList).toBe(false);
    expect(env.enableAccountBenefits).toBe(false);
    expect(env.enablePrivacyTools).toBe(false);
    expect(env.enableDataExport).toBe(false);
  });
});
