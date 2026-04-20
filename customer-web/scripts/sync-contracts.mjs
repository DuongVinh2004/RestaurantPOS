import { copyFileSync, existsSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const appRoot = resolve(__dirname, "..");
const repoRoot = resolve(appRoot, "..");
const targetDir = resolve(appRoot, "src/lib/contracts/generated");
const requiredArtifacts = [
  {
    label: "Frozen OpenAPI",
    source: resolve(repoRoot, "storage/app/booking_release/openapi-v1.json"),
  },
  {
    label: "Mutation contract matrix",
    source: resolve(repoRoot, "build/api-consumer/mutation-contracts.md"),
  },
  {
    label: "TypeScript SDK",
    source: resolve(repoRoot, "build/api-consumer/sdk/typescript/restaurantpos-sdk.ts"),
    target: resolve(targetDir, "restaurantpos-sdk.ts"),
  },
  {
    label: "TypeScript enums",
    source: resolve(repoRoot, "build/api-consumer/sdk/typescript/restaurantpos-enums.ts"),
    target: resolve(targetDir, "restaurantpos-enums.ts"),
  },
];

mkdirSync(targetDir, { recursive: true });

const missingArtifacts = requiredArtifacts.filter(({ source }) => !existsSync(source));

if (missingArtifacts.length > 0) {
  const details = missingArtifacts
    .map(({ label, source }) => `- ${label}: ${source}`)
    .join("\n");

  throw new Error(
    [
      "Missing customer-web contract artifacts:",
      details,
      "",
      "Run `composer api:artifacts` from the repository root, then rerun `npm run sync:contracts`.",
    ].join("\n"),
  );
}

for (const artifact of requiredArtifacts) {
  if (!artifact.target) {
    continue;
  }

  copyFileSync(artifact.source, artifact.target);
}

console.log("Synced customer-web SDK artifacts from build/api-consumer.");
