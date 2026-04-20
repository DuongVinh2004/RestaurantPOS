import net from "node:net";
import path from "node:path";
import process from "node:process";
import { existsSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDirectory, "..", "..");
const manifestPath = path.resolve(repoRoot, "storage", "app", "uat", "scenario-pack.json");
const steps = [];

function pass(step, detail) {
  steps.push({ step, status: "PASS", detail });
}

function fail(step, detail) {
  steps.push({ step, status: "FAIL", detail });
}

function printSummaryAndExit(exitCode) {
  process.stdout.write("Dev smoke\n");
  for (const entry of steps) {
    process.stdout.write(`${entry.status} ${entry.step}: ${entry.detail}\n`);
  }
  process.exit(exitCode);
}

function loadManifest() {
  if (!existsSync(manifestPath)) {
    throw new Error(`UAT manifest not found at ${manifestPath}. Run npm run dev:all or powershell -ExecutionPolicy Bypass -File scripts\\uat\\Bootstrap-UatPack.ps1 first.`);
  }

  return JSON.parse(readFileSync(manifestPath, "utf8"));
}

function ensureCredentials(manifest) {
  const customerIdentifier = manifest?.auth?.customer_primary?.username;
  const customerPassword = manifest?.auth?.customer_primary?.password;
  const staffIdentifier = manifest?.auth?.staff?.username;
  const staffPassword = manifest?.auth?.staff?.password;

  if (!customerIdentifier || !customerPassword || !staffIdentifier || !staffPassword) {
    throw new Error(`UAT manifest ${manifestPath} is missing customer or staff demo credentials. Refresh the pack with npm run dev:all.`);
  }

  return {
    customerIdentifier,
    customerPassword,
    staffIdentifier,
    staffPassword,
  };
}

function probePort(port, timeoutMs = 1_500) {
  return new Promise((resolve) => {
    const socket = new net.Socket();
    let settled = false;
    const recoveryHint = "Run npm run dev:all and wait for backend, customer-web, and staff-web to finish starting before retrying.";

    const finalize = (result) => {
      if (settled) {
        return;
      }

      settled = true;
      socket.destroy();
      resolve(result);
    };

    socket.setTimeout(timeoutMs);
    socket.once("connect", () => finalize({ ok: true, detail: `listening on 127.0.0.1:${port}` }));
    socket.once("timeout", () => finalize({ ok: false, detail: `timed out connecting to 127.0.0.1:${port}. ${recoveryHint}` }));
    socket.once("error", (error) =>
      finalize({
        ok: false,
        detail: `${error instanceof Error ? error.message : String(error)}. ${recoveryHint}`,
      }),
    );
    socket.connect(port, "127.0.0.1");
  });
}

async function fetchText(url, label) {
  const response = await fetch(url, {
    headers: {
      Accept: "text/html,application/json",
      "X-Requested-With": "dev-smoke",
    },
    signal: AbortSignal.timeout(5_000),
  });

  if (!response.ok) {
    throw new Error(`${label} returned HTTP ${response.status}`);
  }

  return response.text();
}

async function postJson(url, body, label) {
  const response = await fetch(url, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Requested-With": "dev-smoke",
    },
    body: JSON.stringify(body),
    signal: AbortSignal.timeout(8_000),
  });

  const text = await response.text();
  let payload = null;

  try {
    payload = JSON.parse(text);
  } catch {
    payload = null;
  }

  if (!response.ok) {
    throw new Error(`${label} returned HTTP ${response.status}${text ? `: ${text.slice(0, 300)}` : ""}`);
  }

  return payload;
}

async function run() {
  let manifest;
  let credentials;

  try {
    manifest = loadManifest();
    credentials = ensureCredentials(manifest);
    pass("uat pack", `loaded ${manifestPath}`);
  } catch (error) {
    fail("uat pack", error instanceof Error ? error.message : String(error));
    printSummaryAndExit(1);
  }

  for (const [label, port] of [
    ["backend port", 8000],
    ["customer-web port", 3000],
    ["staff-web port", 5173],
  ]) {
    const result = await probePort(port);
    if (!result.ok) {
      fail(label, result.detail);
      printSummaryAndExit(1);
    }

    pass(label, result.detail);
  }

  try {
    await fetchText("http://127.0.0.1:8000/api/v1/health", "backend health");
    pass("backend health", "http://127.0.0.1:8000/api/v1/health responded with HTTP 200");
  } catch (error) {
    fail("backend health", error instanceof Error ? error.message : String(error));
    printSummaryAndExit(1);
  }

  try {
    const html = await fetchText("http://127.0.0.1:3000/login", "customer-web login page");
    if (!html.includes("Sign in")) {
      throw new Error("customer-web login page did not include the expected sign-in copy.");
    }

    pass("customer-web page", "http://127.0.0.1:3000/login rendered the live sign-in page");
  } catch (error) {
    fail("customer-web page", error instanceof Error ? error.message : String(error));
    printSummaryAndExit(1);
  }

  try {
    await fetchText("http://127.0.0.1:5173/", "staff-web page");
    pass("staff-web page", "http://127.0.0.1:5173/ responded with HTTP 200");
  } catch (error) {
    fail("staff-web page", error instanceof Error ? error.message : String(error));
    printSummaryAndExit(1);
  }

  try {
    const payload = await postJson(
      "http://127.0.0.1:8000/api/v1/auth/customer/login",
      {
        identifier: credentials.customerIdentifier,
        password: credentials.customerPassword,
        session_label: "dev-smoke-customer",
      },
      "customer login",
    );

    const accessToken = payload?.data?.access_token;
    if (!accessToken) {
      throw new Error("customer login succeeded but did not return data.access_token.");
    }

    pass("customer login", `authenticated ${credentials.customerIdentifier}`);
  } catch (error) {
    fail("customer login", error instanceof Error ? error.message : String(error));
    printSummaryAndExit(1);
  }

  try {
    const payload = await postJson(
      "http://127.0.0.1:8000/api/v1/auth/staff/login",
      {
        identifier: credentials.staffIdentifier,
        password: credentials.staffPassword,
        device_name: "dev-smoke-staff",
      },
      "staff login",
    );

    const accessToken = payload?.data?.access_token;
    if (!accessToken) {
      throw new Error("staff login succeeded but did not return data.access_token.");
    }

    pass("staff login", `authenticated ${credentials.staffIdentifier}`);
  } catch (error) {
    fail("staff login", error instanceof Error ? error.message : String(error));
    printSummaryAndExit(1);
  }

  printSummaryAndExit(0);
}

run().catch((error) => {
  fail("dev smoke", error instanceof Error ? error.message : String(error));
  printSummaryAndExit(1);
});
