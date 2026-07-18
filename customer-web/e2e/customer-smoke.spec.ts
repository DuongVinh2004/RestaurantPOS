import type { Page, Route } from "@playwright/test";
import { expect, test } from "@playwright/test";

function addMinutes(date: Date, minutes: number): Date {
  return new Date(date.getTime() + minutes * 60_000);
}

function toMoney(amount: number): string {
  return amount.toFixed(2);
}

const reservationStart = addMinutes(new Date(), 24 * 60).toISOString();
const reservationEnd = addMinutes(new Date(reservationStart), 90).toISOString();
const holdExpiresAt = addMinutes(new Date(reservationStart), 60).toISOString();
const sessionExpiresAt = addMinutes(new Date(), 7 * 24 * 60).toISOString();
const preorderUnitPrice = 89_000;

type PreorderDraftItem = {
  item_id: number;
  quantity: number;
};

const baseReservation = {
  reservation_id: 501,
  reservation_code: "RSV-MS-501",
  start_time: reservationStart,
  end_time: reservationEnd,
  guest_count: 2,
  status: "Confirmed",
  deposit_status: "Pending",
  deposit_required_amount: "200000.00",
  deposit_paid_amount: "0.00",
  final_bill_amount: "0.00",
  bill_currency: "VND",
  row_version: 1,
  table_ids: [7],
};

function createReservationSummary(overrides: Record<string, unknown> = {}) {
  return {
    ...baseReservation,
    access_scope: "owner",
    deposit_status: "NotRequired",
    deposit_required_amount: "0.00",
    deposit_paid_amount: "0.00",
    customer_self_service: {
      can_attempt_cancel: true,
      can_attempt_reschedule: true,
    },
    hold_summary: {
      current: {
        hold_id: "hold-dev-1",
        status: "Holding",
        expires_at: holdExpiresAt,
        table_ids: [7],
        confirmed_reservation_id: 501,
      },
    },
    ...overrides,
  };
}

function createReservationPreorderPayload(
  items: PreorderDraftItem[],
  overrides: {
    reservation_row_version?: number;
    order_row_version?: number | null;
  } = {},
) {
  const quantity = items.reduce((total, item) => total + item.quantity, 0);
  const subtotal = toMoney(quantity * preorderUnitPrice);

  return {
    reservation_id: 501,
    reservation_code: "RSV-MS-501",
    reservation_status: "Confirmed",
    reservation_row_version: overrides.reservation_row_version ?? 1,
    pre_order: {
      present: items.length > 0,
      order_id: items.length > 0 ? 801 : null,
      order_row_version: items.length > 0 ? (overrides.order_row_version ?? 1) : null,
      order_status: items.length > 0 ? "Open" : null,
      service_time: reservationStart,
      currency: "VND",
      lines: items.map((item, index) => ({
        order_item_id: 900 + index,
        item_id: item.item_id,
        quantity: item.quantity,
        status: "Open",
        name: "Cơm gà lá sen",
        code: "MS-COM-GA-LA-SEN",
        unit_price: toMoney(preorderUnitPrice),
        line_total: toMoney(item.quantity * preorderUnitPrice),
        currency: "VND",
        notes: null,
        updated_at: null,
      })),
      totals: {
        item_count: items.length,
        quantity,
        subtotal,
      },
      normalized_pre_order_items: items,
    },
    management_policy: {
      can_manage: true,
      reservation_status: "Confirmed",
      cutoff_minutes: 30,
      service_start: reservationStart,
      manage_until: addMinutes(new Date(reservationStart), -30).toISOString(),
      reasons: [],
    },
  };
}

function createMenuPreorderPreview(items: PreorderDraftItem[]) {
  const quantity = items.reduce((total, item) => total + item.quantity, 0);

  return {
    totals: {
      quantity,
      item_count: items.length,
      subtotal: toMoney(quantity * preorderUnitPrice),
      currency: "VND",
    },
    warnings: [],
    policy: {
      message: "Nhà hàng sẽ xác nhận lại giỏ món trước khi lưu vào lịch đặt.",
    },
  };
}

async function fulfillJson(route: Route, body: unknown, status = 200) {
  await route.fulfill({
    status,
    contentType: "application/json",
    body: JSON.stringify(body),
  });
}

function readJsonBody(route: Route): unknown {
  const body = route.request().postData();

  return body ? JSON.parse(body) : null;
}

async function mockCustomerApi(page: Page) {
  let reservationState = createReservationSummary();
  let reservationPreorderState = createReservationPreorderPayload([], {
    reservation_row_version: reservationState.row_version,
    order_row_version: null,
  });

  await page.route("**/api/v1/**", async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const path = url.pathname;
    const method = request.method().toUpperCase();

    if (path === "/api/v1/health") {
      return fulfillJson(route, { data: { ok: true } });
    }

    if (path === "/api/v1/restaurant/branches" && method === "GET") {
      return fulfillJson(route, {
        data: [
          {
            branch_id: 1,
            branch_code: "MS-HK",
            branch_name: "Mộc Sen Bistro - Hoàn Kiếm",
            timezone: "Asia/Ho_Chi_Minh",
            business_hours: [0, 1, 2, 3, 4, 5, 6].map((dayOfWeek) => ({
              day_of_week: dayOfWeek,
              periods: [{ start_time: "09:00", end_time: "22:00" }],
            })),
            today_hours: {
              day_of_week: new Date().getDay(),
              periods: [{ start_time: "09:00", end_time: "22:00" }],
              is_closed: false,
            },
            current_status: {
              is_open: true,
              reason: "business_hours",
              checked_at_local: "2026-04-30T10:00:00+07:00",
              timezone: "Asia/Ho_Chi_Minh",
            },
          },
        ],
        meta: {
          action: "restaurant_branches_list",
        },
      });
    }

    if (path === "/api/v1/restaurant/profile" && method === "GET") {
      return fulfillJson(route, {
        data: {
          branch_id: 1,
          branch_code: "MS-HK",
          branch_name: "Mộc Sen Bistro - Hoàn Kiếm",
          timezone: "Asia/Ho_Chi_Minh",
          business_hours: [0, 1, 2, 3, 4, 5, 6].map((dayOfWeek) => ({
            day_of_week: dayOfWeek,
            periods: [{ start_time: "09:00", end_time: "22:00" }],
          })),
          today_hours: {
            day_of_week: new Date().getDay(),
            periods: [{ start_time: "09:00", end_time: "22:00" }],
            is_closed: false,
          },
          current_status: {
            is_open: true,
            reason: "business_hours",
            checked_at_local: "2026-04-30T10:00:00+07:00",
            timezone: "Asia/Ho_Chi_Minh",
          },
        },
        meta: {
          action: "restaurant_profile_show",
        },
      });
    }

    if (path === "/api/v1/menu/categories" && method === "GET") {
      return fulfillJson(route, {
        data: [
          {
            category_id: 1,
            name: "Món chính",
            description: "Các món phục vụ trong ngày",
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
            category_name: "Món chính",
            name: "Cơm gà lá sen",
            description: "Gà áp chảo, cơm dẻo, sốt gừng nhẹ và rau củ theo mùa.",
            img_url: "/customer-web/menu/com-ga-la-sen.jpg",
            is_available: true,
            price: {
              amount: "89000.00",
              currency: "VND",
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

    if (path === "/api/v1/menu/preorder/preview" && method === "POST") {
      const body = readJsonBody(route) as
        | { pre_order_items?: PreorderDraftItem[] | null }
        | null;

      return fulfillJson(route, {
        data: createMenuPreorderPreview(body?.pre_order_items ?? []),
      });
    }

    if (path === "/api/v1/tables/available" && method === "GET") {
      return fulfillJson(route, {
        data: [
          {
            table_id: 7,
            branch_id: 1,
            table_code: "Bàn 7",
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
              table_code: "Bàn 7",
              seats: 4,
              status: "Available",
              row_version: 1,
            },
          ],
        },
      });
    }

    if (path === "/api/v1/table-holds/hold-dev-1" && method === "GET") {
      return fulfillJson(route, {
        data: {
          hold_id: "hold-dev-1",
          session_hash: null,
          start_time: reservationStart,
          end_time: reservationEnd,
          duration_minutes: 90,
          hold_status: "Holding",
          confirmed_reservation_id: reservationState.reservation_id,
          row_version: 1,
          expire_at: holdExpiresAt,
          tables: [
            {
              table_id: 7,
              branch_id: 1,
              table_code: "Bàn 7",
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
            full_name: "Nguyễn Minh Anh",
            email: "minh.anh@mocsen.example",
            phone: "0909000001",
          },
        },
      });
    }

    if (
      (path === "/api/v1/auth/customer/me" && method === "GET") ||
      (path === "/api/v1/auth/customer/refresh" && method === "POST")
    ) {
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
            full_name: "Nguyễn Minh Anh",
            email: "minh.anh@mocsen.example",
            phone: "0909000001",
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
        data: [reservationState],
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

    if (path === "/api/v1/reservations" && method === "POST") {
      const body = readJsonBody(route) as
        | {
            guest_count?: number;
            start_time?: string;
            end_time?: string;
          }
        | null;

      reservationState = createReservationSummary({
        guest_count: body?.guest_count ?? 2,
        start_time: body?.start_time ?? reservationStart,
        end_time: body?.end_time ?? reservationEnd,
      });
      reservationPreorderState = createReservationPreorderPayload([], {
        reservation_row_version: reservationState.row_version,
        order_row_version: null,
      });

      return fulfillJson(route, { data: reservationState }, 201);
    }

    if (/^\/api\/v1\/reservations\/\d+\/?$/.test(path) && method === "GET") {
      return fulfillJson(route, { data: reservationState });
    }

    if (/^\/api\/v1\/reservations\/\d+\/preorder\/?$/.test(path) && method === "GET") {
      return fulfillJson(route, { data: reservationPreorderState });
    }

    if (/^\/api\/v1\/reservations\/\d+\/preorder\/preview\/?$/.test(path) && method === "POST") {
      const body = readJsonBody(route) as
        | { pre_order_items?: PreorderDraftItem[] | null }
        | null;

      return fulfillJson(route, {
        data: createReservationPreorderPayload(body?.pre_order_items ?? [], {
          reservation_row_version: reservationState.row_version,
          order_row_version: reservationPreorderState.pre_order.order_row_version,
        }),
      });
    }

    if (/^\/api\/v1\/reservations\/\d+\/preorder\/?$/.test(path) && method === "PUT") {
      const body = readJsonBody(route) as
        | { pre_order_items?: PreorderDraftItem[] | null }
        | null;
      const items = body?.pre_order_items ?? [];
      const nextReservationRowVersion = reservationState.row_version + 1;
      const nextOrderRowVersion =
        reservationPreorderState.pre_order.order_row_version === null
          ? 1
          : reservationPreorderState.pre_order.order_row_version + 1;

      reservationState = createReservationSummary({
        guest_count: reservationState.guest_count,
        start_time: reservationState.start_time,
        end_time: reservationState.end_time,
        row_version: nextReservationRowVersion,
      });
      reservationPreorderState = createReservationPreorderPayload(items, {
        reservation_row_version: nextReservationRowVersion,
        order_row_version: nextOrderRowVersion,
      });

      return fulfillJson(route, { data: reservationPreorderState });
    }

    if (/^\/api\/v1\/reservations\/\d+\/deposit-preview\/?$/.test(path) && method === "GET") {
      return fulfillJson(route, {
        data: {
          status: "NotRequired",
          currency: "VND",
          amount_due: "0.00",
          outstanding_amount: "0.00",
          self_service: {
            supported: true,
            actionable: false,
            can_acknowledge: false,
            can_submit_intent: false,
            can_revoke_intent: false,
            can_create_payment_session: false,
            next_step: null,
          },
        },
      });
    }

    if (/^\/api\/v1\/reservations\/\d+\/active-order\/?$/.test(path) && method === "GET") {
      return fulfillJson(route, { data: null });
    }

    if (/^\/api\/v1\/reservations\/\d+\/bill-preview\/?$/.test(path) && method === "GET") {
      return fulfillJson(route, {
        data: {
          totals: {
            total: "0.00",
            outstanding_amount: "0.00",
            currency: "VND",
          },
          payment_status: "Unpaid",
          self_payment: {
            supported: true,
            available: false,
            disabled_reason: "Chưa có khoản nào cần thanh toán.",
            next_step: "awaiting_staff_finalization",
            requires_locked_bill: false,
            awaiting_staff_finalization: true,
          },
        },
      });
    }

    if (/^\/api\/v1\/reservations\/\d+\/bill\/?$/.test(path) && method === "GET") {
      return fulfillJson(route, { data: null });
    }

    return fulfillJson(
      route,
      {
        message: `Playwright mock route not implemented: ${method} ${path}`,
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

test("home loads with hero heading and booking CTA", async ({ page }) => {
  await page.goto("/");

  await expect(
    page.getByRole("heading", { name: "Chọn món ngon, giữ bàn đúng giờ" }),
  ).toBeVisible();
  await expect(
    page.getByRole("main").getByRole("link", { name: "Tìm bàn", exact: true }),
  ).toBeVisible();
});

test("menu page loads with search box and item list", async ({ page }) => {
  await page.goto("/menu");

  await expect(
    page.getByRole("searchbox", { name: "Tìm trong thực đơn" }),
  ).toBeVisible();
  await expect(page.getByText("Cơm gà lá sen")).toBeVisible();
});

test("booking can create a hold and continue to reservation", async ({ page }) => {
  await page.goto("/booking");

  await page.getByRole("button", { name: "Tìm bàn", exact: true }).click();
  const tableOption = page.getByRole("button", { name: /^Chọn .+ - Bàn 7$/ });
  await expect(tableOption).toBeVisible();
  await expect(tableOption).toHaveAttribute("aria-pressed", "false");

  await tableOption.click();
  await expect(tableOption).toHaveAttribute("aria-pressed", "true");
  await expect(page.getByText("Mã giữ bàn")).toBeVisible();
  await expect(page.getByText("hold-dev-1")).toBeVisible();

  const continueLink = page.getByRole("link", { name: "Xác nhận thông tin đặt bàn" });
  await expect(continueLink).toBeVisible();
  await expect(continueLink).toHaveAttribute("href", /hold_id=hold-dev-1/);
});

test("login redirects to reservations with the mocked customer session", async ({ page }) => {
  await page.goto("/login");

  await page
    .getByLabel("Email, số điện thoại hoặc mã khách hàng")
    .fill("minh.anh@mocsen.example");
  await page.getByLabel("Mật khẩu").fill("password123");
  await page.getByRole("button", { name: "Đăng nhập" }).click();

  await expect(page).toHaveURL(/\/reservations$/);
  await expect(page.getByRole("heading", { name: "Lịch đặt" })).toBeVisible();
  await expect(page.getByText("RSV-MS-501", { exact: true })).toBeVisible();
});

test("booking can preserve preorder draft, confirm reservation, and attach preorder on detail", async ({
  page,
}) => {
  await page.goto("/menu");
  // Ensure the branch has loaded before interacting with the cart
  await expect(page.getByText("Mộc Sen Bistro - Hoàn Kiếm").first()).toBeVisible();
  
  const addPreorderItem = page.getByRole("button", { name: "Thêm món", exact: true }).first();
  await expect(addPreorderItem).toBeVisible();
  
  await addPreorderItem.click();
  await expect(page.getByRole("heading", { name: "1 món đã chọn" })).toBeVisible();
  
  await addPreorderItem.click();
  await expect(page.getByRole("heading", { name: "2 món đã chọn" })).toBeVisible();

  await page.goto("/booking");

  await page.getByRole("button", { name: "Tìm bàn", exact: true }).click();
  await page.getByRole("button", { name: /^Chọn .+ - Bàn 7$/ }).click();
  await expect(page.getByText("Mã giữ bàn")).toBeVisible();
  await page.getByRole("link", { name: "Xác nhận thông tin đặt bàn" }).click();

  await expect(page).toHaveURL(/\/booking\/preorder\?.*hold_id=hold-dev-1/);
  const reservationContinueLink = page.locator('a[href^="/reservations/new?"]', {
    hasText: "Tiếp tục đặt bàn",
  });
  await expect(reservationContinueLink).toBeVisible();
  await reservationContinueLink.click();

  await expect(
    page.getByRole("heading", { name: "Thông tin liên hệ & Thanh toán cọc" }),
  ).toBeVisible();
  await expect(page.getByText("Món đặt trước").first()).toBeVisible();
  await expect(page.getByText("Cơm gà lá sen").first()).toBeVisible();
  await page.getByLabel("Tên khách").fill("Nguyễn Minh Anh");
  await page.getByLabel("Số điện thoại").fill("0909000001");

  await expect(page.getByRole("button", { name: "Xem trước món" })).toHaveCount(0);

  await page.getByRole("button", { name: "Hoàn tất đặt bàn" }).first().click();
  await expect(page).toHaveURL(/\/reservations\/501\?next=preorder#preorder$/);
  await expect(
    page.getByText("Mộc Sen đã giữ giỏ món của bạn. Bạn có thể xem trước và lưu món đặt trước khi sẵn sàng."),
  ).toBeVisible();
  await expect(page.getByLabel("Số lượng Cơm gà lá sen")).toHaveValue("2");

  await page.getByRole("button", { name: "Xem trước món" }).click();
  await expect(page.getByText("Bản xem trước")).toBeVisible();
  await page.getByRole("button", { name: "Cập nhật món đặt trước" }).click();

  await expect(page.getByText("2 món đã ghi nhận")).toBeVisible();
  await expect(page.getByText("Cơm gà lá sen").first()).toBeVisible();
});
