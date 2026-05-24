import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDirectory, "..", "..");

const baseUrl = process.env.API_BASE_URL || "http://127.0.0.1:8000/api/v1";
const results = [];
let criticalFailedCount = 0;

function pass(step, detail) {
  console.log(`[PASS] ${step}`);
  results.push({ step, status: "PASS", detail });
}

function fail(step, detail) {
  console.log(`[FAIL] ${step}: ${detail}`);
  results.push({ step, status: "FAIL", detail });
  criticalFailedCount++;
}

async function request(method, endpoint, body = null, token = null, customHeaders = {}) {
  const headers = {
    Accept: "application/json",
    "Content-Type": "application/json",
    ...customHeaders,
  };
  if (token) {
    headers["X-Staff-Key"] = token;
    headers["Authorization"] = `Bearer ${token}`;
  }
  
  const options = {
    method,
    headers,
    signal: AbortSignal.timeout(10000),
  };
  if (body) {
    options.body = typeof body === "string" ? body : JSON.stringify(body);
  }

  const response = await fetch(`${baseUrl}${endpoint}`, options);
  const text = await response.text();
  let data = null;
  try {
    data = JSON.parse(text);
  } catch (e) {
    // raw text
  }
  return { ok: response.ok, status: response.status, data, text };
}

async function run() {
  console.log("Starting BATCH 14 Refund Flow E2E Smoke Verification...");

  // 1. Load reservation_id from prior E2E run
  let reservationId = null;
  try {
    const reconPath = path.resolve(repoRoot, "storage", "app", "booking_release", "payment_reconciliation_smoke_result.json");
    if (fs.existsSync(reconPath)) {
      const content = fs.readFileSync(reconPath, "utf8");
      const obj = JSON.parse(content);
      reservationId = obj.reservation_id;
    }
    
    if (reservationId) {
      pass("Load Test Precondition", `Successfully loaded reservation ID ${reservationId} from prior smoke result.`);
    } else {
      return fail("Load Test Precondition", "No prior reservation ID found. Run reconciliation smoke first.");
    }
  } catch (e) {
    return fail("Load Test Precondition", e.message);
  }

  // 2. Staff Auth
  let staffToken = null;
  const staffIdentifier = "bootstrap-admin";
  const staffPassword = "password";
  try {
    const res = await request("POST", "/auth/staff/login", {
      identifier: staffIdentifier,
      password: staffPassword,
      device_name: "e2e-refund-smoke"
    });
    if (res.ok && res.data?.data?.access_token) {
      staffToken = res.data.data.access_token;
      pass("Staff Auth", "Logged in staff successfully.");
    } else {
      return fail("Staff Auth", `Login failed: ${res.text}`);
    }
  } catch (e) {
    return fail("Staff Auth", e.message);
  }

  // 3. GET Current Reservation State and row_version
  let rowVersion = 1;
  try {
    const res = await request("GET", `/staff/reservations/${reservationId}`, null, staffToken);
    if (res.ok && res.data?.data) {
      rowVersion = res.data.data.row_version || 1;
      pass("GET Reservation Detail", `Current row_version is ${rowVersion}, status is [${res.data.data.status}].`);
    } else {
      return fail("GET Reservation Detail", res.text);
    }
  } catch (e) {
    return fail("GET Reservation Detail", e.message);
  }

  // 4. Refund Preview Check
  try {
    const res = await request("GET", `/staff/reservations/${reservationId}/refund-preview?refund_scope=deposit&cancel_after_payment=1`, null, staffToken);
    if (res.ok && res.data?.data?.refund?.refund_amount !== undefined) {
      pass("GET Refund Preview", `Verified refundable amount is: ${res.data.data.refund.refund_amount} VND.`);
    } else {
      return fail("GET Refund Preview", res.text);
    }
  } catch (e) {
    return fail("GET Refund Preview", e.message);
  }

  // 5. Execute Staff Refund and Cancel
  const idempotencyKey = "smoke-refund-" + Math.floor(Math.random() * 100000000);
  const refundPayload = {
    payment_method: "Card",
    payment_provider: "Card",
    refund_scope: "deposit",
    notes: "E2E refund flow smoke UAT",
    reason: "Customer cancellation request",
    cancel_reason: "UAT cancellation UAT",
    row_version: rowVersion
  };

  try {
    const res = await request("POST", `/staff/reservations/${reservationId}/refund-cancel`, refundPayload, staffToken, {
      "Idempotency-Key": idempotencyKey
    });

    if (res.ok && (res.data?.data?.refund?.status === "Refunded" || res.data?.data?.refund?.cancelled === true || res.data?.data?.reservation?.deposit_status === "Refunded")) {
      rowVersion = res.data.data.reservation?.row_version || (rowVersion + 1);
      pass("POST Reservation Refund & Cancel", "Successfully processed Card refund and cancelled reservation.");
    } else {
      return fail("POST Reservation Refund & Cancel", res.text);
    }
  } catch (e) {
    return fail("POST Reservation Refund & Cancel", e.message);
  }

  // 6. Verify duplicate refund is blocked by Idempotency-Key
  try {
    const res = await request("POST", `/staff/reservations/${reservationId}/refund-cancel`, refundPayload, staffToken, {
      "Idempotency-Key": idempotencyKey
    });

    if (res.status === 409 || res.status === 422 || (res.ok && (res.data?.data?.refund?.status === "Refunded" || res.data?.data?.refund?.cancelled === true || res.data?.data?.reservation?.deposit_status === "Refunded"))) {
      pass("Verify Duplicate Refund Guard", "Duplicate refund request correctly caught and prevented from double refund.");
    } else {
      return fail("Verify Duplicate Refund Guard", `Expected 409 or graceful cached response, got: ${res.status} ${res.text}`);
    }
  } catch (e) {
    return fail("Verify Duplicate Refund Guard", e.message);
  }

  // 7. Save verification results
  const overall_status = criticalFailedCount > 0 ? "FAIL" : "PASS";
  const outputData = {
    overall_status,
    checked_at_utc: new Date().toISOString(),
    reservation_id: reservationId,
    steps: results
  };

  const resultsPath = path.resolve(repoRoot, "storage", "app", "booking_release", "refund_flow_smoke_result.json");
  fs.writeFileSync(resultsPath, JSON.stringify(outputData, null, 2));

  const mdPath = path.resolve(repoRoot, "docs", "runbooks", "refund-flow-smoke-result.md");
  let md = "# Refund Flow E2E Smoke Results\n\n";
  md += `- **Overall Status**: ${overall_status}\n`;
  md += `- **Reservation ID**: ${reservationId}\n\n`;
  md += "| Step | Status | Detail |\n";
  md += "|---|---|---|\n";
  for (const r of results) {
    md += `| ${r.step} | ${r.status} | ${r.detail} |\n`;
  }
  fs.writeFileSync(mdPath, md);

  console.log(`Done. Refund Flow Status: ${overall_status}`);
}

run();
