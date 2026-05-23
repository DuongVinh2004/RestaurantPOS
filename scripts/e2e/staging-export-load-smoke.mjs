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
  };
  if (token) {
    headers["X-Staff-Key"] = token;
    headers["Authorization"] = `Bearer ${token}`;
  }
  if (method !== "GET" && method !== "DELETE") {
    headers["Content-Type"] = "application/json";
    headers["Idempotency-Key"] = "smoke-load-" + method.toLowerCase() + "-" + Math.floor(Math.random() * 100000000);
  }
  
  const options = {
    method,
    headers,
    signal: AbortSignal.timeout(30000), // 30s timeout for high volume
  };
  if (body) {
    options.body = typeof body === "string" ? body : JSON.stringify(body);
  }

  const startTime = performance.now();
  const response = await fetch(`${baseUrl}${endpoint}`, options);
  const text = await response.text();
  const endTime = performance.now();
  
  let data = null;
  try {
    data = JSON.parse(text);
  } catch (e) {
    // raw text/CSV
  }
  return { ok: response.ok, status: response.status, data, text, contentType: response.headers.get("content-type"), durationMs: endTime - startTime };
}

function parseCSV(csvText) {
  if (!csvText) return { headers: [], rowsCount: 0 };
  const lines = csvText.split(/\r?\n/).filter(line => line.trim() !== "");
  if (lines.length === 0) return { headers: [], rowsCount: 0 };
  const headers = lines[0].split(",").map(header => header.replace(/^["']|["']$/g, "").trim());
  return { headers, rowsCount: lines.length - 1 };
}

async function run() {
  console.log("Starting BATCH 15 High-Volume Export Load Smoke Verification...");

  // 1. Staff Auth
  let staffToken = null;
  const staffIdentifier = "bootstrap-admin";
  const staffPassword = "password";
  try {
    const res = await request("POST", "/auth/staff/login", {
      identifier: staffIdentifier,
      password: staffPassword,
      device_name: "e2e-load-smoke"
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

  const loadExports = [
    {
      name: "High-Volume Accounting Export (CSV)",
      endpoint: "/staff/finance/accounting-export?format=csv&limit=1000",
      expectedHeaders: ["reservation_id"],
      expectedContentType: "text/csv"
    },
    {
      name: "High-Volume Reconciliation Export (CSV)",
      endpoint: "/staff/finance/reconciliation/export?format=csv&limit=1000",
      expectedHeaders: ["reservation_id"],
      expectedContentType: "text/csv"
    },
    {
      name: "Staging Admin Menu Items Export (CSV)",
      endpoint: "/admin/menu/items/export?format=csv",
      expectedHeaders: ["code", "name"],
      expectedContentType: "text/csv"
    }
  ];

  for (const exp of loadExports) {
    try {
      const res = await request("GET", exp.endpoint, null, staffToken);
      if (!res.ok) {
        fail(exp.name, `Request failed: ${res.status}`);
        continue;
      }

      const contentTypeOk = res.contentType && res.contentType.toLowerCase().includes(exp.expectedContentType.toLowerCase());
      if (!contentTypeOk) {
        fail(`${exp.name} Content-Type`, `Expected ${exp.expectedContentType}, got ${res.contentType}`);
        continue;
      }

      const { headers, rowsCount } = parseCSV(res.text);
      const hasExpectedHeader = exp.expectedHeaders.every(h => headers.includes(h));
      const durationSec = (res.durationMs / 1000).toFixed(3);

      if (hasExpectedHeader) {
        pass(exp.name, `Export complete. Rows: ${rowsCount}, Size: ${res.text.length} bytes, Duration: ${durationSec}s. Columns: [${headers.join(", ")}].`);
      } else {
        fail(exp.name, `Export complete but headers mismatch. Found: [${headers.join(", ")}]`);
      }
    } catch (e) {
      fail(exp.name, `Error: ${e.message}`);
    }
  }

  // 3. Save verification results
  const overall_status = criticalFailedCount > 0 ? "FAIL" : "PASS";
  const outputData = {
    overall_status,
    checked_at_utc: new Date().toISOString(),
    steps: results
  };

  const resultsPath = path.resolve(repoRoot, "storage", "app", "booking_release", "staging_export_load_result.json");
  fs.writeFileSync(resultsPath, JSON.stringify(outputData, null, 2));

  const mdPath = path.resolve(repoRoot, "docs", "runbooks", "staging-export-load-result.md");
  let md = "# High-Volume Export Load E2E Smoke Results\n\n";
  md += `- **Overall Status**: ${overall_status}\n\n`;
  md += "| Step | Status | Detail |\n";
  md += "|---|---|---|\n";
  for (const r of results) {
    md += `| ${r.step} | ${r.status} | ${r.detail} |\n`;
  }
  fs.writeFileSync(mdPath, md);

  console.log(`Done. High-Volume Export Smoke Status: ${overall_status}`);
}

run();
