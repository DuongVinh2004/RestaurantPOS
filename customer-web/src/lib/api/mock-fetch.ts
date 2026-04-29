const now = new Date();
const inOneHour = new Date(now.getTime() + 60 * 60 * 1000).toISOString();
const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000).toISOString();

const menuItems = [
  {
    item_id: 101,
    category_id: 1,
    category_name: "Món chính",
    code: "BOWL-01",
    name: "Cơm gà rau thơm",
    description: "Cơm gà nướng dùng kèm rau xanh và sốt chanh.",
    img_url: null,
    is_available: true,
    price: { price_id: 1, amount: "145000.00", currency: "VND", effective_from: null, effective_to: null },
    preorder: { enabled: true, cutoff_minutes: 45, quota_per_day: null, requires_preview_validation: true },
    created_at: null,
    updated_at: null,
  },
  {
    item_id: 102,
    category_id: 1,
    category_name: "Món chính",
    code: "NOODLE-02",
    name: "Mì xào mè",
    description: "Mì xào cùng rau thơm, rau củ và mè rang.",
    img_url: null,
    is_available: true,
    price: { price_id: 2, amount: "120000.00", currency: "VND", effective_from: null, effective_to: null },
    preorder: { enabled: true, cutoff_minutes: 30, quota_per_day: null, requires_preview_validation: true },
    created_at: null,
    updated_at: null,
  },
];

const reservation = {
  reservation_id: 501,
  reservation_code: "RSV-DEMO-501",
  start_time: tomorrow,
  end_time: new Date(new Date(tomorrow).getTime() + 90 * 60 * 1000).toISOString(),
  guest_count: 2,
  status: "Confirmed",
  deposit_status: "Pending",
  deposit_required_amount: "20.00",
  deposit_paid_amount: "0.00",
  final_bill_amount: "0.00",
  bill_currency: "USD",
  row_version: 1,
  table_ids: [7],
  guest: { full_name: "Demo Customer", phone: "5550100", email: "demo@example.test", is_snapshot_only: false },
};

function json(data: unknown, status = 200): Response {
  return new Response(JSON.stringify(data), {
    status,
    headers: {
      "Content-Type": "application/json",
      "X-Request-Id": `mock-${Date.now().toString(36)}`,
    },
  });
}

function withRowVersion<T extends { row_version?: number }>(record: T): T {
  return { ...record, row_version: (record.row_version ?? 1) + 1 };
}

export function createMockFetch(): typeof fetch {
  return async (input, init) => {
    const rawUrl = typeof input === "string" ? input : input instanceof URL ? input.toString() : input.url;
    const url = new URL(rawUrl);
    const method = (init?.method ?? "GET").toUpperCase();
    const path = url.pathname;

    if (path === "/api/v1/health") {
      return json({ data: { ok: true, adapter: "mock" } });
    }

    if (path === "/api/v1/auth/customer/login" && method === "POST") {
      return json({
        data: {
          auth_mode: "customer_access_session",
          token_type: "opaque",
          auth_header: "X-Customer-Token",
          access_token: "dev-customer-token",
          access_session_id: 9001,
          session_id: "dev-session",
          expires_at_utc: inOneHour,
          user: { user_id: 77, full_name: "Demo Customer", email: "demo@example.test", phone: "5550100" },
        },
      });
    }

    if (path === "/api/v1/auth/customer/me" || path === "/api/v1/auth/customer/refresh") {
      return json({
        data: {
          auth_mode: "customer_access_session",
          token_type: "opaque",
          auth_header: "X-Customer-Token",
          access_token: "dev-customer-token",
          access_session_id: 9001,
          session_id: "dev-session",
          expires_at_utc: inOneHour,
          user: { user_id: 77, full_name: "Demo Customer", email: "demo@example.test", phone: "5550100" },
        },
      });
    }

    if (path === "/api/v1/auth/customer/logout") {
      return json({ data: { revoked: true, access_session_id: 9001 } });
    }

    if (path === "/api/v1/menu/categories") {
      return json({
        data: [{ category_id: 1, name: "Món chính", description: "Các món phục vụ trong ngày", sort_order: 1, items: menuItems }],
        meta: { count: 1, service_time: null, preorder_only: false },
      });
    }

    if (path === "/api/v1/menu/items" && method === "GET") {
      return json({
        data: menuItems,
        meta: {
          current_page: 1,
          per_page: 20,
          from: 1,
          to: menuItems.length,
          total: menuItems.length,
          last_page: 1,
          has_more_pages: false,
          service_time: now.toISOString(),
          filters: { category_id: null, preorder_only: false, q: null },
        },
      });
    }

    const itemMatch = path.match(/^\/api\/v1\/menu\/items\/(\d+)$/);
    if (itemMatch) {
      const item = menuItems.find((candidate) => candidate.item_id === Number(itemMatch[1])) ?? menuItems[0];
      return json({ data: item, meta: { service_time: now.toISOString() } });
    }

    if (path === "/api/v1/tables/available") {
      return json({
        data: [
          { table_id: 7, branch_id: 1, table_no: "Bàn 7", capacity: 2, status: "Available", row_version: 1 },
          { table_id: 8, branch_id: 1, table_no: "Bàn 8", capacity: 4, status: "Available", row_version: 1 },
        ],
        meta: { count: 2, timezone: "UTC", suggestions: [] },
      });
    }

    if (path === "/api/v1/table-holds" && method === "POST") {
      return json({
        data: {
          hold_id: "hold-dev-1",
          session_hash: null,
          start_time: tomorrow,
          end_time: new Date(new Date(tomorrow).getTime() + 90 * 60 * 1000).toISOString(),
          duration_minutes: 90,
          hold_status: "Holding",
          confirmed_reservation_id: null,
          row_version: 1,
          expire_at: inOneHour,
          tables: [{ table_id: 7, branch_id: 1, table_no: "Bàn 7", capacity: 2, status: "Available", row_version: 1 }],
        },
      });
    }

    if (path === "/api/v1/reservations" && method === "GET") {
      return json({ data: [reservation], meta: { access_scope: "customer", pagination: { current_page: 1, last_page: 1, per_page: 20, total: 1, count: 1 } } });
    }

    if (path === "/api/v1/reservations" && method === "POST") {
      return json({ data: withRowVersion(reservation) }, 201);
    }

    const reservationMatch = path.match(/^\/api\/v1\/reservations\/(\d+)/);
    if (reservationMatch) {
      if (path.endsWith("/deposit-preview")) {
        return json({ data: { reservation, deposit: { amount_due: "20.00", currency: "USD", status: "Pending" } } });
      }
      if (path.includes("/deposit/payment-sessions")) {
        return json({
          data: {
            reservation_id: 501,
            deposit: { amount_due: "20.00", currency: "USD" },
            payment_session: {
              deposit_payment_session_id: 701,
              reservation_id: 501,
              provider_code: "MockPay",
              provider_session_code: "mock-deposit-session",
              amount: "20.00",
              currency: "USD",
              session_status: "Pending",
              settlement_status: "NotApplied",
              row_version: 1,
            },
          },
        });
      }
      if (path.endsWith("/active-order")) {
        return json({ data: { reservation_id: 501, active_order: null } });
      }
      if (path.endsWith("/bill-preview")) {
        return json({ data: { reservation_id: 501, active_order: null, bill_preview: { total: "0.00", currency: "USD" } } });
      }
      if (path.endsWith("/bill")) {
        return json({ data: { reservation_id: 501, bill: { total: "0.00", currency: "USD" }, settlement: {}, orders: [], workflow: {} } });
      }
      if (path.includes("/bill/payment-sessions")) {
        return json({
          data: {
            reservation_id: 501,
            bill: { total: "0.00", currency: "USD" },
            payment_session: {
              deposit_payment_session_id: 801,
              bill_payment_session_id: 801,
              reservation_id: 501,
              provider_code: "MockPay",
              provider_session_code: "mock-bill-session",
              amount: "0.00",
              currency: "USD",
              session_status: "Pending",
              settlement_status: "NotApplied",
              row_version: 1,
            },
          },
        });
      }
      if (path.endsWith("/preorder")) {
        return json({ data: { reservation, items: [], totals: { total: "0.00", currency: "USD" } }, meta: { action: "show", access_scope: "customer" } });
      }
      return json({ data: reservation });
    }

    if (path === "/api/v1/waiting-list") {
      return json({ data: [], meta: { access_scope: "customer" } });
    }

    if (path === "/api/v1/me/loyalty") {
      return json({ data: { user: { user_id: 77, full_name: "Demo Customer", email: "demo@example.test", phone: "5550100", total_points: 120, current_tier: null, next_tier: null }, transactions: [] } });
    }

    if (path === "/api/v1/me/vouchers") {
      return json({ data: [], meta: { total: 0 } });
    }

    if (path === "/api/v1/me/data-export") {
      return json({ data: { requested_at: now.toISOString(), customer: { user_id: 77 } } });
    }

    if (path === "/api/v1/me/privacy-requests") {
      return json({ data: [], meta: { total: 0 } });
    }

    return json({ message: "Mock route not implemented.", error_code: "mock_not_found", request_id: "mock" }, 404);
  };
}
