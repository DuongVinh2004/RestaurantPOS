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
    // raw text/CSV
  }
  return { ok: response.ok, status: response.status, data, text, contentType: response.headers.get("content-type") };
}

function parseCSVHeaders(csvText) {
  if (!csvText) return [];
  const lines = csvText.split(/\r?\n/);
  if (lines.length === 0) return [];
  const firstLine = lines[0];
  // Simple CSV parser for headers (split by comma, clean quotes)
  return firstLine.split(",").map(header => header.replace(/^["']|["']$/g, "").trim());
}

async function run() {
  console.log("Starting BATCH 14 CSV/Report Export Smoke Verification...");

  // 1. Staff Auth
  let staffToken = null;
  const staffIdentifier = "bootstrap-admin";
  const staffPassword = "password";
  try {
    const res = await request("POST", "/auth/staff/login", {
      identifier: staffIdentifier,
      password: staffPassword,
      device_name: "e2e-export-smoke"
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

  const exportsToVerify = [
    {
      name: "Staff Financial Accounting Export (CSV)",
      endpoint: "/staff/finance/accounting-export?format=csv&branch_id=14",
      expectedHeaders: ["reservation_id"],
      expectedContentType: "text/csv"
    },
    {
      name: "Staff Financial Accounting Export (JSON)",
      endpoint: "/staff/finance/accounting-export?format=json&branch_id=14",
      expectedContentType: "application/json"
    },
    {
      name: "Staff Financial Reconciliation Export (CSV)",
      endpoint: "/staff/finance/reconciliation/export?format=csv&branch_id=14",
      expectedHeaders: ["reservation_id"],
      expectedContentType: "text/csv"
    },
    {
      name: "Staff Financial Reconciliation Export (JSON)",
      endpoint: "/staff/finance/reconciliation/export?format=json&branch_id=14",
      expectedContentType: "application/json"
    },
    {
      name: "Admin Menu Categories Export (CSV)",
      endpoint: "/admin/menu/categories/export?format=csv",
      expectedHeaders: ["name", "description"],
      expectedContentType: "text/csv"
    },
    {
      name: "Admin Menu Items Export (CSV)",
      endpoint: "/admin/menu/items/export?format=csv",
      expectedHeaders: ["code", "name", "category_name"],
      expectedContentType: "text/csv"
    },
    {
      name: "Admin Restaurant Tables Export (CSV)",
      endpoint: "/admin/restaurant/tables/export?format=csv",
      expectedHeaders: ["branch_code", "table_code", "status"],
      expectedContentType: "text/csv"
    }
  ];

  for (const exp of exportsToVerify) {
    // A. Verify Authorized Access
    try {
      const res = await request("GET", exp.endpoint, null, staffToken);
      if (!res.ok) {
        fail(exp.name, `Authorized request failed with status: ${res.status}. Response: ${res.text.slice(0, 200)}`);
        continue;
      }

      const contentTypeOk = res.contentType && res.contentType.toLowerCase().includes(exp.expectedContentType.toLowerCase());
      if (!contentTypeOk) {
        fail(`${exp.name} Content-Type`, `Expected ${exp.expectedContentType}, got ${res.contentType}`);
        continue;
      }

      if (exp.expectedContentType === "text/csv") {
        const headers = parseCSVHeaders(res.text);
        const hasExpectedHeader = exp.expectedHeaders.every(h => headers.includes(h));
        if (hasExpectedHeader) {
          pass(exp.name, `Export succeeded. Size: ${res.text.length} bytes. Columns found: [${headers.join(", ")}].`);
        } else {
          fail(exp.name, `Export succeeded but headers mismatch. Found: [${headers.join(", ")}], expected to contain: [${exp.expectedHeaders.join(", ")}].`);
        }
      } else {
        // JSON format
        if (res.data && res.data.data !== undefined) {
          pass(exp.name, `Export succeeded in JSON format. Records count: ${Array.isArray(res.data.data) ? res.data.data.length : 'object'}.`);
        } else {
          fail(exp.name, `Export succeeded in JSON format but data wrapper missing. Response: ${res.text.slice(0, 200)}`);
        }
      }
    } catch (e) {
      fail(exp.name, `Error during authorized fetch: ${e.message}`);
    }

    // B. Verify Unauthorized Block (Safety & RBAC)
    try {
      const res = await request("GET", exp.endpoint, null, null);
      if (res.status === 401 || res.status === 403) {
        pass(`${exp.name} - Unauthorized Block`, `Correctly rejected unauthorized request with status ${res.status}.`);
      } else {
        fail(`${exp.name} - Unauthorized Block`, `Expected 401/403 for anonymous request, but got status ${res.status}.`);
      }
    } catch (e) {
      fail(`${exp.name} - Unauthorized Block`, `Error during anonymous request check: ${e.message}`);
    }
  }

  // 3. Save verification results
  const overall_status = criticalFailedCount > 0 ? "FAIL" : "PASS";
  const outputData = {
    overall_status,
    checked_at_utc: new Date().toISOString(),
    steps: results
  };

  const resultsPath = path.resolve(repoRoot, "storage", "app", "booking_release", "export_report_smoke_result.json");
  fs.writeFileSync(resultsPath, JSON.stringify(outputData, null, 2));

  const mdPath = path.resolve(repoRoot, "docs", "runbooks", "export-report-smoke-result.md");
  let md = "# CSV/Report Export E2E Smoke Results\n\n";
  md += `- **Overall Status**: ${overall_status}\n\n`;
  md += "| Step | Status | Detail |\n";
  md += "|---|---|---|\n";
  for (const r of results) {
    md += `| ${r.step} | ${r.status} | ${r.detail} |\n`;
  }
  fs.writeFileSync(mdPath, md);

  console.log(`Done. CSV/Report Export Smoke Status: ${overall_status}`);
}

run();
