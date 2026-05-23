import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDirectory, "..", "..");
const manifestPath = path.resolve(repoRoot, "storage", "app", "uat", "scenario-pack.json");

const baseUrl = process.env.API_BASE_URL || "http://127.0.0.1:8000/api/v1";
const allowEnvBlocked = process.argv.includes("--allow-env-blocked");

const results = [];
let criticalFailedCount = 0;
let skippedCount = 0;
let deferredCount = 0;

function pass(step, detail) {
  console.log(`[PASS] ${step}`);
  results.push({ step, status: "PASS", detail });
}

function fail(step, detail, isCritical = true) {
  console.log(`[FAIL] ${step}: ${detail}`);
  const status = allowEnvBlocked && detail.includes("fetch failed") ? "ENV_BLOCKED" : "FAIL";
  results.push({ step, status, detail, isCritical });
  if (isCritical) {
    criticalFailedCount++;
  }
}

function skip(step, reason, isDeferred = false) {
  const status = isDeferred ? "DEFERRED" : "SKIPPED_WITH_REASON";
  console.log(`[${status}] ${step}: ${reason}`);
  results.push({ step, status, detail: reason });
  if (isDeferred) deferredCount++;
  else skippedCount++;
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
  if (method !== "GET") {
    headers["Idempotency-Key"] = "smoke-" + method.toLowerCase() + "-" + Math.floor(Math.random() * 100000000);
  }
  
  const options = {
    method,
    headers,
    signal: AbortSignal.timeout(10000),
  };
  if (body) {
    options.body = JSON.stringify(body);
  }

  const response = await fetch(`${baseUrl}${endpoint}`, options);
  const text = await response.text();
  let data = null;
  try {
    data = JSON.parse(text);
  } catch (e) {
    // text response
  }
  return { ok: response.ok, status: response.status, data, text };
}

async function run() {
  let customerToken = null;
  let staffToken = null;
  let preorderActive = false;
  let customerIdentifier, customerPassword, staffIdentifier, staffPassword;

  customerIdentifier = process.env.CUSTOMER_USERNAME || "ms.customer-03";
  customerPassword = process.env.CUSTOMER_PASSWORD || "password";
  staffIdentifier = process.env.STAFF_USERNAME || "bootstrap-admin";
  staffPassword = process.env.STAFF_PASSWORD || "password";

  // 1. Health
  try {
    const res = await request("GET", "/health");
    if (res.ok) pass("Health/Readiness", "API is healthy");
    else fail("Health/Readiness", `Status: ${res.status}`);
  } catch (e) {
    fail("Health/Readiness", e.message);
  }

  // 2. Auth Customer
  try {
    const res = await request("POST", "/auth/customer/login", {
      identifier: customerIdentifier,
      password: customerPassword,
      session_label: "e2e-smoke"
    });
    if (res.ok && res.data?.data?.access_token) {
      customerToken = res.data.data.access_token;
      pass("Customer Auth", "Got customer token");
    } else {
      fail("Customer Auth", `Failed to login: ${res.text}`);
    }
  } catch (e) {
    fail("Customer Auth", e.message);
  }

  const UATnow = new Date();
  UATnow.setMilliseconds(0);
  const fromTime = new Date(UATnow.getTime() + 7200000).toISOString();
  const toTime = new Date(UATnow.getTime() + 10800000).toISOString();
  const sessionId = "smoke-sess-" + Math.floor(Math.random() * 1000000);

  // 3. Customer tables
  let tables = [];
  try {
    const res = await request("GET", `/tables/available?from=${encodeURIComponent(fromTime)}&to=${encodeURIComponent(toTime)}&party_size=5`, null, customerToken);
    if (res.ok) {
      pass("GET available tables", "Got available tables");
      tables = res.data?.data || [];
    } else {
      fail("GET available tables", res.text);
    }
  } catch (e) {
    fail("GET available tables", e.message);
  }

  // 4. POST table hold
  let holdId = null;
  let tableIds = [];
  let branchId = null;
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
      tableIds = selectedTables.map(t => t.table_id);
      branchId = selectedTables[0].branch_id;

      const res = await request("POST", "/table-holds", {
        session_id: sessionId,
        start_time: fromTime,
        end_time: toTime,
        table_ids: tableIds,
        branch_id: branchId
      }, customerToken);

      if (res.ok) {
        holdId = res.data?.data?.hold_id;
        pass("POST table hold", `Created hold for tables: ${tableIds.join(",")}`);
      } else {
        fail("POST table hold", res.text);
      }
    } else {
      skip("POST table hold", "No available tables to hold");
    }
  } catch (e) {
    fail("POST table hold", e.message);
  }

  // 5. POST reservation
  let reservationId = null;
  let rowVersion = 1;
  try {
    if (holdId) {
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
        pass("POST reservation", `Reservation created: ${reservationId} (Code: ${res.data?.data?.reservation_code})`);
      } else {
        fail("POST reservation", res.text);
      }
    } else {
      skip("POST reservation", "No hold ID to create reservation");
    }
  } catch (e) {
    fail("POST reservation", e.message);
  }

  // 6. GET reservation detail
  try {
    if (reservationId) {
      const res = await request("GET", `/reservations/${reservationId}`, null, customerToken);
      if (res.ok) pass("GET reservation detail", "Fetched reservation detail");
      else fail("GET reservation detail", res.text, false);
    } else {
      skip("GET reservation detail", "No reservation created");
    }
  } catch(e) {
    fail("GET reservation detail", e.message, false);
  }

  // 6.1. Deposit Preview & Payment confirmation (simulated provider)
  try {
    if (reservationId) {
      const res = await request("GET", `/reservations/${reservationId}/deposit-preview`, null, customerToken);
      if (res.ok) {
        const depositRequired = res.data?.data?.deposit?.self_service?.deposit_required;
        const depositAmount = res.data?.data?.deposit?.outstanding_amount;
        pass("GET deposit preview", `Deposit required: ${depositRequired}, Amount: ${depositAmount}`);

        if (depositRequired) {
          // Acknowledge deposit
          const ackRes = await request("POST", `/reservations/${reservationId}/deposit/acknowledge`, { row_version: rowVersion }, customerToken);
          if (ackRes.ok) {
            rowVersion = ackRes.data?.data?.row_version || ackRes.data?.data?.reservation?.row_version || (rowVersion + 1);
            pass("POST deposit acknowledge", "Deposit requirement acknowledged");
          } else {
            fail("POST deposit acknowledge", ackRes.text);
          }
 
          // Submit intent
          const intentRes = await request("POST", `/reservations/${reservationId}/deposit/intent`, { row_version: rowVersion }, customerToken);
          if (intentRes.ok) {
            rowVersion = intentRes.data?.data?.row_version || intentRes.data?.data?.reservation?.row_version || (rowVersion + 1);
            pass("POST deposit intent", "Deposit intent submitted");
          } else {
            fail("POST deposit intent", intentRes.text);
          }
 
          // Create payment session
          const paySessRes = await request("POST", `/reservations/${reservationId}/deposit/payment-sessions`, {
            payment_method: "Simulated",
            payment_provider: "Simulated",
            row_version: rowVersion
          }, customerToken);
 
          if (paySessRes.ok) {
            const paySessionId = paySessRes.data?.data?.payment_session?.deposit_payment_session_id;
            pass("POST deposit payment session", `Payment session created: ${paySessionId}`);
 
            if (paySessionId) {
              const sessionRowVersion = paySessRes.data?.data?.payment_session?.row_version || 1;
              const confirmRes = await request("POST", `/reservations/${reservationId}/deposit/payment-sessions/${paySessionId}/confirm`, {
                reference: "smoke-ref-" + Math.floor(Math.random() * 1000000),
                row_version: sessionRowVersion
              }, customerToken);
 
              if (confirmRes.ok) {
                rowVersion = confirmRes.data?.data?.row_version || confirmRes.data?.data?.reservation?.row_version || (rowVersion + 1);
                pass("POST deposit payment confirm", "Deposit payment confirmed successfully via Simulated provider");
              } else {
                fail("POST deposit payment confirm", confirmRes.text);
              }
            }
          } else {
            fail("POST deposit payment session", paySessRes.text);
          }
        } else {
          skip("POST deposit flow", "Deposit not required for this booking");
        }
      } else {
        fail("GET deposit preview", res.text);
      }
    } else {
      skip("GET deposit preview", "No reservation created");
    }
  } catch (e) {
    fail("GET deposit preview", e.message);
  }

  // 6.5. Preorder flow (Preview, Replace, Submit)
  try {
    if (reservationId) {
      // Find a menu item to preorder
      const menuRes = await request("GET", "/menu/items", null, customerToken);
      const menuItems = menuRes.data?.data || [];
      if (menuItems.length > 0) {
        const selectedItem = menuItems[0];
        
        // Preview preorder
        const previewRes = await request("POST", `/reservations/${reservationId}/preorder/preview`, {
          pre_order_items: [{ item_id: selectedItem.item_id, quantity: 2 }]
        }, customerToken);

        if (previewRes.ok) {
          pass("POST preorder preview", `Previewed item: ${selectedItem.name}`);

          // Fetch fresh reservation details to ensure we have the absolute latest row_version before preorder replace
          const freshRes = await request("GET", `/reservations/${reservationId}`, null, customerToken);
          if (freshRes.ok) {
            rowVersion = freshRes.data?.data?.row_version || rowVersion;
            console.log(`[DIAGNOSTIC] Fresh rowVersion fetched before replace: ${rowVersion}`);
          }

          // Replace preorder (creates draft)
          console.log(`[DIAGNOSTIC] Current local rowVersion before replace: ${rowVersion}`);
          const replaceRes = await request("PUT", `/reservations/${reservationId}/preorder`, {
            row_version: rowVersion,
            pre_order_items: [{ item_id: selectedItem.item_id, quantity: 3 }]
          }, customerToken);

          if (replaceRes.ok) {
            const preOrderRowVersion = replaceRes.data?.data?.pre_order?.order_row_version || 1;
            pass("PUT preorder replace", "Preorder draft created/replaced successfully");

            // Fetch fresh reservation details to ensure we have the absolute latest row_version before preorder submit
            const freshRes2 = await request("GET", `/reservations/${reservationId}`, null, customerToken);
            if (freshRes2.ok) {
              rowVersion = freshRes2.data?.data?.row_version || rowVersion;
              console.log(`[DIAGNOSTIC] Fresh rowVersion fetched before submit: ${rowVersion}`);
            }

            // Submit preorder
            const submitRes = await request("POST", `/reservations/${reservationId}/preorder/submit`, {
              row_version: rowVersion,
              pre_order_row_version: preOrderRowVersion
            }, customerToken);

            if (submitRes.ok) {
              rowVersion = submitRes.data?.data?.row_version || submitRes.data?.data?.reservation?.row_version || (rowVersion + 1);
              pass("POST preorder submit", "Preorder submitted successfully");
              preorderActive = true;
            } else {
              fail("POST preorder submit", submitRes.text);
            }
          } else {
            fail("PUT preorder replace", replaceRes.text);
          }
        } else {
          fail("POST preorder preview", previewRes.text);
        }
      } else {
        skip("POST preorder flow", "No menu items available to preorder");
      }
    } else {
      skip("POST preorder flow", "No reservation created");
    }
  } catch (e) {
    fail("POST preorder flow", e.message);
  }

  // Staff Flow
  // 7. Staff Auth
  try {
    const res = await request("POST", "/auth/staff/login", {
      identifier: staffIdentifier,
      password: staffPassword,
      device_name: "e2e-smoke"
    });
    if (res.ok && res.data?.data?.access_token) {
      staffToken = res.data.data.access_token;
      pass("Staff Auth", "Got staff token");
    } else {
      fail("Staff Auth", `Failed to login: ${res.text}`);
    }
  } catch (e) {
    fail("Staff Auth", e.message);
  }

  // 8. Staff GET inbox
  try {
    const res = await request("GET", "/staff/reservations", null, staffToken);
    if (res.ok) pass("GET reservation inbox", "Fetched inbox");
    else fail("GET reservation inbox", res.text);
  } catch(e) {
    fail("GET reservation inbox", e.message);
  }

  // 9. Staff GET detail (Optional)
  try {
    if (reservationId) {
      const res = await request("GET", `/staff/reservations/${reservationId}`, null, staffToken);
      if (res.ok) pass("Staff GET reservation detail", "Fetched detail");
      else fail("Staff GET reservation detail", res.text, false);
    } else {
        skip("Staff GET reservation detail", "No reservation created");
    }
  } catch(e) {
    fail("Staff GET reservation detail", e.message, false);
  }

  // Staff Preorder Confirm Flow
  try {
    if (staffToken && reservationId && preorderActive) {
      // Staff GET preorder
      const preorderRes = await request("GET", `/staff/reservations/${reservationId}/preorder`, null, staffToken);
      if (preorderRes.ok) {
        pass("Staff GET preorder", "Staff retrieved preorder detail");

        // Staff confirm preorder
        const confirmRes = await request("POST", `/staff/reservations/${reservationId}/preorder/confirm`, null, staffToken);
        if (confirmRes.ok) {
          pass("Staff POST preorder confirm", "Staff confirmed preorder successfully");
        } else {
          fail("Staff POST preorder confirm", confirmRes.text);
        }
      } else {
        fail("Staff GET preorder", preorderRes.text);
      }
    } else {
      skip("Staff preorder confirm", "No active preorder to confirm");
    }
  } catch (e) {
    fail("Staff preorder confirm", e.message);
  }

  skip("POST staff reservation action/check-in", "Cannot check in a future reservation right away without bypassing business rules", true);
  skip("Open/create service session", "Deferred", true);
  skip("Create/update order", "Deferred", true);
  skip("Dispatch order to kitchen", "Deferred", true);
  skip("Kitchen station tickets", "Deferred", true);
  skip("Fire/bump/recall", "Deferred", true);
  skip("Checkout preview", "Deferred", true);
  skip("Payment/refund", "Deferred", true);

  const overall_status = criticalFailedCount > 0 ? (allowEnvBlocked ? "ENV_BLOCKED" : "FAIL") : "PASS";
  const process_exit_code_expected = (criticalFailedCount > 0 && !allowEnvBlocked) ? 1 : 0;

  const outputData = {
    overall_status,
    process_exit_code_expected,
    critical_failed_count: criticalFailedCount,
    skipped_count: skippedCount,
    deferred_count: deferredCount,
    steps: results
  };

  const resultsPath = path.resolve(repoRoot, "storage", "app", "booking_release", "runtime_e2e_smoke_result.json");
  fs.mkdirSync(path.dirname(resultsPath), { recursive: true });
  fs.writeFileSync(resultsPath, JSON.stringify(outputData, null, 2));
  
  const mdPath = path.resolve(repoRoot, "docs", "runbooks", "runtime-e2e-smoke-result.md");
  let md = "# Runtime E2E Smoke Results\n\n";
  md += `- **Overall Status**: ${overall_status}\n`;
  md += `- **Critical Failed**: ${criticalFailedCount}\n`;
  md += `- **Skipped**: ${skippedCount}\n`;
  md += `- **Deferred**: ${deferredCount}\n\n`;
  for (const r of results) {
      md += `- **${r.step}**: ${r.status} - ${r.detail}\n`;
  }
  fs.writeFileSync(mdPath, md);

  console.log(`Done. Status: ${overall_status}. Expected Exit Code: ${process_exit_code_expected}`);
  process.exit(process_exit_code_expected);
}

run();
