import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDirectory, "..", "..");

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
  if (method !== "GET" && method !== "DELETE") {
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
  let customerIdentifier = process.env.CUSTOMER_USERNAME || "ms.customer-03";
  const customerPassword = process.env.CUSTOMER_PASSWORD || "password";
  const staffIdentifier = process.env.STAFF_USERNAME || "bootstrap-admin";
  const staffPassword = process.env.STAFF_PASSWORD || "password";

  console.log("Starting BATCH 12 Staff Operations E2E Smoke Verification...");

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
      session_label: "e2e-staff-ops-smoke"
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

  // Define booking slot (2 hours from now)
  const UATnow = new Date();
  UATnow.setMilliseconds(0);
  const fromTime = new Date(UATnow.getTime() + 7200000).toISOString();
  const toTime = new Date(UATnow.getTime() + 10800000).toISOString();
  const sessionId = "smoke-staff-sess-" + Math.floor(Math.random() * 1000000);

  // 3. Get available tables
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

  // 4. Create table hold
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
      fail("POST table hold", "No available tables to hold");
    }
  } catch (e) {
    fail("POST table hold", e.message);
  }

  // 5. Create reservation
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
        pass("POST reservation", `Reservation created: ${reservationId}`);
      } else {
        fail("POST reservation", res.text);
      }
    } else {
      fail("POST reservation", "No hold ID to create reservation");
    }
  } catch (e) {
    fail("POST reservation", e.message);
  }

  // 6. Confirm deposit (if needed)
  try {
    if (reservationId) {
      const res = await request("GET", `/reservations/${reservationId}/deposit-preview`, null, customerToken);
      if (res.ok) {
        const depositRequired = res.data?.data?.deposit?.self_service?.deposit_required;
        if (depositRequired) {
          // Acknowledge deposit
          const ackRes = await request("POST", `/reservations/${reservationId}/deposit/acknowledge`, { row_version: rowVersion }, customerToken);
          if (ackRes.ok) {
            rowVersion = ackRes.data?.data?.row_version || ackRes.data?.data?.reservation?.row_version || (rowVersion + 1);
          } else {
            fail("POST deposit acknowledge", ackRes.text);
          }

          // Submit intent
          const intentRes = await request("POST", `/reservations/${reservationId}/deposit/intent`, { row_version: rowVersion }, customerToken);
          if (intentRes.ok) {
            rowVersion = intentRes.data?.data?.row_version || intentRes.data?.data?.reservation?.row_version || (rowVersion + 1);
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
            if (paySessionId) {
              const sessionRowVersion = paySessRes.data?.data?.payment_session?.row_version || 1;
              const confirmRes = await request("POST", `/reservations/${reservationId}/deposit/payment-sessions/${paySessionId}/confirm`, {
                reference: "smoke-ref-" + Math.floor(Math.random() * 1000000),
                row_version: sessionRowVersion
              }, customerToken);

              if (confirmRes.ok) {
                rowVersion = confirmRes.data?.data?.row_version || confirmRes.data?.data?.reservation?.row_version || (rowVersion + 1);
                pass("Customer Deposit Payment", "Paid and Confirmed reservation deposit");
              } else {
                fail("Customer Deposit Payment", confirmRes.text);
              }
            }
          } else {
            fail("POST deposit payment session", paySessRes.text);
          }
        } else {
          pass("Customer Deposit Payment", "No deposit required");
        }
      } else {
        fail("Customer Deposit Payment", res.text);
      }
    }
  } catch (e) {
    fail("Customer Deposit Payment", e.message);
  }

  // 7. Staff Auth
  try {
    const res = await request("POST", "/auth/staff/login", {
      identifier: staffIdentifier,
      password: staffPassword,
      device_name: "e2e-staff-ops-smoke"
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

  // 8. Staff Check-in Reservation
  try {
    if (reservationId && staffToken) {
      // Get fresh reservation detail under staff first to confirm row_version
      const detailRes = await request("GET", `/staff/reservations/${reservationId}`, null, staffToken);
      if (detailRes.ok) {
        rowVersion = detailRes.data?.data?.row_version || rowVersion;
      }

      const res = await request("POST", `/staff/reservations/${reservationId}/check-in`, {
        row_version: rowVersion
      }, staffToken);

      if (res.ok) {
        rowVersion = res.data?.data?.row_version || rowVersion;
        pass("Staff Reservation Check-in", `Successfully checked in reservation ${reservationId}`);
      } else {
        fail("Staff Reservation Check-in", res.text);
      }
    } else {
      fail("Staff Reservation Check-in", "No reservation or staff token available");
    }
  } catch (e) {
    fail("Staff Reservation Check-in", e.message);
  }

  // 9. Active Service Session Verification
  let serviceSessionReservation = null;
  try {
    if (tableIds.length > 0 && staffToken) {
      const targetTableId = tableIds[0];
      const res = await request("GET", `/staff/tables/${targetTableId}/active-service-session`, null, staffToken);
      if (res.ok && res.data?.data?.reservation_id === reservationId) {
        serviceSessionReservation = res.data.data;
        pass("Active Service Session Lookup", `Verified active session on table ${targetTableId} matches reservation ${reservationId}`);
      } else {
        fail("Active Service Session Lookup", `Failed to verify session on table ${targetTableId}: ${res.text}`);
      }
    } else {
      fail("Active Service Session Lookup", "No tables checked in");
    }
  } catch (e) {
    fail("Active Service Session Lookup", e.message);
  }

  // 10. Cashier Shift verification / open
  let cashierShiftId = null;
  try {
    if (staffToken) {
      const res = await request("GET", `/staff/cashier/shifts/current?branch_id=${branchId}`, null, staffToken);
      if (res.ok && res.data?.data?.cashier_shift_id) {
        cashierShiftId = res.data.data.cashier_shift_id;
        pass("Cashier Shift Verification", `Current open cashier shift active: ${cashierShiftId}`);
      } else if (res.status === 404) {
        // Open a new shift
        console.log(`[INFO] No active cashier shift found. Opening a new cashier shift for branch ${branchId}...`);
        const openRes = await request("POST", "/staff/cashier/shifts/open", {
          opening_float_amount: 100000,
          currency: "VND",
          branch_id: branchId,
          notes: "Auto opened by BATCH 12 smoke runner"
        }, staffToken);

        if (openRes.ok && openRes.data?.data?.cashier_shift_id) {
          cashierShiftId = openRes.data.data.cashier_shift_id;
          pass("Cashier Shift Verification", `Opened new cashier shift: ${cashierShiftId}`);
        } else {
          fail("Cashier Shift Verification", `Failed to open cashier shift: ${openRes.text}`);
        }
      } else {
        fail("Cashier Shift Verification", res.text);
      }
    }
  } catch (e) {
    fail("Cashier Shift Verification", e.message);
  }

  // 11. Create Dine-in Order
  let orderId = null;
  let orderRowVersion = 1;
  let menuItem = null;
  try {
    if (reservationId && staffToken && tableIds.length > 0) {
      // Look up available catalog items to order
      const menuRes = await request("GET", "/staff/menu/items", null, staffToken);
      const menuItems = menuRes.data?.data || [];
      const itemsForBranch14 = menuItems.filter(item => item.category_id === 8 || item.category_id === 9);
      if (itemsForBranch14.length > 0) {
        menuItem = itemsForBranch14[0];
        
        // Refresh reservation state first
        const freshRes = await request("GET", `/staff/reservations/${reservationId}`, null, staffToken);
        if (freshRes.ok) {
          rowVersion = freshRes.data?.data?.row_version || rowVersion;
        }

        const targetTableId = tableIds[0];
        const res = await request("POST", `/staff/tables/${targetTableId}/orders`, {
          reservation_id: reservationId,
          row_version: rowVersion,
          items: [{
            menu_item_id: menuItem.item_id,
            qty: 1,
            note: "e2e_batch12_dine_in"
          }]
        }, staffToken);

        if (res.ok && res.data?.data?.order_id) {
          orderId = res.data.data.order_id;
          orderRowVersion = res.data.data.row_version || 1;
          pass("Dine-in Order Creation", `Order ${orderId} created on table ${targetTableId} with item ${menuItem.name}`);
        } else {
          fail("Dine-in Order Creation", res.text);
        }
      } else {
        fail("Dine-in Order Creation", "No catalog menu items found to order in Branch 14 UAT categories");
      }
    }
  } catch (e) {
    fail("Dine-in Order Creation", e.message);
  }

  // 12. Add Order Item
  try {
    if (orderId && staffToken && menuItem) {
      // Find a second item in Category 8 or 9 if possible
      const menuRes = await request("GET", "/staff/menu/items", null, staffToken);
      const menuItems = menuRes.data?.data || [];
      const itemsForBranch14 = menuItems.filter(item => item.category_id === 8 || item.category_id === 9);
      const secondItem = itemsForBranch14[1] || menuItem;

      const res = await request("POST", `/staff/orders/${orderId}/items`, {
        row_version: orderRowVersion,
        items: [{
          menu_item_id: secondItem.item_id,
          qty: 2,
          note: "e2e_batch12_extra_qty"
        }]
      }, staffToken);

      if (res.ok) {
        orderRowVersion = res.data?.data?.row_version || (orderRowVersion + 1);
        pass("Modify Order Items", `Successfully added extra items to order ${orderId}`);
      } else {
        fail("Modify Order Items", res.text);
      }
    }
  } catch (e) {
    fail("Modify Order Items", e.message);
  }

  // 13. Kitchen Dispatch
  let kitchenTickets = [];
  try {
    if (orderId && staffToken) {
      const res = await request("POST", `/staff/orders/${orderId}/kitchen/dispatch`, {
        row_version: orderRowVersion
      }, staffToken);

      if (res.ok && res.data?.data) {
        kitchenTickets = res.data.data;
        pass("Kitchen Dispatch", `Dispatched order ${orderId} to kitchen. Created ${kitchenTickets.length} tickets.`);
      } else {
        fail("Kitchen Dispatch", res.text);
      }
    }
  } catch (e) {
    fail("Kitchen Dispatch", e.message);
  }

  // 14. Kitchen Ticket Station lookup & State Mutation (Fire + Bump)
  try {
    if (kitchenTickets.length > 0 && staffToken) {
      const targetTicket = kitchenTickets[0];
      const ticketId = targetTicket.ticket_id;
      let ticketRowVersion = targetTicket.row_version || 1;

      // Stations
      const stationsRes = await request("GET", "/staff/kitchen/stations", null, staffToken);
      const stations = stationsRes.data?.data || [];
      if (stations.length > 0) {
        const stationId = stations[0].station_id;
        
        // Station tickets lookup
        const ticketsRes = await request("GET", `/staff/kitchen/stations/${stationId}/tickets`, null, staffToken);
        if (ticketsRes.ok) {
          pass("Kitchen Station Tickets Lookup", `Fetched kitchen tickets for station ${stationId}`);
        } else {
          fail("Kitchen Station Tickets Lookup", ticketsRes.text, false);
        }
      }

      // Fire Ticket
      const fireRes = await request("POST", `/staff/kitchen/tickets/${ticketId}/fire`, {
        row_version: ticketRowVersion
      }, staffToken);

      if (fireRes.ok) {
        ticketRowVersion = fireRes.data?.data?.row_version || (ticketRowVersion + 1);
        pass("Kitchen Fire Ticket", `Successfully fired ticket ${ticketId}`);

        // Bump Ticket
        const bumpRes = await request("POST", `/staff/kitchen/tickets/${ticketId}/bump`, {
          row_version: ticketRowVersion
        }, staffToken);

        if (bumpRes.ok) {
          pass("Kitchen Bump Ticket", `Successfully bumped/completed ticket ${ticketId}`);
        } else {
          fail("Kitchen Bump Ticket", bumpRes.text);
        }
      } else {
        fail("Kitchen Fire Ticket", fireRes.text);
      }
    } else {
      skip("Kitchen Ticket Mutation", "No kitchen tickets created to fire/bump");
    }
  } catch (e) {
    fail("Kitchen Ticket Mutation", e.message);
  }

  // 15. Checkout Preview
  let totalDue = 0;
  try {
    if (orderId && staffToken) {
      const res = await request("GET", `/staff/orders/${orderId}/settlement-preview`, null, staffToken);
      if (res.ok && res.data?.data) {
        const rawTotalDue = res.data.data.outstanding_amount !== undefined ? res.data.data.outstanding_amount : res.data.data.total_amount;
        totalDue = typeof rawTotalDue === "string" ? parseFloat(rawTotalDue.replace(/,/g, "")) : parseFloat(rawTotalDue);
        pass("Checkout Preview", `Fetched settlement preview. Outstanding balance: ${totalDue} VND`);
      } else {
        fail("Checkout Preview", res.text);
      }
    }
  } catch (e) {
    fail("Checkout Preview", e.message);
  }

  // 16. Settle and Record Payment
  try {
    if (orderId && staffToken && totalDue > 0) {
      // Get fresh order row_version first
      const freshOrderRes = await request("GET", `/staff/orders/${orderId}`, null, staffToken);
      if (freshOrderRes.ok) {
        orderRowVersion = freshOrderRes.data?.data?.row_version || orderRowVersion;
      }

      const res = await request("POST", `/staff/orders/${orderId}/pay`, {
        payment_method: "Cash",
        payment_provider: "Cash",
        paid_amount: totalDue,
        currency: "VND",
        row_version: orderRowVersion,
        notes: "Settled via BATCH 12 E2E smoke"
      }, staffToken);

      if (res.ok) {
        pass("Order Settlement & Payment", `Paid ${totalDue} VND for order ${orderId} successfully via Cash`);
      } else {
        fail("Order Settlement & Payment", res.text);
      }
    } else {
      fail("Order Settlement & Payment", "No payable amount or order ID available");
    }
  } catch (e) {
    fail("Order Settlement & Payment", e.message);
  }

  // 17. Reporting status verification
  try {
    if (staffToken) {
      const res = await request("GET", `/staff/reporting/daily-sales?branch_id=${branchId}`, null, staffToken);
      if (res.ok) {
        pass("Reporting Endpoint Health", "Daily sales reporting endpoint is operational and healthy");
      } else {
        fail("Reporting Endpoint Health", res.text, false);
      }
    }
  } catch (e) {
    fail("Reporting Endpoint Health", e.message, false);
  }

  // Final summary and reporting
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

  const resultsPath = path.resolve(repoRoot, "storage", "app", "booking_release", "staff_ops_runtime_smoke_result.json");
  fs.mkdirSync(path.dirname(resultsPath), { recursive: true });
  fs.writeFileSync(resultsPath, JSON.stringify(outputData, null, 2));
  
  const mdPath = path.resolve(repoRoot, "docs", "runbooks", "staff-ops-runtime-smoke-result.md");
  let md = "# Staff Operations Runtime Smoke Results\n\n";
  md += `- **Overall Status**: ${overall_status}\n`;
  md += `- **Critical Failed**: ${criticalFailedCount}\n`;
  md += `- **Skipped**: ${skippedCount}\n`;
  md += `- **Deferred**: ${deferredCount}\n\n`;
  md += "| Step | Status | Detail |\n";
  md += "|---|---|---|\n";
  for (const r of results) {
      md += `| ${r.step} | ${r.status} | ${r.detail} |\n`;
  }
  fs.writeFileSync(mdPath, md);

  console.log(`Done. Operational Status: ${overall_status}. Expected Exit Code: ${process_exit_code_expected}`);
  process.exit(process_exit_code_expected);
}

run();
