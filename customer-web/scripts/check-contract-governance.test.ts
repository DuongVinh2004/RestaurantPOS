import { mkdirSync, writeFileSync } from "node:fs";
import { mkdtempSync, rmSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";
import { afterEach, describe, expect, it } from "vitest";
import { checkContractGovernance } from "./check-contract-governance.mjs";

const tempRoots: string[] = [];

function createRepoFixture({
  generatedSdk = "export const sdk = 1;\n",
  generatedEnums = "export const enums = 1;\n",
  supportMatrix = supportMatrixFixture(),
  gitStatusOutput = "",
} = {}) {
  const root = mkdtempSync(join(tmpdir(), "customer-web-contract-governance-"));
  tempRoots.push(root);

  writeFile(root, "storage/app/booking_release/openapi-v1.json", "{}\n");
  writeFile(root, "build/api-consumer/mutation-contracts.md", "# contracts\n");
  writeFile(root, "build/api-consumer/sdk/typescript/restaurantpos-sdk.ts", "export const sdk = 1;\n");
  writeFile(root, "build/api-consumer/sdk/typescript/restaurantpos-enums.ts", "export const enums = 1;\n");
  writeFile(root, "customer-web/src/lib/contracts/generated/restaurantpos-sdk.ts", generatedSdk);
  writeFile(root, "customer-web/src/lib/contracts/generated/restaurantpos-enums.ts", generatedEnums);
  writeFile(root, "customer-web/src/lib/config/support-matrix.ts", supportMatrix);
  writeFile(root, "customer-web/src/lib/api/sdk-client.ts", sdkClientFixture());
  writeFile(root, "customer-web/src/features/deposit/api.ts", depositApiFixture());
  writeFile(root, "customer-web/src/features/billing/api.ts", billingApiFixture());
  writeFile(root, "customer-web/src/features/preorder/api.ts", preorderApiFixture());

  return {
    root,
    gitStatusRunner: () => ({
      ok: true,
      output: gitStatusOutput,
    }),
  };
}

afterEach(() => {
  for (const root of tempRoots.splice(0)) {
    rmSync(root, { recursive: true, force: true });
  }
});

describe("checkContractGovernance", () => {
  it("passes when generated SDK files match and proven Wave 2 surfaces remain flag-gated", () => {
    const fixture = createRepoFixture();

    const result = checkContractGovernance({
      repoRoot: fixture.root,
      gitStatusRunner: fixture.gitStatusRunner,
    });

    expect(result).toEqual({
      ok: true,
      issues: [],
      warnings: [],
    });
  });

  it("fails when generated SDK files drift from build artifacts", () => {
    const fixture = createRepoFixture({
      generatedSdk: "export const sdk = 2;\n",
    });

    const result = checkContractGovernance({
      repoRoot: fixture.root,
      gitStatusRunner: fixture.gitStatusRunner,
    });

    expect(result.ok).toBe(false);
    expect(result.issues).toContain(
      "TypeScript SDK generated copy is out of sync. Run npm run sync:contracts from customer-web after refreshing backend artifacts.",
    );
  });

  it("fails when waiting-list or account benefits are opened outside the support matrix gate", () => {
    const fixture = createRepoFixture({
      supportMatrix: supportMatrixFixture().replace('exposure: "env-flag"', 'exposure: "default-on"'),
    });

    const result = checkContractGovernance({
      repoRoot: fixture.root,
      gitStatusRunner: fixture.gitStatusRunner,
    });

    expect(result.ok).toBe(false);
    expect(result.issues).toContain('support-matrix waiting-list must keep exposure: "env-flag".');
  });

  it("fails when customer mutation wrappers drift from canonical idempotent session contracts", () => {
    const fixture = createRepoFixture();
    writeFile(
      fixture.root,
      "customer-web/src/features/billing/api.ts",
      'client.postV1ReservationsReservationIdBillPaymentSessions({}, { row_version: rowVersion }, { headers: { "X-Idempotency-Key": "legacy" } });\n',
    );

    const result = checkContractGovernance({
      repoRoot: fixture.root,
      gitStatusRunner: fixture.gitStatusRunner,
    });

    expect(result.ok).toBe(false);
    expect(result.issues).toEqual(
      expect.arrayContaining([
        expect.stringContaining("customer bill payment mutations missing required fragments"),
        expect.stringContaining("customer bill payment mutations contains deprecated compatibility fragments"),
      ]),
    );
  });

  it("warns on generated artifact Git changes by default and fails in strict mode", () => {
    const fixture = createRepoFixture({
      gitStatusOutput: [
        "M build/api-consumer/sdk/typescript/restaurantpos-sdk.ts",
        " M customer-web/src/lib/contracts/generated/restaurantpos-sdk.ts",
      ].join("\n"),
    });

    const nonStrict = checkContractGovernance({
      repoRoot: fixture.root,
      gitStatusRunner: fixture.gitStatusRunner,
    });
    const strict = checkContractGovernance({
      repoRoot: fixture.root,
      strictGenerated: true,
      gitStatusRunner: fixture.gitStatusRunner,
    });

    expect(nonStrict.ok).toBe(true);
    expect(nonStrict.warnings[0]).toMatch(/Generated contract artifacts have local Git changes/);
    expect(nonStrict.warnings[0]).toContain("composer api:artifacts");
    expect(nonStrict.warnings[0]).toContain("npm --prefix customer-web run sync:contracts");
    expect(nonStrict.warnings[0]).toContain("build/api-consumer/sdk/typescript/restaurantpos-sdk.ts");
    expect(nonStrict.warnings[0]).toContain("customer-web/src/lib/contracts/generated/restaurantpos-sdk.ts");
    expect(strict.ok).toBe(false);
    expect(strict.issues[0]).toMatch(/Generated contract artifacts have local Git changes/);
  });
});

function writeFile(root: string, path: string, contents: string) {
  const absolutePath = join(root, path);
  mkdirSync(join(absolutePath, ".."), { recursive: true });
  writeFileSync(absolutePath, contents);
}

function supportMatrixFixture() {
  return `
export const customerWebSupportMatrix = [
  {
    id: "waiting-list",
    status: "live-conditional",
    exposure: "env-flag",
    gateFlag: "enableWaitingList",
  },
  {
    id: "account-benefits",
    status: "live-conditional",
    exposure: "env-flag",
    gateFlag: "enableAccountBenefits",
  },
];
`;
}

function sdkClientFixture() {
  return `
export function createApiClient() {
  return new RestaurantPosClient({
    customerToken: () => getCustomerToken(),
    customerSessionId: () => getCustomerSessionId() ?? ensureCustomerSessionId(),
  });
}

export function idempotentOptions(scope, options = {}) {
  return {
    ...options,
    idempotencyKey: options.idempotencyKey ?? createIdempotencyKey(scope),
  };
}

export function sessionOptions(options = {}) {
  const sessionId = ensureCustomerSessionId();

  return {
    ...options,
    headers: {
      ...options.headers,
      "X-Session-Id": sessionId,
    },
  };
}
`;
}

function depositApiFixture() {
  return `
client.postV1ReservationsIdDepositAcknowledge({}, { row_version: rowVersion, session_id: ensureCustomerSessionId() }, idempotentSessionOptions("deposit"));
client.postV1ReservationsIdDepositIntent({}, { row_version: rowVersion, session_id: ensureCustomerSessionId() }, idempotentSessionOptions("deposit"));
client.postV1ReservationsReservationIdDepositPaymentSessions({}, { row_version: rowVersion, session_id: ensureCustomerSessionId() }, idempotentSessionOptions("deposit"));
`;
}

function billingApiFixture() {
  return `
client.postV1ReservationsReservationIdBillPaymentSessions({}, { row_version: rowVersion, session_id: ensureCustomerSessionId() }, idempotentSessionOptions("bill"));
client.postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh({}, { row_version: rowVersion, session_id: ensureCustomerSessionId() }, idempotentSessionOptions("bill"));
client.postV1ReservationsReservationIdBillPaymentSessionsSessionIdConfirm({}, { row_version: rowVersion, session_id: ensureCustomerSessionId() }, idempotentSessionOptions("bill"));
`;
}

function preorderApiFixture() {
  return `
client.getV1ReservationsIdPreorder({});
client.postV1ReservationsIdPreorderPreview({}, {}, idempotentSessionOptions("preorder"));
client.putV1ReservationsIdPreorder({}, {}, idempotentSessionOptions("preorder"));
client.deleteV1ReservationsIdPreorder({}, { row_version: rowVersion }, idempotentSessionOptions("preorder"));
`;
}
