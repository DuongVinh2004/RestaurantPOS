import { createHash } from "node:crypto";
import { existsSync, readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";

const scriptDirectory = dirname(fileURLToPath(import.meta.url));

const contractTruthFiles = [
  "storage/app/booking_release/openapi-v1.json",
  "build/api-consumer/sdk/typescript/restaurantpos-sdk.ts",
  "build/api-consumer/mutation-contracts.md",
  "customer-web/src/lib/config/support-matrix.ts",
];

const generatedPairs = [
  {
    label: "TypeScript SDK",
    source: "build/api-consumer/sdk/typescript/restaurantpos-sdk.ts",
    generated: "customer-web/src/lib/contracts/generated/restaurantpos-sdk.ts",
  },
  {
    label: "TypeScript enums",
    source: "build/api-consumer/sdk/typescript/restaurantpos-enums.ts",
    generated: "customer-web/src/lib/contracts/generated/restaurantpos-enums.ts",
  },
];

const provenanceLanes = [
  {
    command: "composer api:artifacts",
    note: "Frozen OpenAPI, curated consumer artifacts, and the frozen release manifest snapshot refresh together from the backend generator chain.",
    paths: [
      "storage/app/booking_release/openapi-v1.json",
      "build/api-consumer/mutation-contracts.md",
      "build/api-consumer/postman/RestaurantPOS.uat.postman_environment.json",
      "build/api-consumer/sdk/typescript/restaurantpos-sdk.ts",
      "build/api-consumer/sdk/typescript/restaurantpos-enums.ts",
      "storage/app/booking_release/release_manifest_snapshot.json",
    ],
  },
  {
    command: "npm --prefix customer-web run sync:contracts",
    note: "Customer-web vendored copies must stay byte-for-byte aligned with build/api-consumer after the backend refresh.",
    paths: [
      "customer-web/src/lib/contracts/generated/restaurantpos-sdk.ts",
      "customer-web/src/lib/contracts/generated/restaurantpos-enums.ts",
    ],
  },
];

const trackedGeneratedFiles = Array.from(
  new Set(provenanceLanes.flatMap((lane) => lane.paths)),
);

const rolloutChecks = [
  {
    id: "preorder",
    required: ['status: "live-ready"', 'exposure: "env-flag"', 'gateFlag: "enablePreorder"'],
  },
  {
    id: "waiting-list",
    required: ['status: "live-conditional"', 'exposure: "env-flag"', 'gateFlag: "enableWaitingList"'],
  },
  {
    id: "account-benefits",
    required: ['status: "live-conditional"', 'exposure: "env-flag"', 'gateFlag: "enableAccountBenefits"'],
  },
];

const sourceContractChecks = [
  {
    label: "customer API client auth/session/idempotency boundary",
    path: "customer-web/src/lib/api/sdk-client.ts",
    required: [
      "customerToken: () => getCustomerToken()",
      "customerSessionId: () => getCustomerSessionId() ?? ensureCustomerSessionId()",
      "idempotencyKey: options.idempotencyKey ?? createIdempotencyKey(scope)",
      '"X-Session-Id": sessionId',
    ],
    forbidden: [
      '"X-Idempotency-Key"',
      "'X-Idempotency-Key'",
      "idempotency_key",
    ],
  },
  {
    label: "customer deposit payment mutations",
    path: "customer-web/src/features/deposit/api.ts",
    required: [
      "postV1ReservationsIdDepositAcknowledge",
      "postV1ReservationsIdDepositIntent",
      "postV1ReservationsReservationIdDepositPaymentSessions",
      "idempotentSessionOptions(",
      "row_version: rowVersion",
      "session_id: ensureCustomerSessionId()",
    ],
    forbidden: [
      '"X-Idempotency-Key"',
      "'X-Idempotency-Key'",
      "idempotency_key",
    ],
  },
  {
    label: "customer bill payment mutations",
    path: "customer-web/src/features/billing/api.ts",
    required: [
      "postV1ReservationsReservationIdBillPaymentSessions",
      "postV1ReservationsReservationIdBillPaymentSessionsSessionIdRefresh",
      "postV1ReservationsReservationIdBillPaymentSessionsSessionIdConfirm",
      "idempotentSessionOptions(",
      "row_version: rowVersion",
      "session_id: ensureCustomerSessionId()",
    ],
    forbidden: [
      '"X-Idempotency-Key"',
      "'X-Idempotency-Key'",
      "idempotency_key",
    ],
  },
  {
    label: "customer preorder canonical route usage",
    path: "customer-web/src/features/preorder/api.ts",
    required: [
      "getV1ReservationsIdPreorder",
      "postV1ReservationsIdPreorderPreview",
      "putV1ReservationsIdPreorder",
      "deleteV1ReservationsIdPreorder",
      "idempotentSessionOptions(",
      "row_version: rowVersion",
    ],
    forbidden: [
      "pre-order",
      "PreOrder",
      '"X-Idempotency-Key"',
      "'X-Idempotency-Key'",
      "idempotency_key",
    ],
  },
  {
    label: "customer public menu preorder rollout gate",
    path: "customer-web/src/features/menu/menu-page.tsx",
    required: [
      "const canPreorder = featureFlags.preorder && item.preorder.enabled && item.is_available;",
      "disabled={!canPreorder}",
    ],
    forbidden: [],
  },
  {
    label: "customer preorder focused live proof",
    path: "customer-web/e2e/customer-preorder-live.spec.ts",
    required: [
      "NEXT_PUBLIC_FEATURE_PREORDER",
      "/api/v1/menu/preorder/preview",
      "/api/v1/reservations/${reservationId}/preorder/preview",
      "/api/v1/reservations/${reservationId}/preorder",
      "Update preorder",
      "Clear preorder",
      '"X-Staff-Key": adminApiKey',
    ],
    forbidden: [
      "pre-order",
      "PreOrder",
      "X-Staff-Api-Key",
    ],
  },
];

function sha256(path) {
  return createHash("sha256").update(readFileSync(path)).digest("hex");
}

function relativePath(root, path) {
  return path.replace(root, "").replace(/^[/\\]/, "").replace(/\\/g, "/");
}

function checkSupportMatrix({ repoRoot, issues }) {
  const supportMatrixPath = resolve(repoRoot, "customer-web/src/lib/config/support-matrix.ts");
  const supportMatrix = readFileSync(supportMatrixPath, "utf8");

  for (const check of rolloutChecks) {
    const entryStart = supportMatrix.indexOf(`id: "${check.id}"`);
    const nextEntryStart = supportMatrix.indexOf("\n  {", entryStart + 1);
    const entry = entryStart >= 0 ? supportMatrix.slice(entryStart, nextEntryStart >= 0 ? nextEntryStart : undefined) : "";

    if (!entry) {
      issues.push(`support-matrix is missing ${check.id}.`);
      continue;
    }

    for (const required of check.required) {
      if (!entry.includes(required)) {
        issues.push(`support-matrix ${check.id} must keep ${required}.`);
      }
    }
  }
}

function checkSourceContracts({ repoRoot, issues }) {
  for (const check of sourceContractChecks) {
    const absolutePath = resolve(repoRoot, check.path);
    if (!existsSync(absolutePath)) {
      issues.push(`Missing source contract check file for ${check.label}: ${check.path}`);
      continue;
    }

    const source = readFileSync(absolutePath, "utf8");
    const missing = check.required.filter((fragment) => !source.includes(fragment));
    const forbidden = check.forbidden.filter((fragment) => source.includes(fragment));

    if (missing.length > 0) {
      issues.push(`${check.label} missing required fragments: ${missing.join(", ")}`);
    }

    if (forbidden.length > 0) {
      issues.push(`${check.label} contains deprecated compatibility fragments: ${forbidden.join(", ")}`);
    }
  }
}

function gitStatus(repoRoot, paths) {
  const result = spawnSync("git", ["status", "--porcelain", "--", ...paths], {
    cwd: repoRoot,
    encoding: "utf8",
  });

  if (result.error || result.status !== 0) {
    return {
      ok: false,
      output: [result.stdout, result.stderr].filter(Boolean).join("\n").trim(),
    };
  }

  return {
    ok: true,
    output: result.stdout.trimEnd(),
  };
}

function parseGitStatusPaths(output) {
  return output
    .split(/\r?\n/)
    .map((line) => line.trimEnd())
    .filter(Boolean)
    .flatMap((line) => {
      const match = line.match(/^(?:[ MADRCU?!]{1,2})\s+(.+)$/);
      const pathSegment = match ? match[1].trim() : line.trim();

      if (!pathSegment) {
        return [];
      }

      return pathSegment
        .split(" -> ")
        .map((path) => path.replace(/\\/g, "/"))
        .filter(Boolean);
    });
}

function buildGeneratedArtifactWarning(output) {
  const changedPaths = parseGitStatusPaths(output);
  const changedLookup = new Set(changedPaths);
  const laneLines = provenanceLanes.flatMap((lane) => {
    const ownedChanges = lane.paths.filter((path) => changedLookup.has(path));

    if (ownedChanges.length === 0) {
      return [];
    }

    return [
      `- ${lane.command}:`,
      ...ownedChanges.map((path) => `  - ${path}`),
      `  - ${lane.note}`,
    ];
  });
  const unknownChanges = changedPaths.filter((path) => !trackedGeneratedFiles.includes(path));
  const lines = [
    "Generated contract artifacts have local Git changes. Confirm they came from the canonical refresh chain, not hand edits:",
    ...laneLines,
  ];

  if (unknownChanges.length > 0) {
    lines.push("- review manually:");
    lines.push(...unknownChanges.map((path) => `  - ${path}`));
  }

  lines.push("Git status:");
  lines.push(output);

  return lines.join("\n");
}

export function checkContractGovernance({
  repoRoot = resolve(scriptDirectory, "..", ".."),
  strictGenerated = false,
  gitStatusRunner = gitStatus,
} = {}) {
  const issues = [];
  const warnings = [];

  for (const file of contractTruthFiles) {
    const absolutePath = resolve(repoRoot, file);

    if (!existsSync(absolutePath)) {
      issues.push(`Missing contract truth file: ${file}`);
    }
  }

  for (const pair of generatedPairs) {
    const sourcePath = resolve(repoRoot, pair.source);
    const generatedPath = resolve(repoRoot, pair.generated);

    if (!existsSync(sourcePath) || !existsSync(generatedPath)) {
      issues.push(`Missing ${pair.label} source or generated copy.`);
      continue;
    }

    if (sha256(sourcePath) !== sha256(generatedPath)) {
      issues.push(`${pair.label} generated copy is out of sync. Run npm run sync:contracts from customer-web after refreshing backend artifacts.`);
    }
  }

  if (existsSync(resolve(repoRoot, "customer-web/src/lib/config/support-matrix.ts"))) {
    checkSupportMatrix({ repoRoot, issues });
  }

  checkSourceContracts({ repoRoot, issues });

  const generatedStatus = gitStatusRunner(repoRoot, trackedGeneratedFiles);

  if (!generatedStatus.ok) {
    warnings.push(`Could not inspect generated artifact Git status: ${generatedStatus.output || "git status failed"}`);
  } else if (generatedStatus.output) {
    const message = buildGeneratedArtifactWarning(generatedStatus.output);

    if (strictGenerated) {
      issues.push(message);
    } else {
      warnings.push(message);
    }
  }

  return {
    ok: issues.length === 0,
    issues,
    warnings,
  };
}

function runCli() {
  const strictGenerated = process.argv.includes("--strict-generated");
  const repoRoot = resolve(scriptDirectory, "..", "..");
  const result = checkContractGovernance({ repoRoot, strictGenerated });

  for (const warning of result.warnings) {
    process.stderr.write(`warning: ${warning}\n`);
  }

  if (!result.ok) {
    for (const issue of result.issues) {
      process.stderr.write(`error: ${issue}\n`);
    }

    process.exit(1);
  }

  process.stdout.write(`Contract governance check passed for ${relativePath(repoRoot, resolve(repoRoot, "customer-web"))}.\n`);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  runCli();
}
