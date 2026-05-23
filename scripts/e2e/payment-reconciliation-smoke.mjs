import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import crypto from "node:crypto";
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

function getSimulatedWebhookSecret() {
  try {
    const envPath = path.resolve(repoRoot, ".env");
    if (fs.existsSync(envPath)) {
      const content = fs.readFileSync(envPath, "utf8");
      const match = content.match(/^PAYMENT_PROVIDER_SIMULATED_WEBHOOK_SECRET\s*=\s*(.*)$/m);
      if (match && match[1]) {
        return match[1].trim();
      }
    }
  } catch (e) {
    // fallback
  }
  return "local-simulated-webhook-secret";
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
  console.log("Starting BATCH 14 Payment Reconciliation E2E Smoke Verification...");
  
  let customerToken = null;
  const customerIdentifier = "ms.customer-03";
  const customerPassword = "password";

  // 1. Auth Customer
  try {
    const res = await request("POST", "/auth/customer/login", {
      identifier: customerIdentifier,
      password: customerPassword,
      session_label: "e2e-payment-recon-smoke"
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

  // 2. Available Tables Lookup
  const UATnow = new Date();
  UATnow.setMilliseconds(0);
  const fromTime = new Date(UATnow.getTime() + 7200000).toISOString();
  const toTime = new Date(UATnow.getTime() + 10800000).toISOString();
  const sessionId = "smoke-recon-sess-" + Math.floor(Math.random() * 1000000);

  let tables = [];
  try {
    const res = await request("GET", `/tables/available?from=${encodeURIComponent(fromTime)}&to=${encodeURIComponent(toTime)}&party_size=5`, null, customerToken);
    if (res.ok) {
      tables = res.data?.data || [];
      pass("GET Available Tables", `Found ${tables.length} tables.`);
    } else {
      return fail("GET Available Tables", res.text);
    }
  } catch (e) {
    return fail("GET Available Tables", e.message);
  }

  // 3. Table Hold Creation
  let holdId = null;
  try {
    if (tables.length > 0) {
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
      const res = await request("POST", "/table-holds", {
        session_id: sessionId,
        start_time: fromTime,
        end_time: toTime,
        table_ids: tableIds,
        branch_id: branchId
      }, customerToken);

      if (res.ok) {
        holdId = res.data?.data?.hold_id;
        pass("POST Table Hold", `Table hold ${holdId} created.`);
      } else {
        return fail("POST Table Hold", res.text);
      }
    } else {
      return fail("POST Table Hold", "No tables available.");
    }
  } catch (e) {
    return fail("POST Table Hold", e.message);
  }

  // 4. Reservation Creation
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
      pass("POST Reservation", `Reservation ${reservationId} created.`);
    } else {
      return fail("POST Reservation", res.text);
    }
  } catch (e) {
    return fail("POST Reservation", e.message);
  }

  // 5. Deposit Intent & Payment Session Creation
  let providerSessionCode = null;
  let sessionRowVersion = 1;
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

    // Create payment session
    const paySessRes = await request("POST", `/reservations/${reservationId}/deposit/payment-sessions`, {
      payment_method: "Simulated",
      payment_provider: "Simulated",
      row_version: rowVersion
    }, customerToken);

    if (paySessRes.ok && paySessRes.data?.data?.payment_session) {
      providerSessionCode = paySessRes.data.data.payment_session.provider_session_code;
      sessionRowVersion = paySessRes.data.data.payment_session.row_version || 1;
      pass("POST Payment Session", `Deposit session created: ${providerSessionCode}`);
    } else {
      return fail("POST Payment Session", paySessRes.text);
    }
  } catch (e) {
    return fail("POST Payment Session", e.message);
  }

  // 6. Simulate Signed Webhook Callback (Succeeded)
  const webhookSecret = getSimulatedWebhookSecret();
  const webhookPayload = {
    provider_session_code: providerSessionCode,
    payment_scope: "deposit",
    simulation_outcome: "succeeded"
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
    const webhookRes = await fetch(`${baseUrl}/payments/providers/simulated/webhooks`, {
      method: "POST",
      headers: webhookHeaders,
      body: rawBody
    });
    const webhookText = await webhookRes.text();
    const webhookData = JSON.parse(webhookText);

    if (webhookRes.ok && webhookData?.data?.delivery_status === "Applied") {
      pass("POST Webhook Callback", "Simulated callback ingested and applied successfully.");
    } else {
      return fail("POST Webhook Callback", `Status: ${webhookRes.status}, Body: ${webhookText}`);
    }
  } catch (e) {
    return fail("POST Webhook Callback", e.message);
  }

  // 7. Verify Deposit Status updated to Paid
  try {
    const res = await request("GET", `/reservations/${reservationId}/deposit-preview`, null, customerToken);
    const depositStatus = res.data?.data?.deposit?.status;
    if (res.ok && depositStatus === "Paid") {
      pass("Verify Reconciliation Status", `Reservation deposit status successfully reconciled to [${depositStatus}].`);
    } else {
      return fail("Verify Reconciliation Status", `Expected deposit Paid, got: ${depositStatus}. Detail: ${res.text}`);
    }
  } catch (e) {
    return fail("Verify Reconciliation Status", e.message);
  }

  // 8. Replay Webhook Callback to verify Idempotency
  try {
    const webhookHeaders = {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Payment-Signature": signature,
      "X-Payment-Timestamp": timestamp
    };
    const webhookRes = await fetch(`${baseUrl}/payments/providers/simulated/webhooks`, {
      method: "POST",
      headers: webhookHeaders,
      body: rawBody
    });
    const webhookText = await webhookRes.text();
    const webhookData = JSON.parse(webhookText);

    if (webhookRes.ok && webhookData?.data?.duplicate === true) {
      pass("Verify Idempotency Guard", "Double-webhook invocation correctly flagged as duplicate and did not duplicate charge.");
    } else {
      return fail("Verify Idempotency Guard", `Expected duplicate=true, got: ${webhookText}`);
    }
  } catch (e) {
    return fail("Verify Idempotency Guard", e.message);
  }

  // 9. Save verification results
  const overall_status = criticalFailedCount > 0 ? "FAIL" : "PASS";
  const outputData = {
    overall_status,
    checked_at_utc: new Date().toISOString(),
    reservation_id: reservationId,
    provider_session_code: providerSessionCode,
    steps: results
  };

  const resultsPath = path.resolve(repoRoot, "storage", "app", "booking_release", "payment_reconciliation_smoke_result.json");
  fs.writeFileSync(resultsPath, JSON.stringify(outputData, null, 2));

  const mdPath = path.resolve(repoRoot, "docs", "runbooks", "payment-reconciliation-smoke-result.md");
  let md = "# Payment Reconciliation E2E Smoke Results\n\n";
  md += `- **Overall Status**: ${overall_status}\n`;
  md += `- **Reservation ID**: ${reservationId}\n`;
  md += `- **Session Code**: ${providerSessionCode}\n\n`;
  md += "| Step | Status | Detail |\n";
  md += "|---|---|---|\n";
  for (const r of results) {
    md += `| ${r.step} | ${r.status} | ${r.detail} |\n`;
  }
  fs.writeFileSync(mdPath, md);

  console.log(`Done. Reconciliation Status: ${overall_status}`);
}

run();
