const baseUrl = "http://127.0.0.1:8000/api/v1";

async function run() {
  const now = new Date();
  now.setMilliseconds(0);
  // Schedule reservation 2 hours in the future to satisfy the >60min preorder cutoff rule
  const fromTime = new Date(now.getTime() + 7200000).toISOString();
  const toTime = new Date(now.getTime() + 10800000).toISOString();
  const sessionId = "smoke-sess-" + Math.floor(Math.random() * 1000000);

  console.log("=== STEP 1: CUSTOMER LOGIN ===");
  const loginRes = await fetch(`${baseUrl}/auth/customer/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      identifier: "ms.customer-03",
      password: "password",
      session_label: "smoke-test"
    })
  });
  const loginData = await loginRes.json();
  const token = loginData.data.access_token;
  console.log("Customer Token:", token ? "Acquired" : "FAILED");

  console.log("=== STEP 2: GET MENU ITEMS FOR PREORDER ===");
  const menuRes = await fetch(`${baseUrl}/menu/items`, {
    headers: {
      "Accept": "application/json",
      "X-Customer-Token": token
    }
  });
  const menuData = await menuRes.json();
  const menuItems = menuData.data || [];
  console.log("Menu Items Available:", menuItems.length);
  if (menuItems.length === 0) {
    console.log("No menu items seeded!");
    return;
  }
  
  // Find item with preorder enabled
  let selectedItem = null;
  console.log("Scanning seeded menu items for preorder settings...");
  for (const item of menuItems) {
    if (item.is_preorder_enabled) {
      selectedItem = item;
    }
  }

  if (!selectedItem) {
    console.log("No menu items have is_preorder_enabled: true in seed data. Using the first one as fallback.");
    selectedItem = menuItems[0];
  }
  console.log("Selected Item for Preorder:", selectedItem.name, "ID:", selectedItem.item_id);

  console.log("=== STEP 3: GET AVAILABLE TABLES ===");
  const tablesRes = await fetch(`${baseUrl}/tables/available?from=${encodeURIComponent(fromTime)}&to=${encodeURIComponent(toTime)}&party_size=5`, {
    headers: {
      "Accept": "application/json",
      "X-Customer-Token": token,
      "X-Session-Id": sessionId
    }
  });
  const tablesData = await tablesRes.json();
  const tables = tablesData.data || [];
  if (tables.length === 0) {
    console.log("No available tables found for party size 5.");
    return;
  }

  // Dynamically group tables until their combined capacity/seats covers 5 guests
  let selectedTables = [];
  let totalCapacity = 0;
  for (const t of tables) {
    selectedTables.push(t);
    const seats = t.seats || t.capacity || 4; // fallback
    totalCapacity += seats;
    if (totalCapacity >= 5) {
      break;
    }
  }

  const tableIds = selectedTables.map(t => t.table_id);
  const branchId = selectedTables[0].branch_id;
  console.log("Selected Tables for Hold:", selectedTables.map(t => `${t.table_code} (${t.seats || t.capacity || 4} seats)`).join(", "));

  console.log("=== STEP 4: CREATE TABLE HOLD ===");
  const holdRes = await fetch(`${baseUrl}/table-holds`, {
    method: "POST",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json",
      "X-Customer-Token": token,
      "X-Session-Id": sessionId,
      "Idempotency-Key": "smoke-hold-" + Math.floor(Math.random() * 10000000)
    },
    body: JSON.stringify({
      session_id: sessionId,
      start_time: fromTime,
      end_time: toTime,
      table_ids: tableIds,
      branch_id: branchId
    })
  });
  const holdData = await holdRes.json();
  console.log("Hold Status:", holdRes.status);
  const holdId = holdData.data?.hold_id;
  if (!holdId) {
    console.log("Failed to create hold:", JSON.stringify(holdData));
    return;
  }

  console.log("=== STEP 5: CREATE RESERVATION ===");
  const resvRes = await fetch(`${baseUrl}/reservations`, {
    method: "POST",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json",
      "X-Customer-Token": token,
      "X-Session-Id": sessionId,
      "Idempotency-Key": "smoke-resv-" + Math.floor(Math.random() * 10000000)
    },
    body: JSON.stringify({
      hold_id: holdId,
      session_id: sessionId,
      start_time: fromTime,
      end_time: toTime,
      guest_count: 5
    })
  });
  const resvData = await resvRes.json();
  console.log("Reservation Status:", resvRes.status);
  const reservationId = resvData.data?.reservation_id;
  const rowVersion = resvData.data?.row_version || 1;
  console.log("Reservation ID:", reservationId, "Row Version:", rowVersion);
  if (!reservationId) {
    console.log("Failed to create reservation:", JSON.stringify(resvData));
    return;
  }

  console.log("=== STEP 6: CHECK DEPOSIT PREVIEW & PAYMENT ===");
  const depositPreviewRes = await fetch(`${baseUrl}/reservations/${reservationId}/deposit-preview`, {
    headers: {
      "Accept": "application/json",
      "X-Customer-Token": token,
      "X-Session-Id": sessionId
    }
  });
  const depositPreviewData = await depositPreviewRes.json();
  console.log("Deposit Preview status:", depositPreviewRes.status);

  // The fields in deposit-preview are under self_service or similar:
  const depositRequired = depositPreviewData.data?.self_service?.deposit_required;
  const depositAmount = depositPreviewData.data?.outstanding_amount;
  console.log("Deposit Required:", depositRequired, "Amount:", depositAmount);

  if (depositRequired) {
    console.log("=== STEP 6.1: ACKNOWLEDGE DEPOSIT ===");
    const ackRes = await fetch(`${baseUrl}/reservations/${reservationId}/deposit/acknowledge`, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-Customer-Token": token,
        "X-Session-Id": sessionId,
        "Idempotency-Key": "smoke-ack-" + Math.floor(Math.random() * 10000000)
      }
    });
    console.log("Deposit Acknowledge Status:", ackRes.status);

    console.log("=== STEP 6.2: SUBMIT DEPOSIT INTENT ===");
    const intentRes = await fetch(`${baseUrl}/reservations/${reservationId}/deposit/intent`, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-Customer-Token": token,
        "X-Session-Id": sessionId,
        "Idempotency-Key": "smoke-intent-" + Math.floor(Math.random() * 10000000)
      }
    });
    const intentData = await intentRes.json();
    console.log("Deposit Intent Status:", intentRes.status);

    console.log("=== STEP 6.3: CREATE PAYMENT SESSION ===");
    const paySessRes = await fetch(`${baseUrl}/reservations/${reservationId}/deposit/payment-sessions`, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-Customer-Token": token,
        "X-Session-Id": sessionId,
        "Idempotency-Key": "smoke-paysess-" + Math.floor(Math.random() * 10000000)
      },
      body: JSON.stringify({
        payment_method: "Simulated",
        payment_provider: "Simulated"
      })
    });
    const paySessData = await paySessRes.json();
    console.log("Payment Session Status:", paySessRes.status);
    const paySessionId = paySessData.data?.session_id;
    console.log("Payment Session ID:", paySessionId);

    if (paySessionId) {
      console.log("=== STEP 6.4: CONFIRM PAYMENT SESSION ===");
      const confirmRes = await fetch(`${baseUrl}/reservations/${reservationId}/deposit/payment-sessions/${paySessionId}/confirm`, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-Customer-Token": token,
          "X-Session-Id": sessionId,
          "Idempotency-Key": "smoke-payconf-" + Math.floor(Math.random() * 10000000)
        },
        body: JSON.stringify({
          reference: "smoke-ref-" + Math.floor(Math.random() * 1000000)
        })
      });
      const confirmData = await confirmRes.json();
      console.log("Payment Confirmation Status:", confirmRes.status);
      console.log("Payment Confirmation Data:", JSON.stringify(confirmData));
    }
  }

  console.log("=== STEP 7: PREORDER PREVIEW ===");
  const preorderPreviewRes = await fetch(`${baseUrl}/reservations/${reservationId}/preorder/preview`, {
    method: "POST",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json",
      "X-Customer-Token": token,
      "X-Session-Id": sessionId
    },
    body: JSON.stringify({
      pre_order_items: [
        {
          item_id: selectedItem.item_id,
          quantity: 2
        }
      ]
    })
  });
  const preorderPreviewData = await preorderPreviewRes.json();
  console.log("Preorder Preview Status:", preorderPreviewRes.status);

  console.log("=== STEP 8: REPLACE PREORDER ===");
  const replacePreorderRes = await fetch(`${baseUrl}/reservations/${reservationId}/preorder`, {
    method: "PUT",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json",
      "X-Customer-Token": token,
      "X-Session-Id": sessionId,
      "Idempotency-Key": "smoke-pre-repl-" + Math.floor(Math.random() * 10000000)
    },
    body: JSON.stringify({
      row_version: rowVersion,
      pre_order_items: [
        {
          item_id: selectedItem.item_id,
          quantity: 3
        }
      ]
    })
  });
  const replacePreorderData = await replacePreorderRes.json();
  console.log("Replace Preorder Status:", replacePreorderRes.status);
  const preOrderRowVersion = replacePreorderData.data?.pre_order?.order_row_version || 1;

  console.log("=== STEP 9: SUBMIT PREORDER ===");
  const submitPreorderRes = await fetch(`${baseUrl}/reservations/${reservationId}/preorder/submit`, {
    method: "POST",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json",
      "X-Customer-Token": token,
      "X-Session-Id": sessionId,
      "Idempotency-Key": "smoke-pre-sub-" + Math.floor(Math.random() * 10000000)
    },
    body: JSON.stringify({
      row_version: rowVersion,
      pre_order_row_version: preOrderRowVersion
    })
  });
  const submitPreorderData = await submitPreorderRes.json();
  console.log("Submit Preorder Status:", submitPreorderRes.status);
  console.log("Submit Preorder Data:", JSON.stringify(submitPreorderData));

  console.log("=== STEP 10: STAFF LOGIN ===");
  const staffLoginRes = await fetch(`${baseUrl}/auth/staff/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      identifier: "bootstrap-admin",
      password: "password",
      device_name: "smoke-test"
    })
  });
  const staffLoginData = await staffLoginRes.json();
  const staffToken = staffLoginData.data.access_token;
  console.log("Staff Token:", staffToken ? "Acquired" : "FAILED");

  if (staffToken) {
    console.log("=== STEP 11: STAFF GET PREORDER ===");
    const staffPreorderRes = await fetch(`${baseUrl}/staff/reservations/${reservationId}/preorder`, {
      headers: {
        "Accept": "application/json",
        "X-Staff-Key": staffToken
      }
    });
    const staffPreorderData = await staffPreorderRes.json();
    console.log("Staff Preorder Status:", staffPreorderRes.status);
    console.log("Staff Preorder Present:", staffPreorderData.data?.pre_order?.present);

    console.log("=== STEP 12: STAFF CONFIRM PREORDER ===");
    const staffConfirmRes = await fetch(`${baseUrl}/staff/reservations/${reservationId}/preorder/confirm`, {
      method: "POST",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-Staff-Key": staffToken,
        "Idempotency-Key": "smoke-pre-conf-" + Math.floor(Math.random() * 10000000)
      }
    });
    const staffConfirmData = await staffConfirmRes.json();
    console.log("Staff Preorder Confirm Status:", staffConfirmRes.status);
    console.log("Staff Preorder Confirm Result:", JSON.stringify(staffConfirmData));
  }
}

run();
