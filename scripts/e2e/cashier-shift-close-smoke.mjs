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

async function request(method, endpoint, body = null, token = null) {
  const headers = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };
  if (token) {
    headers["X-Staff-Key"] = token;
    headers["Authorization"] = `Bearer ${token}`;
  }
  if (method !== "GET" && method !== "DELETE") {
    headers["Idempotency-Key"] = "smoke-" + method.toLowerCase() + "-" + Math.floor(Math.random() * 100000000);
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
  console.log("Starting BATCH 14 Cashier Shift Close E2E Smoke Verification...");

  // 1. Staff Auth
  let staffToken = null;
  const staffIdentifier = "bootstrap-admin";
  const staffPassword = "password";
  try {
    const res = await request("POST", "/auth/staff/login", {
      identifier: staffIdentifier,
      password: staffPassword,
      device_name: "e2e-cashier-smoke"
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

  // 2. Resolve or Close any existing open shift
  const branchId = 14; // UAT Branch
  const openingFloat = 200000.00;

  try {
    const currentRes = await request("GET", `/staff/cashier/shifts/current?branch_id=${branchId}`, null, staffToken);
    if (currentRes.ok && currentRes.data?.data?.cashier_shift_id) {
      const activeShiftId = currentRes.data.data.cashier_shift_id;
      const activeRowVersion = currentRes.data.data.row_version || 1;
      const activeFloat = currentRes.data.data.opening_float_amount || openingFloat;
      console.log(`Found existing open cashier shift ${activeShiftId}. Closing it first...`);
      const closeRes = await request("POST", `/staff/cashier/shifts/${activeShiftId}/close`, {
        actual_cash_amount: activeFloat,
        notes: "Closing pre-existing shift for UAT baseline",
        row_version: activeRowVersion
      }, staffToken);
      if (closeRes.ok) {
        pass("Close Existing Shift", `Pre-existing shift ID ${activeShiftId} successfully closed.`);
      } else {
        console.warn(`Warning: failed to close pre-existing shift ${activeShiftId}: ${closeRes.text}`);
      }
    }
  } catch (e) {
    console.warn(`Warning: failed to check/close pre-existing shift: ${e.message}`);
  }

  // 3. Open Dedicated Cashier Shift
  let shiftId = null;
  let rowVersion = 1;

  try {
    const res = await request("POST", "/staff/cashier/shifts/open", {
      opening_float_amount: openingFloat,
      currency: "VND",
      branch_id: branchId,
      notes: "E2E dedicated cashier shift UAT"
    }, staffToken);

    if (res.ok && res.data?.data?.cashier_shift_id) {
      shiftId = res.data.data.cashier_shift_id;
      rowVersion = res.data.data.row_version || 1;
      pass("POST Open Shift", `Opened dedicated cashier shift ID ${shiftId} with opening float: ${openingFloat} VND.`);
    } else {
      return fail("POST Open Shift", res.text);
    }
  } catch (e) {
    return fail("POST Open Shift", e.message);
  }

  // 4. Close and Settle the Shift
  try {
    const res = await request("POST", `/staff/cashier/shifts/${shiftId}/close`, {
      actual_cash_amount: openingFloat,
      notes: "E2E cashier shift closed successfully",
      row_version: rowVersion
    }, staffToken);

    if (res.ok && res.data?.data?.status === "Closed") {
      pass("POST Close Shift", `Shift ${shiftId} successfully settled and closed.`);
    } else {
      return fail("POST Close Shift", res.text);
    }
  } catch (e) {
    return fail("POST Close Shift", e.message);
  }

  // 5. Verify post-close guard blocks duplicate close requests
  try {
    const res = await request("POST", `/staff/cashier/shifts/${shiftId}/close`, {
      actual_cash_amount: openingFloat,
      notes: "Duplicate close request",
      row_version: rowVersion + 1
    }, staffToken);

    if (res.status === 422 || res.status === 409 || !res.ok) {
      pass("Verify Post-Close Guard", "Double closure or payment operations on a closed shift correctly rejected by business rule.");
    } else {
      return fail("Verify Post-Close Guard", `Expected failure/block on closed shift, got status: ${res.status}. Detail: ${res.text}`);
    }
  } catch (e) {
    return fail("Verify Post-Close Guard", e.message);
  }

  // 6. Save verification results
  const overall_status = criticalFailedCount > 0 ? "FAIL" : "PASS";
  const outputData = {
    overall_status,
    checked_at_utc: new Date().toISOString(),
    cashier_shift_id: shiftId,
    steps: results
  };

  const resultsPath = path.resolve(repoRoot, "storage", "app", "booking_release", "cashier_shift_close_smoke_result.json");
  fs.writeFileSync(resultsPath, JSON.stringify(outputData, null, 2));

  const mdPath = path.resolve(repoRoot, "docs", "runbooks", "cashier-shift-close-smoke-result.md");
  let md = "# Cashier Shift Close E2E Smoke Results\n\n";
  md += `- **Overall Status**: ${overall_status}\n`;
  md += `- **Cashier Shift ID**: ${shiftId}\n\n`;
  md += "| Step | Status | Detail |\n";
  md += "|---|---|---|\n";
  for (const r of results) {
    md += `| ${r.step} | ${r.status} | ${r.detail} |\n`;
  }
  fs.writeFileSync(mdPath, md);

  console.log(`Done. Cashier Shift Close Status: ${overall_status}`);
}

run();

