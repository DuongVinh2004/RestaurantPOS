const baseUrl = "http://127.0.0.1:8000/api/v1";

async function probe() {
  const fromTime = new Date(Date.now() + 3600000).toISOString();
  const toTime = new Date(Date.now() + 7200000).toISOString();
  const sessionId = "smoke-sess-" + Math.floor(Math.random() * 1000000);

  // Login
  const loginRes = await fetch(`${baseUrl}/auth/customer/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      identifier: "ms.customer-03",
      password: "password",
      session_label: "probe"
    })
  });
  const loginData = await loginRes.json();
  const token = loginData.data.access_token;
  console.log("Token acquired.");

  // Get available tables
  const tablesRes = await fetch(`${baseUrl}/tables/available?from=${encodeURIComponent(fromTime)}&to=${encodeURIComponent(toTime)}&party_size=2`, {
    headers: {
      "Accept": "application/json",
      "X-Customer-Token": token,
      "X-Session-Id": sessionId
    }
  });
  const tablesData = await tablesRes.json();
  const tables = tablesData.data;
  if (!tables || tables.length === 0) {
    console.log("No tables available.");
    return;
  }
  const table = tables[0];
  console.log("Found available table:", table.table_code, "ID:", table.table_id);

  // Post table hold
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
      table_ids: [table.table_id],
      branch_id: table.branch_id
    })
  });
  const holdData = await holdRes.json();
  console.log("Hold status:", holdRes.status);
  const holdId = holdData.data?.hold_id;
  console.log("Hold ID:", holdId);

  if (!holdId) return;

  // Post reservation
  const resRes = await fetch(`${baseUrl}/reservations`, {
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
      guest_count: 2
    })
  });
  const resData = await resRes.json();
  console.log("Reservation status:", resRes.status);
  console.log("Reservation data:", JSON.stringify(resData, null, 2));
}

probe();
