import { afterEach, describe, expect, it } from "vitest";
import { readPublicEnv, readPublicEnvDiagnostics } from "./env";

const mutableEnv = process.env as Record<string, string | undefined>;
const originalPreorder = mutableEnv.NEXT_PUBLIC_FEATURE_PREORDER;
const originalWaitingList = mutableEnv.NEXT_PUBLIC_FEATURE_WAITING_LIST;
const originalNodeEnv = mutableEnv.NODE_ENV;

describe("public env", () => {
  afterEach(() => {
    if (originalPreorder === undefined) {
      delete mutableEnv.NEXT_PUBLIC_FEATURE_PREORDER;
    } else {
      mutableEnv.NEXT_PUBLIC_FEATURE_PREORDER = originalPreorder;
    }

    if (originalWaitingList === undefined) {
      delete mutableEnv.NEXT_PUBLIC_FEATURE_WAITING_LIST;
    } else {
      mutableEnv.NEXT_PUBLIC_FEATURE_WAITING_LIST = originalWaitingList;
    }

    if (originalNodeEnv === undefined) {
      delete mutableEnv.NODE_ENV;
    } else {
      mutableEnv.NODE_ENV = originalNodeEnv;
    }
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

    const env = readPublicEnv();

    expect(env.enablePreorder).toBe(false);
    expect(env.enableWaitingList).toBe(false);
  });
});
