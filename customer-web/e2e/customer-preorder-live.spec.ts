import path from "node:path";
import { readFileSync } from "node:fs";
import { expect, test, type APIRequestContext, type APIResponse, type Page, type Response as PlaywrightResponse } from "@playwright/test";

type LiveConfig = {
  apiBaseUrl: string;
  identifier: string;
  password: string;
  guestName: string;
  guestPhone: string;
  guestEmail?: string;
};

type AuthEnvelope = {
  data: {
    access_token?: string | null;
    session_id: string;
    expires_at_utc?: string | null;
  };
};

type Manifest = {
  auth?: {
    admin?: {
      api_key?: string | null;
    };
    customer_primary?: {
      username?: string | null;
      password?: string | null;
    };
  };
  branch?: {
    branch_id?: number;
  };
  menu?: {
    items?: Record<
      string,
      {
        item_id?: number;
      }
    >;
  };
  scenarios?: {
    availability_hold_reservation?: {
      from_utc?: string;
      to_utc?: string;
      guest_count?: number;
      preferred_table_ids?: number[];
    };
  };
};

type AvailableTablesEnvelope = {
  data?: Array<{
    table_id?: number;
    table_code?: string | null;
  }>;
};

type HoldEnvelope = {
  data: {
    hold_id: string;
    hold_status: string;
    start_time: string;
    duration_minutes: number;
    expire_at: string;
    tables?: Array<{ table_id: number }>;
  };
};

type MenuItemsEnvelope = {
  data?: Array<{
    item_id?: number;
    name?: string | null;
    is_available?: boolean | null;
    preorder?: {
      enabled?: boolean | null;
    } | null;
  }>;
};

type ReservationEnvelope = {
  data: {
    reservation_id: number;
  };
};

type AdminMenuItemEnvelope = {
  data: {
    item_id: number;
    category_id: number;
    code: string;
    name: string;
    description?: string | null;
    img_url?: string | null;
    is_available?: boolean | null;
    is_preorder_enabled?: boolean | null;
    preorder_quota_per_day?: number | null;
    preorder_cutoff_minutes?: number | null;
  };
};

type ReservationPreorderEnvelope = {
  data: {
    pre_order: {
      present: boolean;
      totals: {
        quantity: number;
      };
    };
  };
};

type BrowserAuth = {
  token: string;
  sessionId: string;
  expiresAtUtc: string | null;
};

const guestNamePattern = /^(Guest name|T\u00ean kh\u00e1ch)$/i;
const guestPhonePattern = /^(Phone|S\u1ed1 \u0111i\u1ec7n tho\u1ea1i)$/i;
const guestEmailPattern = /^Email$/i;
const previewPreorderPattern = /^(Preview preorder|Xem tr\u01b0\u1edbc m\u00f3n)$/i;
const createReservationPattern = /^(Create reservation|T\u1ea1o l\u1ecbch \u0111\u1eb7t)$/i;
const updatePreorderPattern = /^(Update preorder|C\u1eadp nh\u1eadt m\u00f3n \u0111\u1eb7t tr\u01b0\u1edbc)$/i;
const clearPreorderPattern = /^(Clear preorder|X\u00f3a m\u00f3n \u0111\u1eb7t tr\u01b0\u1edbc)$/i;

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function formatLocalDateTimeInput(value: string): string {
  const date = new Date(value);
  const pad = (part: number) => String(part).padStart(2, "0");

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function liveApiUrl(config: LiveConfig, pathName: string): string {
  return `${config.apiBaseUrl.replace(/\/+$/, "")}${pathName}`;
}

function newIdempotencyKey(scope: string): string {
  return `${scope}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

function readLiveConfig(manifest: Manifest): LiveConfig {
  if (!["1", "true", "yes", "on"].includes((process.env.NEXT_PUBLIC_FEATURE_PREORDER ?? "").toLowerCase())) {
    throw new Error("NEXT_PUBLIC_FEATURE_PREORDER=true is required for preorder live proof.");
  }

  const identifier = process.env.CUSTOMER_WEB_LIVE_IDENTIFIER?.trim() || manifest.auth?.customer_primary?.username?.trim();
  const password = process.env.CUSTOMER_WEB_LIVE_PASSWORD?.trim() || manifest.auth?.customer_primary?.password?.trim();

  if (!identifier) {
    throw new Error("CUSTOMER_WEB_LIVE_IDENTIFIER or auth.customer_primary.username is required for preorder live proof.");
  }

  if (!password) {
    throw new Error("CUSTOMER_WEB_LIVE_PASSWORD or auth.customer_primary.password is required for preorder live proof.");
  }

  return {
    apiBaseUrl:
      process.env.NEXT_PUBLIC_API_BASE_URL ??
      process.env.CUSTOMER_WEB_LIVE_API_BASE_URL ??
      "http://127.0.0.1:8000",
    identifier,
    password,
    guestName: process.env.CUSTOMER_WEB_LIVE_GUEST_NAME ?? "Preorder Live Customer",
    guestPhone: process.env.CUSTOMER_WEB_LIVE_GUEST_PHONE ?? "5550100",
    guestEmail: process.env.CUSTOMER_WEB_LIVE_GUEST_EMAIL,
  };
}

function readManifest(): Manifest {
  const manifestPath = path.resolve(process.cwd(), "..", "storage", "app", "uat", "scenario-pack.json");

  return JSON.parse(readFileSync(manifestPath, "utf8")) as Manifest;
}

function readAdminApiKey(manifest: Manifest): string {
  const apiKey = manifest.auth?.admin?.api_key?.trim();

  if (!apiKey) {
    throw new Error("UAT manifest is missing auth.admin.api_key for preorder live setup.");
  }

  return apiKey;
}

function readSeedMenuItemIds(manifest: Manifest): number[] {
  const ids = Object.values(manifest.menu?.items ?? {})
    .map((candidate) => Number(candidate.item_id))
    .filter((candidate): candidate is number => Number.isInteger(candidate) && candidate > 0);

  if (ids.length === 0) {
    throw new Error("UAT manifest is missing seeded menu item ids for preorder live setup.");
  }

  return ids;
}

function readAvailabilityFixture(manifest: Manifest) {
  const scenario = manifest.scenarios?.availability_hold_reservation;
  const startTime = scenario?.from_utc;
  const endTime = scenario?.to_utc;
  const guestCount = Number(scenario?.guest_count);
  const preferredTableIds = (scenario?.preferred_table_ids ?? []).filter(
    (value): value is number => Number.isInteger(value) && value > 0,
  );

  if (!startTime || Number.isNaN(Date.parse(startTime))) {
    throw new Error("UAT manifest is missing scenarios.availability_hold_reservation.from_utc for preorder live proof.");
  }

  if (!endTime || Number.isNaN(Date.parse(endTime))) {
    throw new Error("UAT manifest is missing scenarios.availability_hold_reservation.to_utc for preorder live proof.");
  }

  if (!Number.isInteger(guestCount) || guestCount <= 0) {
    throw new Error("UAT manifest is missing scenarios.availability_hold_reservation.guest_count for preorder live proof.");
  }

  if (preferredTableIds.length === 0) {
    throw new Error("UAT manifest is missing scenarios.availability_hold_reservation.preferred_table_ids for preorder live proof.");
  }

  return {
    branchId: Number(manifest.branch?.branch_id) || undefined,
    startTime,
    endTime,
    guestCount,
    preferredTableIds,
  };
}

async function expectOk(response: APIResponse | PlaywrightResponse, label: string): Promise<void> {
  if (response.ok()) {
    return;
  }

  const body = await response.text().catch(() => "");
  throw new Error(`${label} returned HTTP ${response.status()}${body ? `: ${body.slice(0, 500)}` : ""}`);
}

async function expectOkJson<T>(response: APIResponse | PlaywrightResponse, label: string): Promise<T> {
  await expectOk(response, label);

  return (await response.json()) as T;
}

function customerHeaders(auth: BrowserAuth) {
  return {
    Accept: "application/json",
    "X-Customer-Token": auth.token,
    "X-Session-Id": auth.sessionId,
  };
}

function waitForApi(page: Page, pathName: string, method = "GET"): Promise<PlaywrightResponse> {
  return page.waitForResponse((candidate) => {
    const request = candidate.request();
    const pathname = new URL(candidate.url()).pathname;

    return pathname === pathName && request.method().toUpperCase() === method;
  });
}

function waitForApiWithQuery(
  page: Page,
  pathName: string,
  queryKey: string,
  queryValue: string,
  method = "GET",
): Promise<PlaywrightResponse> {
  return page.waitForResponse((candidate) => {
    const request = candidate.request();
    const url = new URL(candidate.url());

    return (
      url.pathname === pathName &&
      request.method().toUpperCase() === method &&
      url.searchParams.get(queryKey) === queryValue
    );
  });
}

async function loginCustomer(request: APIRequestContext, config: LiveConfig): Promise<BrowserAuth> {
  const response = await request.post(liveApiUrl(config, "/api/v1/auth/customer/login"), {
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    data: {
      identifier: config.identifier,
      password: config.password,
      session_label: "customer-web-preorder-live",
    },
  });
  const payload = await expectOkJson<AuthEnvelope>(response, "POST /api/v1/auth/customer/login");
  const token = payload.data.access_token?.trim();
  const sessionId = payload.data.session_id?.trim();

  if (!token) {
    throw new Error("Customer login did not return an access token for preorder live proof.");
  }

  if (!sessionId) {
    throw new Error("Customer login did not return a session id for preorder live proof.");
  }

  return {
    token,
    sessionId,
    expiresAtUtc: payload.data.expires_at_utc ?? null,
  };
}

async function seedBrowserAuth(page: Page, auth: BrowserAuth) {
  await page.addInitScript((seed) => {
    window.localStorage.setItem("restaurantpos.customer.token.v1", seed.token);

    if (seed.expiresAtUtc) {
      window.localStorage.setItem("restaurantpos.customer.expires.v1", seed.expiresAtUtc);
    }

    window.sessionStorage.setItem("restaurantpos.customer.session-id.v1", seed.sessionId);
  }, auth);
}

function resolveAvailableTable(payload: AvailableTablesEnvelope, preferredTableIds: number[]) {
  const tables = payload.data ?? [];
  const preferred = tables.find((candidate) => preferredTableIds.includes(Number(candidate.table_id)));

  if (preferred?.table_id) {
    return preferred.table_id;
  }

  const first = tables.find((candidate) => Number.isInteger(candidate.table_id) && Number(candidate.table_id) > 0);

  if (!first?.table_id) {
    throw new Error("Live availability did not return any selectable table for preorder proof.");
  }

  return first.table_id;
}

function buildReservationCreateUrl(hold: HoldEnvelope["data"], guestCount: number): string {
  const params = new URLSearchParams({
    hold_id: hold.hold_id,
    hold_status: hold.hold_status,
    hold_expires_at: hold.expire_at,
    tables: (hold.tables ?? []).map((table) => table.table_id).join(","),
    start_time: formatLocalDateTimeInput(hold.start_time),
    duration_minutes: String(hold.duration_minutes),
    guest_count: String(guestCount),
  });

  return `/reservations/new?${params.toString()}`;
}

async function ensurePreorderCatalogFixture(
  request: APIRequestContext,
  config: LiveConfig,
  manifest: Manifest,
): Promise<void> {
  const adminApiKey = readAdminApiKey(manifest);
  const candidateItemIds = readSeedMenuItemIds(manifest);

  for (const itemId of candidateItemIds) {
    const current = await expectOkJson<AdminMenuItemEnvelope>(
      await request.get(liveApiUrl(config, `/api/v1/admin/menu/items/${itemId}`), {
        headers: {
          Accept: "application/json",
          "X-Staff-Key": adminApiKey,
        },
      }),
      `GET /api/v1/admin/menu/items/${itemId}`,
    );

    if (current.data.is_preorder_enabled === true) {
      return;
    }

    await expectOk(
      await request.patch(liveApiUrl(config, `/api/v1/admin/menu/items/${itemId}`), {
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-Staff-Key": adminApiKey,
          "Idempotency-Key": newIdempotencyKey("customer-web-preorder-enable"),
        },
        data: {
          category_id: current.data.category_id,
          code: current.data.code,
          name: current.data.name,
          description: current.data.description ?? "",
          img_url: current.data.img_url,
          is_available: current.data.is_available !== false,
          is_preorder_enabled: true,
          preorder_quota_per_day: current.data.preorder_quota_per_day ?? 20,
          preorder_cutoff_minutes: current.data.preorder_cutoff_minutes && current.data.preorder_cutoff_minutes > 0
            ? current.data.preorder_cutoff_minutes
            : 30,
        },
      }),
      `PATCH /api/v1/admin/menu/items/${itemId}`,
    );

    return;
  }

  throw new Error("No seeded menu item was available for preorder live setup.");
}

test("customer preorder stays closed-loop against the live Laravel runtime", async ({ page, request }) => {
  const manifest = readManifest();
  const config = readLiveConfig(manifest);
  const availabilityFixture = readAvailabilityFixture(manifest);
  const auth = await loginCustomer(request, config);
  const headers = customerHeaders(auth);

  await ensurePreorderCatalogFixture(request, config, manifest);
  await seedBrowserAuth(page, auth);

  const availability = await expectOkJson<AvailableTablesEnvelope>(
    await request.get(liveApiUrl(config, "/api/v1/tables/available"), {
      headers,
      params: {
        from: availabilityFixture.startTime,
        to: availabilityFixture.endTime,
        guest_count: availabilityFixture.guestCount,
        session_id: auth.sessionId,
        suggest: 1,
        ...(availabilityFixture.branchId ? { branch_id: availabilityFixture.branchId } : {}),
      },
    }),
    "GET /api/v1/tables/available",
  );
  const tableId = resolveAvailableTable(availability, availabilityFixture.preferredTableIds);
  const hold = await expectOkJson<HoldEnvelope>(
    await request.post(liveApiUrl(config, "/api/v1/table-holds"), {
      headers: {
        ...headers,
        "Content-Type": "application/json",
        "Idempotency-Key": newIdempotencyKey("customer-web-preorder-hold"),
      },
      data: {
        session_id: auth.sessionId,
        start_time: availabilityFixture.startTime,
        end_time: availabilityFixture.endTime,
        table_ids: [tableId],
        branch_id: availabilityFixture.branchId,
      },
    }),
    "POST /api/v1/table-holds",
  );

  const holdRead = waitForApi(page, `/api/v1/table-holds/${hold.data.hold_id}`);
  const preorderMenuRead = waitForApiWithQuery(page, "/api/v1/menu/items", "preorder_only", "1");
  await page.goto(buildReservationCreateUrl(hold.data, availabilityFixture.guestCount));
  await expectOk(await holdRead, "GET /api/v1/table-holds/{hold_id}");
  const preorderMenu = await expectOkJson<MenuItemsEnvelope>(
    await preorderMenuRead,
    "GET /api/v1/menu/items?preorder_only=1",
  );
  const preorderItem = (preorderMenu.data ?? []).find(
    (candidate) =>
      Number.isInteger(candidate.item_id) &&
      typeof candidate.name === "string" &&
      candidate.name.trim() !== "" &&
      candidate.is_available !== false &&
      candidate.preorder?.enabled !== false,
  );

  expect(preorderItem, "Live menu fixture must expose at least one preorder-enabled menu item.").toBeTruthy();

  await page.getByLabel(guestNamePattern).fill(config.guestName);
  await page.getByLabel(guestPhonePattern).fill(config.guestPhone);

  if (config.guestEmail) {
    await page.getByLabel(guestEmailPattern).fill(config.guestEmail);
  }

  const quantityInput = page.getByLabel(new RegExp(escapeRegExp(preorderItem?.name ?? ""), "i"));
  await quantityInput.fill("2");

  const previewMenuPreorder = waitForApi(page, "/api/v1/menu/preorder/preview", "POST");
  await page.getByRole("button", { name: previewPreorderPattern }).click();
  await expectOk(await previewMenuPreorder, "POST /api/v1/menu/preorder/preview");

  const reservationCreate = waitForApi(page, "/api/v1/reservations", "POST");
  const reservationPreorderSnapshot = page.waitForResponse((response) => {
    const requestMethod = response.request().method().toUpperCase();
    const pathname = new URL(response.url()).pathname;

    return /^\/api\/v1\/reservations\/\d+\/preorder$/.test(pathname) && requestMethod === "GET";
  });
  const reservationPreorderPreview = page.waitForResponse((response) => {
    const requestMethod = response.request().method().toUpperCase();
    const pathname = new URL(response.url()).pathname;

    return /^\/api\/v1\/reservations\/\d+\/preorder\/preview$/.test(pathname) && requestMethod === "POST";
  });
  const reservationPreorderReplace = page.waitForResponse((response) => {
    const requestMethod = response.request().method().toUpperCase();
    const pathname = new URL(response.url()).pathname;

    return /^\/api\/v1\/reservations\/\d+\/preorder$/.test(pathname) && requestMethod === "PUT";
  });

  await page.getByRole("button", { name: createReservationPattern }).click();
  const createdReservation = await expectOkJson<ReservationEnvelope>(
    await reservationCreate,
    "POST /api/v1/reservations",
  );
  const reservationId = createdReservation.data.reservation_id;

  expect(reservationId).toBeGreaterThan(0);
  await expectOk(await reservationPreorderSnapshot, "GET /api/v1/reservations/{id}/preorder during create flow");
  await expectOk(await reservationPreorderPreview, "POST /api/v1/reservations/{id}/preorder/preview during create flow");
  await expectOk(await reservationPreorderReplace, "PUT /api/v1/reservations/{id}/preorder during create flow");
  await expect(page.getByRole("button", { name: clearPreorderPattern })).toBeVisible({ timeout: 15_000 });

  const persistedPreorder = await expectOkJson<ReservationPreorderEnvelope>(
    await request.get(liveApiUrl(config, `/api/v1/reservations/${reservationId}/preorder`), {
      headers,
    }),
    "GET /api/v1/reservations/{id}/preorder after create flow",
  );

  expect(persistedPreorder.data.pre_order.present).toBe(true);
  expect(persistedPreorder.data.pre_order.totals.quantity).toBe(2);

  const detailQuantityInput = page.getByLabel(new RegExp(escapeRegExp(preorderItem?.name ?? ""), "i"));
  await expect(detailQuantityInput).toBeVisible();
  await detailQuantityInput.fill("3");

  const detailPreview = waitForApi(page, `/api/v1/reservations/${reservationId}/preorder/preview`, "POST");
  await page.getByRole("button", { name: previewPreorderPattern }).click();
  await expectOk(await detailPreview, "POST /api/v1/reservations/{id}/preorder/preview");

  const detailReplace = waitForApi(page, `/api/v1/reservations/${reservationId}/preorder`, "PUT");
  await page.getByRole("button", { name: updatePreorderPattern }).click();
  await expectOk(await detailReplace, "PUT /api/v1/reservations/{id}/preorder");

  const updatedPreorder = await expectOkJson<ReservationPreorderEnvelope>(
    await request.get(liveApiUrl(config, `/api/v1/reservations/${reservationId}/preorder`), {
      headers,
    }),
    "GET /api/v1/reservations/{id}/preorder after update",
  );

  expect(updatedPreorder.data.pre_order.present).toBe(true);
  expect(updatedPreorder.data.pre_order.totals.quantity).toBe(3);

  const detailClear = waitForApi(page, `/api/v1/reservations/${reservationId}/preorder`, "DELETE");
  await page.getByRole("button", { name: clearPreorderPattern }).click();
  await expectOk(await detailClear, "DELETE /api/v1/reservations/{id}/preorder");

  const clearedPreorder = await expectOkJson<ReservationPreorderEnvelope>(
    await request.get(liveApiUrl(config, `/api/v1/reservations/${reservationId}/preorder`), {
      headers,
    }),
    "GET /api/v1/reservations/{id}/preorder after clear",
  );

  expect(clearedPreorder.data.pre_order.present).toBe(false);
  expect(clearedPreorder.data.pre_order.totals.quantity).toBe(0);
});
