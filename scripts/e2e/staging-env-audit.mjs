import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDirectory, "..", "..");

// Environment groups to check
const envDefinitions = {
  "core runtime": [
    { key: "APP_ENV", critical: true },
    { key: "APP_KEY", critical: true },
    { key: "APP_URL", critical: true }
  ],
  "database": [
    { key: "DB_CONNECTION", critical: true },
    { key: "DB_HOST", critical: true },
    { key: "DB_PORT", critical: true },
    { key: "DB_DATABASE", critical: true },
    { key: "DB_USERNAME", critical: true },
    { key: "DB_PASSWORD", critical: true }
  ],
  "redis": [
    { key: "REDIS_HOST", critical: true },
    { key: "REDIS_PORT", critical: true }
  ],
  "queue": [
    { key: "QUEUE_CONNECTION", critical: true }
  ],
  "scheduler": [
    { key: "SCHEDULER_HEARTBEAT_TTL_SECONDS", critical: true }
  ],
  "MoMo": [
    { key: "PAYMENT_PROVIDER_MOMO_PARTNER_CODE", critical: false },
    { key: "PAYMENT_PROVIDER_MOMO_ACCESS_KEY", critical: false },
    { key: "PAYMENT_PROVIDER_MOMO_SECRET_KEY", critical: false },
    { key: "PAYMENT_PROVIDER_MOMO_API_URL", critical: false }
  ],
  "VNPay": [
    { key: "PAYMENT_PROVIDER_VNPAY_TMN_CODE", critical: false },
    { key: "PAYMENT_PROVIDER_VNPAY_HASH_SECRET", critical: false },
    { key: "PAYMENT_PROVIDER_VNPAY_PAY_URL", critical: false }
  ],
  "webhook URL": [
    { key: "PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET", critical: false },
    { key: "PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_SIGNING_SECRET", critical: false }
  ],
  "staff/customer auth": [
    { key: "STAFF_API_KEY", critical: true },
    { key: "CUSTOMER_AUTH_JWT_SECRET", critical: true }
  ]
};

function loadMergedEnv() {
  const envVars = { ...process.env };
  const envPath = path.resolve(repoRoot, ".env");
  if (fs.existsSync(envPath)) {
    try {
      const content = fs.readFileSync(envPath, "utf8");
      const lines = content.split(/\r?\n/);
      for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith("#")) continue;
        const index = trimmed.indexOf("=");
        if (index > 0) {
          const key = trimmed.substring(0, index).trim();
          let value = trimmed.substring(index + 1).trim();
          // strip quotes if any
          if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
            value = value.substring(1, value.length - 1);
          }
          if (key && !envVars[key]) {
            envVars[key] = value;
          }
        }
      }
    } catch (e) {
      console.warn("Could not read .env file:", e.message);
    }
  }
  return envVars;
}

function runAudit() {
  console.log("=========================================");
  console.log("RESTAURANT POS - STAGING ENV AUDIT");
  console.log("=========================================");

  const allowMissingProviderEnv = process.argv.includes("--allow-missing-provider-env");
  const envVars = loadMergedEnv();
  const auditReport = {};
  let criticalFail = false;
  let providerFail = false;

  for (const [groupName, keys] of Object.entries(envDefinitions)) {
    console.log(`\nGroup: ${groupName.toUpperCase()}`);
    auditReport[groupName] = [];

    for (const item of keys) {
      const value = envVars[item.key];
      const present = typeof value === "string" && value.trim() !== "";
      const status = present ? "PRESENT" : "MISSING";
      const isCritical = item.critical;

      console.log(`  - ${item.key}: [${status}] (Critical: ${isCritical})`);
      auditReport[groupName].push({
        key: item.key,
        status,
        critical: isCritical
      });

      if (!present) {
        if (isCritical) {
          criticalFail = true;
        } else {
          providerFail = true;
        }
      }
    }
  }

  // Determine overall readiness
  let verdict = "READY";
  if (criticalFail) {
    verdict = "CRITICAL_MISSING";
  } else if (providerFail) {
    verdict = "PROVIDER_MISSING";
  }

  console.log("\n=========================================");
  console.log(`AUDIT VERDICT: ${verdict}`);
  console.log("=========================================");

  // Prepare storage folder
  const storageDir = path.resolve(repoRoot, "storage", "app", "booking_release");
  if (!fs.existsSync(storageDir)) {
    fs.mkdirSync(storageDir, { recursive: true });
  }

  const jsonReport = {
    verdict,
    allow_missing_provider_env: allowMissingProviderEnv,
    audited_at_utc: new Date().toISOString(),
    details: auditReport
  };

  fs.writeFileSync(
    path.resolve(storageDir, "batch17_staging_env_audit.json"),
    JSON.stringify(jsonReport, null, 2)
  );

  // Generate markdown report
  let md = `# Batch 17 — Staging Environment & Secret Audit\n\n`;
  md += `This document reports the status (PRESENT/MISSING) of critical environment variables for **Batch 17 - Real Staging Infrastructure Setup Checklist & Execution Prep**.\n\n`;
  md += `## 1. Audit Executive Summary\n`;
  md += `- **Overall Verdict**: **${verdict}**\n`;
  md += `- **Evidence Checked UTC**: \`${new Date().toISOString()}\`\n`;
  md += `- **Allow Missing Provider Env**: \`${allowMissingProviderEnv}\`\n\n`;
  md += `> [!IMPORTANT]\n`;
  md += `> This report only shows variable **presence** (PRESENT/MISSING). Secret values are never printed, logged, or checked in plain text.\n\n`;
  md += `## 2. Env Presence Breakdown\n\n`;

  for (const [groupName, items] of Object.entries(auditReport)) {
    md += `### ${groupName.toUpperCase()}\n`;
    md += `| Environment Variable | Status | Critical |\n`;
    md += `|---|---|---|\n`;
    for (const item of items) {
      md += `| \`${item.key}\` | **${item.status}** | \`${item.critical}\` |\n`;
    }
    md += `\n`;
  }

  fs.writeFileSync(path.resolve(repoRoot, "docs/runbooks/batch17-staging-env-audit.md"), md);
  console.log("Saved JSON audit report to storage/app/booking_release/batch17_staging_env_audit.json");
  console.log("Saved Markdown audit report to docs/runbooks/batch17-staging-env-audit.md");

  // Determine exit code
  if (criticalFail) {
    console.error("Audit failed: One or more critical core infrastructure env keys are missing!");
    process.exit(1);
  }

  if (providerFail && !allowMissingProviderEnv) {
    console.error("Audit failed: One or more provider sandbox env keys are missing (strict mode enforced)!");
    process.exit(1);
  }

  console.log("Environment audit passed successfully.");
  process.exit(0);
}

runAudit();
