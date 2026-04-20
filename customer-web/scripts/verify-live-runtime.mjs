import path from "node:path";
import process from "node:process";
import { spawnSync } from "node:child_process";
import { existsSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const DEFAULT_MAX_MANIFEST_AGE_MINUTES = 360;
const FUTURE_CLOCK_SKEW_MS = 5 * 60 * 1000;

function boolEnv(env, name) {
  return ["1", "true", "yes", "on"].includes((env[name] ?? "").toLowerCase());
}

function positiveNumber(value) {
  const number = Number(value);

  return Number.isFinite(number) && number > 0 ? number : Number.NaN;
}

function readMaxManifestAgeMinutes(env) {
  const configured = positiveNumber(env.CUSTOMER_WEB_LIVE_MAX_MANIFEST_AGE_MINUTES);

  return Number.isFinite(configured) ? configured : DEFAULT_MAX_MANIFEST_AGE_MINUTES;
}

function normalizeBaseUrl(value) {
  try {
    const url = new URL(value);
    url.pathname = url.pathname.replace(/\/+$/, "");
    url.search = "";
    url.hash = "";

    return url.toString().replace(/\/+$/, "");
  } catch {
    return String(value ?? "").replace(/\/+$/, "");
  }
}

function isManifestReadable(manifestStatus) {
  return manifestStatus.exists && !manifestStatus.error && manifestStatus.data;
}

function classifyDepositPaymentProof({ requested, hasData, providerCode }) {
  if (!hasData) {
    return requested ? "enabled-missing-data" : "missing-data";
  }

  if (providerCode.toLowerCase() === "simulated") {
    return "simulated-local-uat";
  }

  return requested ? "enabled-runtime-support" : "runtime-prerequisites-present";
}

function classifyBillPaymentProof({ requested, hasData }) {
  if (!hasData) {
    return requested ? "enabled-missing-data" : "missing-data";
  }

  return requested ? "enabled-runtime-support" : "runtime-prerequisites-present";
}

export function resolveManifestStatus({
  manifestPath = path.resolve(scriptDirectory, "..", "..", "storage", "app", "uat", "scenario-pack.json"),
} = {}) {
  if (!existsSync(manifestPath)) {
    return {
      path: manifestPath,
      exists: false,
      data: null,
      error: null,
    };
  }

  try {
    return {
      path: manifestPath,
      exists: true,
      data: JSON.parse(readFileSync(manifestPath, "utf8")),
      error: null,
    };
  } catch (error) {
    return {
      path: manifestPath,
      exists: true,
      data: null,
      error: error instanceof Error ? error.message : String(error),
    };
  }
}

export function createLiveRuntimeConfig({
  env = process.env,
  manifestStatus = resolveManifestStatus(),
  now = new Date(),
} = {}) {
  const appBaseUrl =
    env.CUSTOMER_WEB_LIVE_BASE_URL ??
    `http://${env.CUSTOMER_WEB_LIVE_APP_HOST ?? "127.0.0.1"}:${env.CUSTOMER_WEB_LIVE_APP_PORT ?? "3000"}`;
  const apiBaseUrl =
    env.NEXT_PUBLIC_API_BASE_URL ??
    env.CUSTOMER_WEB_LIVE_API_BASE_URL ??
    "http://127.0.0.1:8000";
  const issues = [];
  const manifestData = isManifestReadable(manifestStatus) ? manifestStatus.data : null;
  const maxManifestAgeMinutes = readMaxManifestAgeMinutes(env);
  const exerciseDepositPaymentSession = boolEnv(env, "CUSTOMER_WEB_LIVE_EXERCISE_DEPOSIT_PAYMENT_SESSION");
  const exerciseBillPaymentSession = boolEnv(env, "CUSTOMER_WEB_LIVE_EXERCISE_BILL_PAYMENT_SESSION");
  const exerciseWaitingList = boolEnv(env, "CUSTOMER_WEB_LIVE_EXERCISE_WAITING_LIST");
  const exerciseAccountBenefits = boolEnv(env, "CUSTOMER_WEB_LIVE_EXERCISE_ACCOUNT_BENEFITS");
  const exercisePrivacyTools = boolEnv(env, "CUSTOMER_WEB_LIVE_EXERCISE_PRIVACY_TOOLS");
  const exerciseDataExport = boolEnv(env, "CUSTOMER_WEB_LIVE_EXERCISE_DATA_EXPORT");

  if (!manifestStatus.exists) {
    issues.push(`Canonical UAT manifest not found at ${manifestStatus.path}. Run npm run dev:all or powershell -ExecutionPolicy Bypass -File ..\\scripts\\uat\\Bootstrap-UatPack.ps1 first.`);
  } else if (manifestStatus.error) {
    issues.push(`Canonical UAT manifest at ${manifestStatus.path} could not be parsed: ${manifestStatus.error}`);
  } else if (!manifestStatus.data?.auth?.customer_primary?.username || !manifestStatus.data?.auth?.customer_primary?.password) {
    issues.push(`Canonical UAT manifest at ${manifestStatus.path} is missing auth.customer_primary credentials. Refresh the pack before running live verification.`);
  }

  if (manifestData) {
    const generatedAtRaw = manifestData.pack?.generated_at_utc;
    const generatedAtMs = generatedAtRaw ? Date.parse(generatedAtRaw) : Number.NaN;

    if (!generatedAtRaw || Number.isNaN(generatedAtMs)) {
      issues.push(`Canonical UAT manifest at ${manifestStatus.path} is missing pack.generated_at_utc. Refresh the pack before running live verification.`);
    } else {
      const manifestAgeMinutes = Math.floor((now.getTime() - generatedAtMs) / 60_000);

      if (generatedAtMs - now.getTime() > FUTURE_CLOCK_SKEW_MS) {
        issues.push(`Canonical UAT manifest at ${manifestStatus.path} was generated in the future (${generatedAtRaw}). Check the runtime clock and refresh the pack.`);
      } else if (manifestAgeMinutes > maxManifestAgeMinutes) {
        issues.push(`Canonical UAT manifest at ${manifestStatus.path} was generated at ${generatedAtRaw}, older than ${maxManifestAgeMinutes} minutes. Refresh the pack before running live verification.`);
      }
    }

    const manifestBaseUrl = typeof manifestData.pack?.base_url === "string" ? manifestData.pack.base_url.trim() : "";

    if (manifestBaseUrl !== "" && normalizeBaseUrl(manifestBaseUrl) !== normalizeBaseUrl(apiBaseUrl)) {
      issues.push(`Canonical UAT manifest at ${manifestStatus.path} was generated for ${manifestBaseUrl}, but live verification is targeting ${apiBaseUrl}. Refresh the pack for the target API base URL.`);
    }
  }

  if (manifestData && !manifestData.auth?.staff?.api_key) {
    issues.push(`Canonical UAT manifest at ${manifestStatus.path} is missing auth.staff.api_key. Live bill proof requires the canonical UAT staff key.`);
  }

  const dineInMenuItems = manifestData?.scenarios?.dine_in_checkout?.menu_item_ids ?? null;
  const depositReservationId = manifestData ? Number(manifestData.reservations?.deposit_pending?.reservation_id) : Number.NaN;
  const depositPaymentAmount = manifestData ? Number(manifestData.scenarios?.deposit_self_pay?.payment_amount) : Number.NaN;
  const depositProviderCode = manifestData ? String(manifestData.scenarios?.deposit_self_pay?.provider_code ?? "").trim() : "";
  const dineInReservationId = manifestData ? Number(manifestData.reservations?.dine_in_checkin?.reservation_id) : Number.NaN;
  const dineInReservationRowVersion = manifestData ? Number(manifestData.reservations?.dine_in_checkin?.row_version) : Number.NaN;
  const dineInTableId = manifestData ? Number(manifestData.scenarios?.dine_in_checkout?.table_id) : Number.NaN;
  const waitingListSeededId = manifestData ? Number(manifestData.waiting_list?.seeded_waiting_entry?.waiting_id) : Number.NaN;
  const waitingListScenario = manifestData?.scenarios?.waiting_list_lifecycle ?? null;
  const waitingListBranchId = Number(waitingListScenario?.branch_id);
  const waitingListCustomerUserId = Number(waitingListScenario?.customer_user_id);
  const waitingListTableId = Number(waitingListScenario?.table_id);
  const benefitsReservationId = manifestData ? Number(manifestData.reservations?.benefits_pending?.reservation_id) : Number.NaN;
  const benefitsUserVoucherId = manifestData ? Number(manifestData.scenarios?.benefits?.user_voucher_id) : Number.NaN;
  const benefitsLoyaltyPoints = manifestData ? Number(manifestData.scenarios?.benefits?.loyalty_points) : Number.NaN;

  if (manifestData && (!Array.isArray(dineInMenuItems) || dineInMenuItems.length === 0)) {
    issues.push(`Canonical UAT manifest at ${manifestStatus.path} is missing scenarios.dine_in_checkout.menu_item_ids. Refresh the pack before running live verification.`);
  }

  if (manifestData && (!Number.isInteger(depositReservationId) || depositReservationId <= 0)) {
    issues.push(`Canonical UAT manifest at ${manifestStatus.path} is missing reservations.deposit_pending.reservation_id. Refresh the pack before running live verification.`);
  }

  if (manifestData && (!Number.isFinite(depositPaymentAmount) || depositPaymentAmount <= 0)) {
    issues.push(`Canonical UAT manifest at ${manifestStatus.path} is missing scenarios.deposit_self_pay.payment_amount. Refresh the pack before running live verification.`);
  }

  if (manifestData && depositProviderCode === "") {
    issues.push(`Canonical UAT manifest at ${manifestStatus.path} is missing scenarios.deposit_self_pay.provider_code. Refresh the pack before running live verification.`);
  }

  if (manifestData && (!Number.isInteger(dineInReservationId) || dineInReservationId <= 0)) {
    issues.push(`Canonical UAT manifest at ${manifestStatus.path} is missing reservations.dine_in_checkin.reservation_id. Refresh the pack before running live verification.`);
  }

  if (manifestData && (!Number.isInteger(dineInReservationRowVersion) || dineInReservationRowVersion <= 0)) {
    issues.push(`Canonical UAT manifest at ${manifestStatus.path} is missing reservations.dine_in_checkin.row_version. Refresh the pack before running live verification.`);
  }

  if (manifestData && (!Number.isInteger(dineInTableId) || dineInTableId <= 0)) {
    issues.push(`Canonical UAT manifest at ${manifestStatus.path} is missing scenarios.dine_in_checkout.table_id. Refresh the pack before running live verification.`);
  }

  const hasDepositPrerequisites =
    Number.isInteger(depositReservationId) &&
    depositReservationId > 0 &&
    Number.isFinite(depositPaymentAmount) &&
    depositPaymentAmount > 0 &&
    depositProviderCode !== "";
  const hasBillPrerequisites =
    Boolean(manifestData?.auth?.staff?.api_key) &&
    Array.isArray(dineInMenuItems) &&
    dineInMenuItems.length > 0 &&
    Number.isInteger(dineInReservationId) &&
    dineInReservationId > 0 &&
    Number.isInteger(dineInReservationRowVersion) &&
    dineInReservationRowVersion > 0 &&
    Number.isInteger(dineInTableId) &&
    dineInTableId > 0;
  const hasWaitingListPrerequisites =
    Number.isInteger(waitingListSeededId) &&
    waitingListSeededId > 0 &&
    Number.isInteger(waitingListBranchId) &&
    waitingListBranchId > 0 &&
    Number.isInteger(waitingListCustomerUserId) &&
    waitingListCustomerUserId > 0 &&
    Number.isInteger(waitingListTableId) &&
    waitingListTableId > 0;
  const hasBenefitsPrerequisites =
    Number.isInteger(benefitsReservationId) &&
    benefitsReservationId > 0 &&
    Number.isInteger(benefitsUserVoucherId) &&
    benefitsUserVoucherId > 0 &&
    Number.isFinite(benefitsLoyaltyPoints) &&
    benefitsLoyaltyPoints > 0;

  if (manifestData && exerciseDepositPaymentSession && !hasDepositPrerequisites) {
    issues.push("Deposit payment-session live proof was requested, but the canonical UAT manifest is missing deposit_self_pay prerequisites.");
  }

  if (manifestData && exerciseBillPaymentSession && !hasBillPrerequisites) {
    issues.push("Bill payment-session live proof was requested, but the canonical UAT manifest is missing dine-in checkout prerequisites. Positive bill proof requires a seeded dine-in active order path, not fake success.");
  }

  if (manifestData && exerciseWaitingList && !hasWaitingListPrerequisites) {
    issues.push("Waiting-list Wave 2 diagnostics were requested, but the canonical UAT manifest is missing waiting_list_lifecycle invite or seating prerequisites.");
  }

  if (manifestData && exerciseAccountBenefits && !hasBenefitsPrerequisites) {
    issues.push("Account benefits diagnostics were requested, but the canonical UAT manifest is missing benefits reservation, voucher, or loyalty prerequisites.");
  }

  if (exerciseWaitingList && !boolEnv(env, "NEXT_PUBLIC_FEATURE_WAITING_LIST")) {
    issues.push("Waiting-list live proof was requested, but NEXT_PUBLIC_FEATURE_WAITING_LIST is not enabled for the customer-web runtime.");
  }

  if (exerciseAccountBenefits && !boolEnv(env, "NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS")) {
    issues.push("Account benefits live proof was requested, but NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS is not enabled for the customer-web runtime.");
  }

  if (exercisePrivacyTools && !boolEnv(env, "NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS")) {
    issues.push("Privacy tools live proof was requested, but NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS is not enabled for the customer-web runtime.");
  }

  if (exerciseDataExport && !boolEnv(env, "NEXT_PUBLIC_FEATURE_DATA_EXPORT")) {
    issues.push("Data export live proof was requested, but NEXT_PUBLIC_FEATURE_DATA_EXPORT is not enabled for the customer-web runtime.");
  }

  const proof = {
    depositPaymentSession: {
      requested: exerciseDepositPaymentSession,
      status: classifyDepositPaymentProof({
        requested: exerciseDepositPaymentSession,
        hasData: hasDepositPrerequisites,
        providerCode: depositProviderCode,
      }),
      providerCode: depositProviderCode || null,
    },
    billPaymentSession: {
      requested: exerciseBillPaymentSession,
      status: classifyBillPaymentProof({
        requested: exerciseBillPaymentSession,
        hasData: hasBillPrerequisites,
      }),
    },
    waitingList: {
      requested: exerciseWaitingList,
      status: !hasWaitingListPrerequisites
        ? exerciseWaitingList
          ? "enabled-missing-data"
          : "missing-data"
        : exerciseWaitingList
          ? "enabled-runtime-prerequisites-present"
          : "runtime-prerequisites-present",
    },
    accountBenefits: {
      requested: exerciseAccountBenefits,
      status: !hasBenefitsPrerequisites
        ? exerciseAccountBenefits
          ? "enabled-missing-data"
          : "missing-data"
        : exerciseAccountBenefits
          ? "enabled-runtime-prerequisites-present"
          : "runtime-prerequisites-present",
    },
    privacyTools: {
      requested: exercisePrivacyTools,
      status: exercisePrivacyTools ? "enabled-runtime-prerequisites-present" : "runtime-prerequisites-present",
    },
    dataExport: {
      requested: exerciseDataExport,
      status: exerciseDataExport ? "enabled-runtime-prerequisites-present" : "runtime-prerequisites-present",
    },
  };

  if (!env.CUSTOMER_WEB_LIVE_IDENTIFIER) {
    issues.push("CUSTOMER_WEB_LIVE_IDENTIFIER is required for live verification.");
  }

  if (!env.CUSTOMER_WEB_LIVE_PASSWORD) {
    issues.push("CUSTOMER_WEB_LIVE_PASSWORD is required for live verification.");
  }

  if ((env.NEXT_PUBLIC_ENABLE_DEV_MOCKS ?? "").toLowerCase() === "true") {
    issues.push("Live verification requires NEXT_PUBLIC_ENABLE_DEV_MOCKS=false so the browser cannot fall back to mock adapters.");
  }

  if ((env.CUSTOMER_WEB_LIVE_E2E_ALLOW_SKIP ?? "").toLowerCase() === "true") {
    issues.push("CUSTOMER_WEB_LIVE_E2E_ALLOW_SKIP is not supported by npm run test:e2e:live or npm run verify:release:live. Use npm run verify:release for the CI-safe lane.");
  }

  return {
    appBaseUrl,
    apiBaseUrl,
    healthUrl: `${apiBaseUrl.replace(/\/+$/, "")}/api/v1/health`,
    appHealthUrl: `${appBaseUrl.replace(/\/+$/, "")}/login`,
    identifier: env.CUSTOMER_WEB_LIVE_IDENTIFIER ?? "",
    password: env.CUSTOMER_WEB_LIVE_PASSWORD ?? "",
    manifestStatus,
    proof,
    issues,
  };
}

function resolvePowerShellCommand() {
  return process.platform === "win32" ? "powershell" : "pwsh";
}

function runPowerShellScript(scriptPath, args = []) {
  const result = spawnSync(resolvePowerShellCommand(), [
    "-ExecutionPolicy",
    "Bypass",
    "-File",
    scriptPath,
    ...args,
  ], {
    cwd: path.resolve(scriptDirectory, "..", ".."),
    encoding: "utf8",
  });

  if (result.error) {
    throw result.error;
  }

  if (result.status !== 0) {
    const detail = [result.stdout, result.stderr].filter(Boolean).join("\n").trim();
    throw new Error(detail || `${path.basename(scriptPath)} exited with code ${result.status ?? "unknown"}.`);
  }
}

function refreshCanonicalUatPack(apiBaseUrl) {
  const repoRoot = path.resolve(scriptDirectory, "..", "..");
  const resetScript = path.resolve(repoRoot, "scripts", "uat", "Reset-UatPack.ps1");
  const bootstrapScript = path.resolve(repoRoot, "scripts", "uat", "Bootstrap-UatPack.ps1");

  runPowerShellScript(resetScript);
  runPowerShellScript(bootstrapScript, ["-BaseUrl", apiBaseUrl]);
}

async function verifyHealth(healthUrl) {
  const response = await fetch(healthUrl, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "customer-web-live-preflight",
    },
    signal: AbortSignal.timeout(8_000),
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(`${healthUrl} returned HTTP ${response.status}${text ? `: ${text.slice(0, 300)}` : ""}`);
  }
}

async function verifyFrontend(appHealthUrl) {
  const response = await fetch(appHealthUrl, {
    headers: {
      Accept: "text/html",
      "X-Requested-With": "customer-web-live-preflight",
    },
    signal: AbortSignal.timeout(8_000),
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(`${appHealthUrl} returned HTTP ${response.status}${text ? `: ${text.slice(0, 300)}` : ""}`);
  }
}

async function runCli() {
  let config = createLiveRuntimeConfig();

  if (config.issues.length > 0) {
    for (const issue of config.issues) {
      process.stderr.write(`${issue}\n`);
    }
    process.stderr.write("Live verification preflight failed. Use npm run verify:release for CI-safe checks, or satisfy the live prerequisites and retry.\n");
    process.exit(1);
  }

  try {
    await verifyHealth(config.healthUrl);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    process.stderr.write(`Live backend health check failed: ${message}\n`);
    process.stderr.write("This is a live-only gate. Start the Laravel runtime and refresh the UAT pack before retrying.\n");
    process.exit(1);
  }

  try {
    refreshCanonicalUatPack(config.apiBaseUrl);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    process.stderr.write(`Canonical UAT pack refresh failed: ${message}\n`);
    process.stderr.write("Live verification requires a clean canonical UAT pack. Fix the bootstrap failure and retry.\n");
    process.exit(1);
  }

  config = createLiveRuntimeConfig();

  if (config.issues.length > 0) {
    for (const issue of config.issues) {
      process.stderr.write(`${issue}\n`);
    }
    process.stderr.write("Canonical UAT pack refresh completed, but refreshed proof prerequisites are still invalid.\n");
    process.exit(1);
  }

  try {
    await verifyFrontend(config.appHealthUrl);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    process.stderr.write(`Live customer-web check failed: ${message}\n`);
    process.stderr.write(`Start customer-web on ${config.appBaseUrl} before running the live gate. npm run test:e2e:live does not start the app for you.\n`);
    process.exit(1);
  }

  process.stdout.write(
    `Live proof preflight summary: deposit-payment=${config.proof.depositPaymentSession.status}; bill-payment=${config.proof.billPaymentSession.status}; waiting-list=${config.proof.waitingList.status}; account-benefits=${config.proof.accountBenefits.status}; privacy-tools=${config.proof.privacyTools.status}; data-export=${config.proof.dataExport.status}\n`,
  );
  process.stdout.write(`Live runtime preflight passed for ${config.healthUrl} and ${config.appHealthUrl}\n`);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  runCli().catch((error) => {
    const message = error instanceof Error ? error.stack ?? error.message : String(error);
    process.stderr.write(`${message}\n`);
    process.exit(1);
  });
}
