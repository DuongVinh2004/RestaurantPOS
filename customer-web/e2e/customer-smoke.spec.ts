import type { Page, Route } from "@playwright/test";
import { expect, test } from "@playwright/test";

function addMinutes(date: Date, minutes: number): Date {
  return new Date(date.getTime() + minutes * 60_000);
}

const reservationStart = addMinutes(new Date(), 24 * 60).toISOString();
const reservationEnd = addMinutes(new Date(reservationStart), 90).toISOString();
const holdExpiresAt = addMinutes(new Date(reservationStart), 60).toISOString();
const sessionExpiresAt = addMinutes(new Date(), 7 * 24 * 60).toISOString();

const reservation = {
  reservation_id: 501,
  reservation_code: "RSV-DEMO-501",
  start_time: reservationStart,
  end_time: reservationEnd,
  guest_count: 2,
  status: "Confirmed",
  deposit_status: "Pending",
  deposit_required_amount: "20.00",
  deposit_paid_amount: "0.00",
  final_bill_amount: "0.00",
  bill_currency: "USD",
  row_version: 1,
  table_ids: [7],
};

async function fulfillJson(route: Route, body: unknown, status = 200) {
  await route.fulfill({
    status,
    contentType: "application/json",
    body: JSON.stringify(body),
  });
}

async function mockCustomerApi(page: Page) {
  await page.route("**/api/v1/**", async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const path = url.pathname;
    const method = request.method().toUpperCase();

    if (path === "/api/v1/health") {
      return fulfillJson(route, { data: { ok: true } });
    }

    if (path === "/api/v1/menu/categories" && method === "GET") {
      return fulfillJson(route, {
        data: [
          {
            category_id: 1,
            name: "Lunch",
            description: "Daytime favorites",
            sort_order: 1,
            items: [],
          },
        ],
        meta: {
          count: 1,
          service_time: null,
          preorder_only: false,
        },
      });
    }

    if (path === "/api/v1/menu/items" && method === "GET") {
      return fulfillJson(route, {
        data: [
          {
            item_id: 101,
            category_id: 1,
            category_name: "Lunch",
            name: "Herb Chicken Bowl",
            description: "Grilled chicken, greens, rice, and lime dressing.",
            img_url: null,
            is_available: true,
            price: {
              amount: "14.50",
              currency: "USD",
            },
            preorder: {
              enabled: true,
            },
          },
        ],
        meta: {
          current_page: 1,
          per_page: 20,
          total: 1,
          last_page: 1,
          has_more_pages: false,
        },
      });
    }

    if (path === "/api/v1/tables/available" && method === "GET") {
      return fulfillJson(route, {
        data: [
          {
            table_id: 7,
            branch_id: 1,
            table_code: "Table 7",
            seats: 4,
            status: "Available",
            row_version: 1,
          },
        ],
        meta: {
          count: 1,
          timezone: "UTC",
          suggestions: [],
        },
      });
    }

    if (path === "/api/v1/table-holds" && method === "POST") {
      return fulfillJson(route, {
        data: {
          hold_id: "hold-dev-1",
          session_hash: null,
          start_time: reservationStart,
          end_time: reservationEnd,
          duration_minutes: 90,
          hold_status: "Holding",
          confirmed_reservation_id: null,
          row_version: 1,
          expire_at: holdExpiresAt,
          tables: [
            {
              table_id: 7,
              branch_id: 1,
              table_code: "Table 7",
              seats: 4,
              status: "Available",
              row_version: 1,
            },
          ],
        },
      });
    }

    if (path === "/api/v1/auth/customer/login" && method === "POST") {
      return fulfillJson(route, {
        data: {
          auth_mode: "customer_access_session",
          token_type: "opaque",
          auth_header: "X-Customer-Token",
          access_token: "dev-customer-token",
          access_session_id: 9001,
          session_id: "dev-session",
          expires_at_utc: sessionExpiresAt,
          user: {
            user_id: 77,
            full_name: "Demo Customer",
            email: "demo@example.test",
            phone: "5550100",
          },
        },
      });
    }

    if ((path === "/api/v1/auth/customer/me" && method === "GET") || (path === "/api/v1/auth/customer/refresh" && method === "POST")) {
      return fulfillJson(route, {
        data: {
          auth_mode: "customer_access_session",
          token_type: "opaque",
          auth_header: "X-Customer-Token",
          access_token: "dev-customer-token",
          access_session_id: 9001,
          session_id: "dev-session",
          expires_at_utc: sessionExpiresAt,
          user: {
            user_id: 77,
            full_name: "Demo Customer",
            email: "demo@example.test",
            phone: "5550100",
          },
        },
      });
    }

    if (path === "/api/v1/auth/customer/logout" && method === "POST") {
      return fulfillJson(route, {
        data: {
          revoked: true,
          access_session_id: 9001,
        },
      });
    }

    if (path === "/api/v1/reservations" && method === "GET") {
      return fulfillJson(route, {
        data: [reservation],
        meta: {
          access_scope: "customer",
          pagination: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 1,
            count: 1,
          },
        },
      });
    }

    return fulfillJson(
      route,
      {
        message: "Playwright mock route not implemented.",
        error_code: "playwright_mock_not_found",
        request_id: "playwright-mock",
      },
      404,
    );
  });
}

test.beforeEach(async ({ page }) => {
  await mockCustomerApi(page);
});

test("menu home loads with mock-backed customer content", async ({ page }) => {
  await page.goto("/");

  await expect(page.getByRole("heading", { name: "Browse the menu before your visit." })).toBeVisible();
  await expect(page.getByRole("main").getByRole("link", { name: "Find a table" })).toBeVisible();
  await expect(page.getByText("Herb Chicken Bowl")).toBeVisible();
});

test("booking can create a hold and continue to reservation", async ({ page }) => {
  await page.goto("/booking");

  await page.getByRole("button", { name: "Search tables" }).click();
  await expect(page.getByRole("button", { name: /Table 7/i })).toBeVisible();

  await page.getByRole("button", { name: /Table 7/i }).click();
  await page.getByRole("button", { name: "Create hold" }).click();

  const continueLink = page.getByRole("link", { name: "Continue to reservation" });
  await expect(continueLink).toBeVisible();
  await expect(continueLink).toHaveAttribute("href", /hold_id=hold-dev-1/);
});

test("login redirects to reservations with the mocked customer session", async ({ page }) => {
  await page.goto("/login");

  await page.getByLabel("Email, phone, or customer id").fill("demo@example.test");
  await page.getByLabel("Password").fill("password123");
  await page.getByRole("button", { name: "Sign in" }).click();

  await expect(page).toHaveURL(/\/reservations$/);
  await expect(page.getByRole("heading", { name: "Reservations" })).toBeVisible();
  await expect(page.getByText("RSV-DEMO-501")).toBeVisible();
});
