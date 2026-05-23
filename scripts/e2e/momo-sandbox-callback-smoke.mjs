import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import crypto from "node:crypto";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDirectory, "..", "..");

const baseUrl = process.env.API_BASE_URL || "http://127.0.0.1:8000/api/v1";
const results = [];
let isBlocked = false;

function pass(step, detail) {
  console.log(`[PASS] ${step}`);
  results.push({ step, status: "PASS", detail });
}

function block(step, reason) {
  console.log(`[STAGING_BLOCKED] ${step}: ${reason}`);
  results.push({ step, status: "STAGING_BLOCKED", detail: reason });
  isBlocked = true;
}

function fail(step, detail) {
  console.log(`[FAIL] ${step}: ${detail}`);
  results.push({ step, status: "FAIL", detail });
}

function getGenericHmacWebhookSecret() {
  try {
    const envPath = path.resolve(repoRoot, ".env");
    if (fs.existsSync(envPath)) {
      const content = fs.readFileSync(envPath, "utf8");
      const match = content.match(/^PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET\s*=\s*(.*)$/m);
      if (match && match[1]) {
        return match[1].trim();
      }
    }
  } catch (e) {
    // fallback
  }
  return "";
}

async function request(method, endpoint, body = null, token = null) {
  const headers = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };
  if (token) {
    if (endpoint.startsWith("/staff/") || endpoint.startsWith("/admin/")) {
      headers["X-Staff-Key"] = token;
      headers["Authorization"] = `Bearer ${token}`;
    } else {
      headers["X-Customer-Token"] = token;
    }
  }
  if (method !== "GET" && method !== "DELETE") {
    headers["Idempotency-Key"] = "smoke-momo-" + method.toLowerCase() + "-" + Math.floor(Math.random() * 100000000);
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
  console.log("Starting BATCH 15 MoMo Sandbox Callback Smoke Verification...");
  
  let customerToken = null;
  const customerIdentifier = "ms.customer-03";
  const customerPassword = "password";

  // 1. Auth Customer
  try {
    const res = await request("POST", "/auth/customer/login", {
      identifier: customerIdentifier,
      password: customerPassword,
      session_label: "e2e-momo-smoke"
    });
    if (res.ok && res.data?.data?.access_token) {
      customerToken = res.data.data.access_token;
      pass("Customer Auth", "Logged in customer successfully.");
    } else {
      return fail("Customer Auth", `Login failed: ${res.text}`);
    }
  } catch (e) {
    return fail("Customer Auth", e.message);
  }

  // 2. Pre-Check generic_http_hmac enablement
  let selfPayStatus = null;
  try {
    const res = await request("GET", "/reservations/1/deposit-preview", null, customerToken);
    selfPayStatus = res.data?.meta?.payment_providers?.providers?.generic_http_hmac;
  } catch (e) {
    // ignore
  }

  const webhookSecret = getGenericHmacWebhookSecret();
  if (webhookSecret === "") {
    block("MoMo Sandbox Ingestion Gate", "PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET is empty in .env. Webhook calls remain staging-blocked.");
    
    // Save blocked metadata
    const outputData = {
      overall_status: "STAGING_BLOCKED",
      checked_at_utc: new Date().toISOString(),
      reason: "PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET is empty.",
      steps: results
    };
    fs.writeFileSync(path.resolve(repoRoot, "storage", "app", "booking_release", "momo_sandbox_callback_result.json"), JSON.stringify(outputData, null, 2));

    let md = "# MoMo Sandbox Callback E2E Results\n\n";
    md += `- **Overall Status**: STAGING_BLOCKED\n`;
    md += `- **Reason**: PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET is empty in .env.\n\n`;
    md += "| Step | Status | Detail |\n";
    md += "|---|---|---|\n";
    for (const r of results) {
      md += `| ${r.step} | ${r.status} | ${r.detail} |\n`;
    }
    fs.writeFileSync(path.resolve(repoRoot, "docs/runbooks/momo-sandbox-callback-result.md"), md);
    return;
  }

  // 3. Table and reservation hold creation for dynamic check
  const UATnow = new Date();
  const fromTime = new Date(UATnow.getTime() + 7200000).toISOString();
  const toTime = new Date(UATnow.getTime() + 10800000).toISOString();
  const sessionId = "smoke-momo-sess-" + Math.floor(Math.random() * 1000000);

  let tables = [];
  try {
    const res = await request("GET", `/tables/available?from=${encodeURIComponent(fromTime)}&to=${encodeURIComponent(toTime)}&party_size=5`, null, customerToken);
    if (res.ok) {
      tables = res.data?.data || [];
    }
  } catch (e) {}

  if (tables.length === 0) {
    block("GET Tables", "No tables available for UAT branch.");
    return;
  }

  let selectedTables = [];
  let totalCapacity = 0;
  for (const t of tables) {
    selectedTables.push(t);
    const seats = t.seats || t.capacity || 4;
    totalCapacity += seats;
    if (totalCapacity >= 5) break;
  }
  const tableIds = selectedTables.map(t => t.table_id);
  const branchId = selectedTables[0].branch_id;

  let holdId = null;
  try {
    const res = await request("POST", "/table-holds", {
      session_id: sessionId,
      start_time: fromTime,
      end_time: toTime,
      table_ids: tableIds,
      branch_id: branchId
    }, customerToken);
    if (res.ok) holdId = res.data?.data?.hold_id;
  } catch (e) {}

  let reservationId = null;
  let rowVersion = 1;
  try {
    const res = await request("POST", "/reservations", {
      hold_id: holdId,
      session_id: sessionId,
      start_time: fromTime,
      end_time: toTime,
      guest_count: 5
    }, customerToken);
    if (res.ok) {
      reservationId = res.data?.data?.reservation_id;
      rowVersion = res.data?.data?.row_version || 1;
    }
  } catch (e) {}

  // Create payment session under generic_http_hmac
  let providerSessionCode = null;
  try {
    // Acknowledge deposit
    const ackRes = await request("POST", `/reservations/${reservationId}/deposit/acknowledge`, { row_version: rowVersion }, customerToken);
    if (ackRes.ok) {
      rowVersion = ackRes.data?.data?.row_version || ackRes.data?.data?.reservation?.row_version || (rowVersion + 1);
    }
    // Submit intent
    const intentRes = await request("POST", `/reservations/${reservationId}/deposit/intent`, { row_version: rowVersion }, customerToken);
    if (intentRes.ok) {
      rowVersion = intentRes.data?.data?.row_version || intentRes.data?.data?.reservation?.row_version || (rowVersion + 1);
    }
    // Create session
    const paySessRes = await request("POST", `/reservations/${reservationId}/deposit/payment-sessions`, {
      payment_method: "Online",
      payment_provider: "generic_http_hmac",
      row_version: rowVersion
    }, customerToken);

    if (paySessRes.ok && paySessRes.data?.data?.payment_session) {
      providerSessionCode = paySessRes.data.data.payment_session.provider_session_code;
      pass("Create Generic Session", `Session created: ${providerSessionCode}`);
    } else {
      return block("Create Generic Session", `generic_http_hmac session creation skipped or unsupported: ${paySessRes.text}`);
    }
  } catch (e) {
    return block("Create Generic Session", e.message);
  }

  // 4. Simulate Generic Webhook Signed Callback
  const webhookPayload = {
    provider_session_code: providerSessionCode,
    payment_scope: "deposit",
    session_status: "Succeeded",
    event_type: "payment.session.completed",
    provider_event_code: "momo-smoke-evt-" + Math.floor(Math.random() * 1000000)
  };
  const rawBody = JSON.stringify(webhookPayload);
  const signature = crypto.createHmac("sha256", webhookSecret).update(rawBody).digest("hex");
  const timestamp = new Date().toISOString();

  try {
    const webhookHeaders = {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Payment-Signature": signature,
      "X-Payment-Timestamp": timestamp
    };
    const webhookRes = await fetch(`${baseUrl}/payments/providers/generic_http_hmac/webhooks`, {
      method: "POST",
      headers: webhookHeaders,
      body: rawBody
    });
    const webhookText = await webhookRes.text();

    if (webhookRes.ok) {
      pass("Simulate Webhook Ingestion", "Generic HTTP HMAC signature webhook applied successfully.");
    } else {
      fail("Simulate Webhook Ingestion", `Status: ${webhookRes.status}, Body: ${webhookText}`);
    }
  } catch (e) {
    fail("Simulate Webhook Ingestion", e.message);
  }

  // Save verification results
  const overall_status = results.some(r => r.status === "FAIL") ? "FAIL" : "PASS";
  const outputData = {
    overall_status,
    checked_at_utc: new Date().toISOString(),
    reservation_id: reservationId,
    provider_session_code: providerSessionCode,
    steps: results
  };

  const resultsPath = path.resolve(repoRoot, "storage", "app", "booking_release", "momo_sandbox_callback_result.json");
  fs.writeFileSync(resultsPath, JSON.stringify(outputData, null, 2));

  const mdPath = path.resolve(repoRoot, "docs", "runbooks", "momo-sandbox-callback-result.md");
  let md = "# MoMo Sandbox Callback E2E Results\n\n";
  md += `- **Overall Status**: ${overall_status}\n`;
  md += `- **Reservation ID**: ${reservationId}\n`;
  md += `- **Session Code**: ${providerSessionCode}\n\n`;
  md += "| Step | Status | Detail |\n";
  md += "|---|---|---|\n";
  for (const r of results) {
    md += `| ${r.step} | ${r.status} | ${r.detail} |\n`;
  }
  fs.writeFileSync(mdPath, md);

  console.log(`Done. MoMo Callback Status: ${overall_status}`);
}

run();
