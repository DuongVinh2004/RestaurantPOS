import path from "node:path";
import { readFileSync } from "node:fs";
import { expect, test, type APIRequestContext, type APIResponse, type Page, type Response as PlaywrightResponse } from "@playwright/test";

type LiveRuntimeConfig = {
  apiBaseUrl: string;
  identifier?: string;
  password?: string;
  guestName: string;
  guestPhone: string;
  guestEmail?: string;
  guestCount: number;
  durationMinutes: number;
  startTime: string;
  allowSkip: boolean;
  exerciseDepositPaymentSession: boolean;
  exerciseBillPaymentSession: boolean;
  exerciseWaitingList: boolean;
  exerciseAccountBenefits: boolean;
  exercisePrivacyTools: boolean;
  exerciseDataExport: boolean;
  missing: string[];
};

type UatManifest = {
  branch?: {
    branch_id?: number;
  };
  auth?: {
    staff?: {
      api_key?: string;
    };
    customer_secondary?: {
      username?: string;
      password?: string;
    };
  };
  tables?: Record<string, { table_id?: number; table_code?: string; seats?: number }>;
  menu?: {
    items?: Record<string, { item_id?: number; current_price?: string | number }>;
  };
  reservations?: {
    deposit_pending?: {
      reservation_id?: number;
    };
    dine_in_checkin?: {
      reservation_id?: number;
      row_version?: number;
    };
    benefits_pending?: {
      reservation_id?: number;
      row_version?: number;
    };
  };
  benefits?: {
    voucher?: {
      voucher_code?: string;
      user_voucher_id?: number;
    };
  };
  scenarios?: {
    availability_hold_reservation?: {
      from_utc?: string;
      to_utc?: string;
      guest_count?: number;
      preferred_table_ids?: number[];
    };
    deposit_self_pay?: {
      payment_amount?: string | number;
      provider_code?: string;
    };
    dine_in_checkout?: {
      menu_item_ids?: number[];
      table_id?: number;
    };
    waiting_list_lifecycle?: {
      branch_id?: number;
      customer_user_id?: number;
      table_id?: number;
    };
    benefits?: {
      reservation_id?: number;
      user_voucher_id?: number;
      loyalty_points?: number;
    };
  };
};

type StaffCheckInEnvelope = {
  data: {
    row_version: number;
  };
};

type StaffCreateOrderEnvelope = {
  data: {
    order_id: number;
    row_version: number;
  };
};

type PositiveBillFixture = {
  staffApiKey: string;
  menuItemId: number;
  expectedAmount: number;
  reservationId: number;
  reservationRowVersion: number;
  tableId: number;
};

type PositiveDepositFixture = {
  reservationId: number;
  expectedAmount: number;
  providerCode: string;
};

type WaitingListFixture = {
  staffApiKey: string;
  branchId: number;
  tableIds: number[];
  secondaryIdentifier: string;
  secondaryPassword: string;
};

type AccountBenefitsFixture = {
  reservationId: number;
  voucherCode: string;
  loyaltyPoints: number;
};

type AvailabilityFixture = {
  startTimeLocal: string;
  durationMinutes: number;
  guestCount: number;
  preferredTableIds: number[];
};

type AvailableTableCandidate = {
  tableId: number;
  tableCode: string | null;
  seats: number | null;
};

type AvailableTablesEnvelope = {
  data?: Array<{
    table_id?: number;
    table_code?: string | null;
    seats?: number | null;
  }>;
};

type CustomerBrowserAuthHeaders = {
  Accept: string;
  "X-Customer-Token": string;
  "X-Session-Id": string;
};

type DepositPaymentSessionEnvelope = {
  data: {
    payment_session: {
      deposit_payment_session_id: number;
      row_version: number;
    };
  };
};

type BillPaymentSessionEnvelope = {
  data: {
    payment_session: {
      bill_payment_session_id: number;
      row_version: number;
    };
  };
};

type CustomerAuthSessionEnvelope = {
  data: {
    auth_header: string;
    access_token?: string | null;
    session_id: string;
  };
};

type WaitingListEntryEnvelope = {
  data: {
    waiting_id: number;
    row_version: number;
    status: string;
    current_response_state?: string;
  };
};

type WaitingListCollectionEnvelope = {
  data: Array<{
    waiting_id: number;
    row_version: number;
    status: string;
  }>;
};

type StaffWaitingListEnvelope = {
  data: {
    waiting_id: number;
    row_version: number;
    status: string;
  };
};

type BenefitsPreviewEnvelope = {
  data: {
    reservation: {
      reservation_id: number;
      row_version: number;
      loyalty: {
        can_redeem: boolean;
        can_release: boolean;
        min_redeem_points: number;
        max_redeemable_points: number;
      };
    };
    available_vouchers: Array<{
      voucher_code: string;
      can_apply: boolean;
      is_usable_now: boolean;
      is_currently_applied: boolean;
    }>;
  };
};

type VoucherActionEnvelope = {
  data: {
    reservation: {
      row_version: number;
    };
    available_vouchers: Array<Record<string, unknown>>;
  };
};

type LoyaltyActionEnvelope = {
  data: {
    reservation: {
      row_version: number;
    };
  };
};

type PrivacyRequestCollectionEnvelope = {
  data: Array<Record<string, unknown>>;
};

type PrivacyRequestEnvelope = {
  data: {
    request: Record<string, unknown>;
    created: boolean;
  };
};

type DataExportEnvelope = {
  data: Record<string, unknown>;
};

function boolEnv(name: string, fallback = false): boolean {
  const value = process.env[name];

  if (value === undefined || value === "") {
    return fallback;
  }

  return ["1", "true", "yes", "on"].includes(value.toLowerCase());
}

function numberEnv(name: string, fallback: number): number {
  const value = Number(process.env[name]);

  return Number.isFinite(value) && value > 0 ? value : fallback;
}

function defaultFutureLocalDateTime(): string {
  const date = new Date(Date.now() + 48 * 60 * 60 * 1000);
  date.setMinutes(date.getMinutes() < 30 ? 30 : 0, 0, 0);

  if (date.getMinutes() === 0) {
    date.setHours(date.getHours() + 1);
  }

  const pad = (value: number) => String(value).padStart(2, "0");

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function formatLocalDateTimeInput(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, "0");

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function addMinutesToLocalDateTimeInput(value: string, minutes: number): string {
  return formatLocalDateTimeInput(new Date(new Date(value).getTime() + minutes * 60_000));
}

function readLiveRuntimeConfig(defaults?: Partial<Pick<LiveRuntimeConfig, "guestCount" | "durationMinutes" | "startTime">>): LiveRuntimeConfig {
  const identifier = process.env.CUSTOMER_WEB_LIVE_IDENTIFIER;
  const password = process.env.CUSTOMER_WEB_LIVE_PASSWORD;
  const apiBaseUrl =
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    process.env.CUSTOMER_WEB_LIVE_API_BASE_URL ??
    "http://127.0.0.1:8000";
  const missing: string[] = [];

  if (!identifier) {
    missing.push("CUSTOMER_WEB_LIVE_IDENTIFIER");
  }

  if (!password) {
    missing.push("CUSTOMER_WEB_LIVE_PASSWORD");
  }

  return {
    apiBaseUrl,
    identifier,
    password,
    guestName: process.env.CUSTOMER_WEB_LIVE_GUEST_NAME ?? "Live Runtime Customer",
    guestPhone: process.env.CUSTOMER_WEB_LIVE_GUEST_PHONE ?? "5550100",
    guestEmail: process.env.CUSTOMER_WEB_LIVE_GUEST_EMAIL,
    guestCount: numberEnv("CUSTOMER_WEB_LIVE_GUEST_COUNT", defaults?.guestCount ?? 2),
    durationMinutes: numberEnv("CUSTOMER_WEB_LIVE_DURATION_MINUTES", defaults?.durationMinutes ?? 90),
    startTime: process.env.CUSTOMER_WEB_LIVE_START_TIME ?? defaults?.startTime ?? defaultFutureLocalDateTime(),
    allowSkip: boolEnv("CUSTOMER_WEB_LIVE_E2E_ALLOW_SKIP"),
    exerciseDepositPaymentSession: boolEnv("CUSTOMER_WEB_LIVE_EXERCISE_DEPOSIT_PAYMENT_SESSION"),
    exerciseBillPaymentSession: boolEnv("CUSTOMER_WEB_LIVE_EXERCISE_BILL_PAYMENT_SESSION"),
    exerciseWaitingList: boolEnv("CUSTOMER_WEB_LIVE_EXERCISE_WAITING_LIST"),
    exerciseAccountBenefits: boolEnv("CUSTOMER_WEB_LIVE_EXERCISE_ACCOUNT_BENEFITS"),
    exercisePrivacyTools: boolEnv("CUSTOMER_WEB_LIVE_EXERCISE_PRIVACY_TOOLS"),
    exerciseDataExport: boolEnv("CUSTOMER_WEB_LIVE_EXERCISE_DATA_EXPORT"),
    missing,
  };
}

function liveApiUrl(config: LiveRuntimeConfig, path: string): string {
  return `${config.apiBaseUrl.replace(/\/+$/, "")}${path}`;
}

async function expectOk(response: APIResponse | PlaywrightResponse, label: string): Promise<void> {
  if (response.ok()) {
    return;
  }

  let body = "";
  try {
    body = await response.text();
  } catch {
    body = "";
  }

  expect(response.ok(), `${label} returned HTTP ${response.status()}${body ? `: ${body.slice(0, 500)}` : ""}`).toBe(true);
}

async function expectOkJson<T>(response: APIResponse | PlaywrightResponse, label: string): Promise<T> {
  await expectOk(response, label);

  return (await response.json()) as T;
}

async function readCustomerBrowserAuthHeaders(page: Page): Promise<CustomerBrowserAuthHeaders> {
  const auth = await page.evaluate(() => ({
    token: window.localStorage.getItem("restaurantpos.customer.token.v1"),
    sessionId: window.sessionStorage.getItem("restaurantpos.customer.session-id.v1"),
  }));

  if (!auth.token) {
    throw new Error("Customer browser token was not available for live API proof.");
  }

  if (!auth.sessionId) {
    throw new Error("Customer browser session id was not available for live API proof.");
  }

  return {
    Accept: "application/json",
    "X-Customer-Token": auth.token,
    "X-Session-Id": auth.sessionId,
  };
}

async function waitForApi(page: Page, path: string, method = "GET"): Promise<PlaywrightResponse> {
  return page.waitForResponse((response) => {
    const request = response.request();
    const pathname = new URL(response.url()).pathname;

    return pathname === path && request.method().toUpperCase() === method;
  });
}

async function maybeWaitForApi(page: Page, path: string, method = "GET", timeout = 2_000): Promise<PlaywrightResponse | null> {
  try {
    return await page.waitForResponse((response) => {
      const request = response.request();
      const pathname = new URL(response.url()).pathname;

      return pathname === path && request.method().toUpperCase() === method;
    }, { timeout });
  } catch {
    return null;
  }
}

function readUatManifest(): UatManifest {
  const manifestPath = path.resolve(process.cwd(), "..", "storage", "app", "uat", "scenario-pack.json");

  return JSON.parse(readFileSync(manifestPath, "utf8")) as UatManifest;
}

function resolvePositiveBillFixture(manifest: UatManifest): PositiveBillFixture {
  const staffApiKey = manifest.auth?.staff?.api_key?.trim();
  const menuItemId = manifest.scenarios?.dine_in_checkout?.menu_item_ids?.find((value) => Number.isInteger(value) && value > 0);
  const reservationId = Number(manifest.reservations?.dine_in_checkin?.reservation_id);
  const reservationRowVersion = Number(manifest.reservations?.dine_in_checkin?.row_version);
  const tableId = Number(manifest.scenarios?.dine_in_checkout?.table_id);

  if (!staffApiKey) {
    throw new Error("UAT manifest is missing auth.staff.api_key required for positive bill live proof.");
  }

  if (!menuItemId) {
    throw new Error("UAT manifest is missing scenarios.dine_in_checkout.menu_item_ids required for positive bill live proof.");
  }

  const menuItem = Object.values(manifest.menu?.items ?? {}).find((candidate) => Number(candidate.item_id) === menuItemId);
  const expectedAmount = Number(menuItem?.current_price ?? Number.NaN);

  if (!Number.isFinite(expectedAmount) || expectedAmount <= 0) {
    throw new Error(`UAT manifest is missing a positive current_price for menu item ${menuItemId}.`);
  }

  if (!Number.isInteger(reservationId) || reservationId <= 0) {
    throw new Error("UAT manifest is missing reservations.dine_in_checkin.reservation_id required for positive bill live proof.");
  }

  if (!Number.isInteger(reservationRowVersion) || reservationRowVersion <= 0) {
    throw new Error("UAT manifest is missing reservations.dine_in_checkin.row_version required for positive bill live proof.");
  }

  if (!Number.isInteger(tableId) || tableId <= 0) {
    throw new Error("UAT manifest is missing scenarios.dine_in_checkout.table_id required for positive bill live proof.");
  }

  return {
    staffApiKey,
    menuItemId,
    expectedAmount,
    reservationId,
    reservationRowVersion,
    tableId,
  };
}

function resolvePositiveDepositFixture(manifest: UatManifest): PositiveDepositFixture {
  const reservationId = Number(manifest.reservations?.deposit_pending?.reservation_id);
  const expectedAmount = Number(manifest.scenarios?.deposit_self_pay?.payment_amount ?? Number.NaN);
  const providerCode = String(manifest.scenarios?.deposit_self_pay?.provider_code ?? "").trim();

  if (!Number.isInteger(reservationId) || reservationId <= 0) {
    throw new Error("UAT manifest is missing reservations.deposit_pending.reservation_id required for positive deposit live proof.");
  }

  if (!Number.isFinite(expectedAmount) || expectedAmount <= 0) {
    throw new Error("UAT manifest is missing scenarios.deposit_self_pay.payment_amount required for positive deposit live proof.");
  }

  if (providerCode === "") {
    throw new Error("UAT manifest is missing scenarios.deposit_self_pay.provider_code required for positive deposit live proof.");
  }

  return {
    reservationId,
    expectedAmount,
    providerCode,
  };
}

function resolveWaitingListFixture(manifest: UatManifest): WaitingListFixture {
  const staffApiKey = manifest.auth?.staff?.api_key?.trim();
  const branchId = Number(manifest.scenarios?.waiting_list_lifecycle?.branch_id ?? manifest.branch?.branch_id);
  const tableId = Number(manifest.scenarios?.waiting_list_lifecycle?.table_id);
  const tableIds = [
    tableId,
    ...Object.values(manifest.tables ?? {})
      .map((candidate) => Number(candidate.table_id))
      .filter((candidate) => Number.isInteger(candidate) && candidate > 0 && candidate !== tableId),
  ];
  const secondaryIdentifier = manifest.auth?.customer_secondary?.username?.trim();
  const secondaryPassword = manifest.auth?.customer_secondary?.password?.trim();

  if (!staffApiKey) {
    throw new Error("UAT manifest is missing auth.staff.api_key required for waiting-list live proof.");
  }

  if (!Number.isInteger(branchId) || branchId <= 0) {
    throw new Error("UAT manifest is missing scenarios.waiting_list_lifecycle.branch_id required for waiting-list live proof.");
  }

  if (!Number.isInteger(tableId) || tableId <= 0) {
    throw new Error("UAT manifest is missing scenarios.waiting_list_lifecycle.table_id required for waiting-list live proof.");
  }

  if (tableIds.length < 2) {
    throw new Error("UAT manifest needs at least two waiting-list candidate tables for accept and decline live proof.");
  }

  if (!secondaryIdentifier || !secondaryPassword) {
    throw new Error("UAT manifest is missing customer_secondary credentials required for waiting-list owner-denial live proof.");
  }

  return {
    staffApiKey,
    branchId,
    tableIds: Array.from(new Set(tableIds)),
    secondaryIdentifier,
    secondaryPassword,
  };
}

function resolveAccountBenefitsFixture(manifest: UatManifest): AccountBenefitsFixture {
  const reservationId = Number(manifest.scenarios?.benefits?.reservation_id ?? manifest.reservations?.benefits_pending?.reservation_id);
  const voucherCode = String(manifest.benefits?.voucher?.voucher_code ?? "").trim();
  const loyaltyPoints = Number(manifest.scenarios?.benefits?.loyalty_points);

  if (!Number.isInteger(reservationId) || reservationId <= 0) {
    throw new Error("UAT manifest is missing scenarios.benefits.reservation_id required for account-benefits live proof.");
  }

  if (voucherCode === "") {
    throw new Error("UAT manifest is missing benefits.voucher.voucher_code required for account-benefits live proof.");
  }

  if (!Number.isFinite(loyaltyPoints) || loyaltyPoints <= 0) {
    throw new Error("UAT manifest is missing scenarios.benefits.loyalty_points required for account-benefits live proof.");
  }

  return {
    reservationId,
    voucherCode,
    loyaltyPoints,
  };
}

function resolveAvailabilityFixture(manifest: UatManifest): AvailabilityFixture {
  const scenario = manifest.scenarios?.availability_hold_reservation;
  const startUtc = scenario?.from_utc;
  const endUtc = scenario?.to_utc;
  const guestCount = Number(scenario?.guest_count);
  const preferredTableIds = Array.isArray(scenario?.preferred_table_ids)
    ? scenario.preferred_table_ids.filter((value) => Number.isInteger(value) && value > 0)
    : [];

  if (!startUtc || Number.isNaN(Date.parse(startUtc))) {
    throw new Error("UAT manifest is missing scenarios.availability_hold_reservation.from_utc required for live booking proof.");
  }

  if (!endUtc || Number.isNaN(Date.parse(endUtc))) {
    throw new Error("UAT manifest is missing scenarios.availability_hold_reservation.to_utc required for live booking proof.");
  }

  if (!Number.isInteger(guestCount) || guestCount <= 0) {
    throw new Error("UAT manifest is missing scenarios.availability_hold_reservation.guest_count required for live booking proof.");
  }

  if (preferredTableIds.length === 0) {
    throw new Error("UAT manifest is missing scenarios.availability_hold_reservation.preferred_table_ids required for live booking proof.");
  }

  const durationMinutes = Math.round((Date.parse(endUtc) - Date.parse(startUtc)) / 60_000);
  if (!Number.isFinite(durationMinutes) || durationMinutes <= 0) {
    throw new Error("UAT manifest availability_hold_reservation duration is invalid for live booking proof.");
  }

  return {
    startTimeLocal: formatLocalDateTimeInput(new Date(startUtc)),
    durationMinutes,
    guestCount,
    preferredTableIds,
  };
}

function resolveAvailabilityCandidate(
  envelope: AvailableTablesEnvelope,
  fixture: AvailabilityFixture,
  guestCount: number,
): AvailableTableCandidate {
  const availableTables = Array.isArray(envelope.data) ? envelope.data : [];
  const normalized = availableTables
    .map((table) => ({
      tableId: Number(table.table_id),
      tableCode: typeof table.table_code === "string" && table.table_code.trim() !== "" ? table.table_code : null,
      seats: Number.isFinite(Number(table.seats)) ? Number(table.seats) : null,
    }))
    .filter((table) => Number.isInteger(table.tableId) && table.tableId > 0);

  const preferredIds = new Set(fixture.preferredTableIds);

  const candidate =
    normalized.find((table) => preferredIds.has(table.tableId) && (table.seats ?? 0) >= guestCount) ??
    normalized.find((table) => (table.seats ?? 0) >= guestCount) ??
    normalized.find((table) => preferredIds.has(table.tableId)) ??
    normalized[0];

  if (!candidate) {
    throw new Error("Live availability search returned no selectable tables.");
  }

  return candidate;
}

function formatExpectedMoney(amount: number, currency = "VND"): string {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency,
  }).format(amount);
}

function newIdempotencyKey(scope: string): string {
  return `${scope}-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function customerOwnerHeaders(headers: CustomerBrowserAuthHeaders): Record<string, string> {
  return {
    Accept: "application/json",
    "X-Customer-Token": headers["X-Customer-Token"],
  };
}

function staffJsonHeaders(staffApiKey: string, scope: string): Record<string, string> {
  return {
    Accept: "application/json",
    "X-Staff-Key": staffApiKey,
    "Idempotency-Key": newIdempotencyKey(scope),
  };
}

async function loginCustomerForOwnerDenial(
  request: APIRequestContext,
  fixture: WaitingListFixture,
): Promise<Record<string, string>> {
  const response = await request.post(liveApiUrl(live, "/api/v1/auth/customer/login"), {
    headers: {
      Accept: "application/json",
      "Idempotency-Key": newIdempotencyKey("customer-web-live-secondary-login"),
    },
    data: {
      identifier: fixture.secondaryIdentifier,
      password: fixture.secondaryPassword,
      session_id: `customer-web-live-secondary-${Date.now()}`,
    },
  });
  const payload = await expectOkJson<CustomerAuthSessionEnvelope>(response, "POST /api/v1/auth/customer/login for owner-denial proof");
  const token = payload.data.access_token ?? payload.data.auth_header.replace(/^Bearer\s+/i, "");

  return {
    Accept: "application/json",
    "X-Customer-Token": token,
  };
}

async function createWaitingListEntry(
  request: APIRequestContext,
  headers: Record<string, string>,
  fixture: WaitingListFixture,
  suffix: string,
): Promise<WaitingListEntryEnvelope> {
  const response = await request.post(liveApiUrl(live, "/api/v1/waiting-list"), {
    headers: {
      ...headers,
      "Idempotency-Key": newIdempotencyKey(`customer-web-live-waiting-create-${suffix}`),
    },
    data: {
      branch_id: fixture.branchId,
      guest_count: 2,
      guest_name: `Live Waiting ${suffix}`,
      phone: "0900001777",
      notes: `Customer-web live ${suffix} proof`,
    },
  });

  return expectOkJson<WaitingListEntryEnvelope>(response, "POST /api/v1/waiting-list");
}

async function notifyWaitingListEntry(
  request: APIRequestContext,
  fixture: WaitingListFixture,
  entry: WaitingListEntryEnvelope,
  suffix: string,
  tableIndex = 0,
): Promise<StaffWaitingListEnvelope> {
  const tableId = fixture.tableIds[Math.min(tableIndex, fixture.tableIds.length - 1)];
  const response = await request.post(liveApiUrl(live, `/api/v1/staff/waiting-list/${entry.data.waiting_id}/notify`), {
    headers: staffJsonHeaders(fixture.staffApiKey, `customer-web-live-waiting-notify-${suffix}`),
    data: {
      table_id: tableId,
      hold_minutes: 10,
      row_version: entry.data.row_version,
    },
  });

  return expectOkJson<StaffWaitingListEnvelope>(response, "POST /api/v1/staff/waiting-list/{id}/notify");
}

async function cancelActiveWaitingListEntries(
  request: APIRequestContext,
  headers: Record<string, string>,
): Promise<void> {
  const list = await expectOkJson<WaitingListCollectionEnvelope>(
    await request.get(liveApiUrl(live, "/api/v1/waiting-list?active_only=0"), {
      headers,
    }),
    "GET /api/v1/waiting-list before waiting-list cleanup",
  );
  const activeEntries = list.data.filter((entry) => !["cancelled", "seated"].includes(entry.status.toLowerCase()));

  for (const entry of activeEntries) {
    await expectOk(
      await request.post(liveApiUrl(live, `/api/v1/waiting-list/${entry.waiting_id}/cancel`), {
        headers: {
          ...headers,
          "Idempotency-Key": newIdempotencyKey(`customer-web-live-waiting-cleanup-${entry.waiting_id}`),
        },
        data: {
          row_version: entry.row_version,
          cancel_reason: "Customer-web live proof cleanup",
        },
      }),
      "POST /api/v1/waiting-list/{id}/cancel during live cleanup",
    );
  }
}

async function expectRecoverableRowVersionFailure(response: APIResponse, label: string): Promise<void> {
  const status = response.status();
  const body = await response.text();

  expect([409, 422], `${label} should reject stale row_version. Body: ${body.slice(0, 500)}`).toContain(status);
}

async function expectOwnerDenied(response: APIResponse, label: string): Promise<void> {
  const status = response.status();
  const body = await response.text();

  expect([403, 404], `${label} should not expose another customer's record. Body: ${body.slice(0, 500)}`).toContain(status);
}

async function proveWaitingListWave2({
  page,
  request,
  customerHeaders,
  fixture,
}: {
  page: Page;
  request: APIRequestContext;
  customerHeaders: CustomerBrowserAuthHeaders;
  fixture: WaitingListFixture;
}): Promise<void> {
  const ownerHeaders = customerOwnerHeaders(customerHeaders);

  await page.goto("/waiting-list");
  await expect(page.getByRole("heading", { name: "Waiting list" })).toBeVisible();

  const listResponse = await request.get(liveApiUrl(live, "/api/v1/waiting-list?active_only=0"), {
    headers: ownerHeaders,
  });
  await expectOk(listResponse, "GET /api/v1/waiting-list");
  await cancelActiveWaitingListEntries(request, ownerHeaders);

  const acceptEntry = await createWaitingListEntry(request, ownerHeaders, fixture, "accept");
  const notifiedAccept = await notifyWaitingListEntry(request, fixture, acceptEntry, "accept");
  const detailResponse = await request.get(liveApiUrl(live, `/api/v1/waiting-list/${acceptEntry.data.waiting_id}`), {
    headers: ownerHeaders,
  });
  const detail = await expectOkJson<WaitingListEntryEnvelope>(detailResponse, "GET /api/v1/waiting-list/{id}");

  const secondaryHeaders = await loginCustomerForOwnerDenial(request, fixture);
  await expectOwnerDenied(
    await request.get(liveApiUrl(live, `/api/v1/waiting-list/${acceptEntry.data.waiting_id}`), {
      headers: secondaryHeaders,
    }),
    "GET /api/v1/waiting-list/{id} as another customer",
  );

  const staleAccept = await request.post(liveApiUrl(live, `/api/v1/waiting-list/${acceptEntry.data.waiting_id}/accept`), {
    headers: {
      ...ownerHeaders,
      "Idempotency-Key": newIdempotencyKey("customer-web-live-waiting-stale-accept"),
    },
    data: {
      row_version: Math.max(1, notifiedAccept.data.row_version - 1),
    },
  });
  await expectRecoverableRowVersionFailure(staleAccept, "POST /api/v1/waiting-list/{id}/accept stale row_version");

  const accepted = await expectOkJson<WaitingListEntryEnvelope>(
    await request.post(liveApiUrl(live, `/api/v1/waiting-list/${acceptEntry.data.waiting_id}/accept`), {
      headers: {
        ...ownerHeaders,
        "Idempotency-Key": newIdempotencyKey("customer-web-live-waiting-accept"),
      },
      data: {
        row_version: detail.data.row_version,
      },
    }),
    "POST /api/v1/waiting-list/{id}/accept",
  );
  const arrival = await expectOkJson<WaitingListEntryEnvelope>(
    await request.post(liveApiUrl(live, `/api/v1/waiting-list/${acceptEntry.data.waiting_id}/confirm-arrival`), {
      headers: {
        ...ownerHeaders,
        "Idempotency-Key": newIdempotencyKey("customer-web-live-waiting-arrival"),
      },
      data: {
        row_version: accepted.data.row_version,
      },
    }),
    "POST /api/v1/waiting-list/{id}/confirm-arrival",
  );

  expect(arrival.data.row_version).toBeGreaterThan(accepted.data.row_version);

  await cancelActiveWaitingListEntries(request, secondaryHeaders);
  const declineEntry = await createWaitingListEntry(request, secondaryHeaders, fixture, "decline");
  const notifiedDecline = await notifyWaitingListEntry(request, fixture, declineEntry, "decline", 1);
  await expectOk(
    await request.post(liveApiUrl(live, `/api/v1/waiting-list/${declineEntry.data.waiting_id}/decline`), {
      headers: {
        ...secondaryHeaders,
        "Idempotency-Key": newIdempotencyKey("customer-web-live-waiting-decline"),
      },
      data: {
        row_version: notifiedDecline.data.row_version,
      },
    }),
    "POST /api/v1/waiting-list/{id}/decline",
  );

  await cancelActiveWaitingListEntries(request, secondaryHeaders);
  const cancelEntry = await createWaitingListEntry(request, secondaryHeaders, fixture, "cancel");
  await expectOk(
    await request.post(liveApiUrl(live, `/api/v1/waiting-list/${cancelEntry.data.waiting_id}/cancel`), {
      headers: {
        ...secondaryHeaders,
        "Idempotency-Key": newIdempotencyKey("customer-web-live-waiting-cancel"),
      },
      data: {
        row_version: cancelEntry.data.row_version,
        cancel_reason: "Customer-web live cancel proof",
      },
    }),
    "POST /api/v1/waiting-list/{id}/cancel",
  );
}

async function proveAccountBenefitsWave2({
  request,
  customerHeaders,
  fixture,
}: {
  request: APIRequestContext;
  customerHeaders: CustomerBrowserAuthHeaders;
  fixture: AccountBenefitsFixture;
}): Promise<void> {
  const ownerHeaders = customerOwnerHeaders(customerHeaders);

  await expectOk(
    await request.get(liveApiUrl(live, "/api/v1/me/loyalty?limit=10"), {
      headers: ownerHeaders,
    }),
    "GET /api/v1/me/loyalty",
  );
  await expectOk(
    await request.get(liveApiUrl(live, "/api/v1/me/vouchers?bucket=all&per_page=24"), {
      headers: ownerHeaders,
    }),
    "GET /api/v1/me/vouchers",
  );

  const preview = await expectOkJson<BenefitsPreviewEnvelope>(
    await request.get(liveApiUrl(live, `/api/v1/reservations/${fixture.reservationId}/benefits-preview`), {
      headers: ownerHeaders,
    }),
    "GET /api/v1/reservations/{id}/benefits-preview",
  );
  const voucher = preview.data.available_vouchers.find((candidate) => candidate.voucher_code === fixture.voucherCode);
  expect(voucher, `UAT benefits fixture must expose voucher ${fixture.voucherCode}.`).toBeTruthy();

  const applied = await expectOkJson<VoucherActionEnvelope>(
    await request.post(liveApiUrl(live, `/api/v1/reservations/${fixture.reservationId}/voucher/apply`), {
      headers: {
        ...ownerHeaders,
        "Idempotency-Key": newIdempotencyKey("customer-web-live-voucher-apply"),
      },
      data: {
        row_version: preview.data.reservation.row_version,
        voucher_code: fixture.voucherCode,
      },
    }),
    "POST /api/v1/reservations/{id}/voucher/apply",
  );

  const staleRemove = await request.post(liveApiUrl(live, `/api/v1/reservations/${fixture.reservationId}/voucher/remove`), {
    headers: {
      ...ownerHeaders,
      "Idempotency-Key": newIdempotencyKey("customer-web-live-voucher-stale-remove"),
    },
    data: {
      row_version: preview.data.reservation.row_version,
    },
  });
  await expectRecoverableRowVersionFailure(staleRemove, "POST /api/v1/reservations/{id}/voucher/remove stale row_version");

  const refreshedPreview = await expectOkJson<BenefitsPreviewEnvelope>(
    await request.get(liveApiUrl(live, `/api/v1/reservations/${fixture.reservationId}/benefits-preview`), {
      headers: ownerHeaders,
    }),
    "GET /api/v1/reservations/{id}/benefits-preview after stale voucher remove",
  );
  const removed = await expectOkJson<VoucherActionEnvelope>(
    await request.post(liveApiUrl(live, `/api/v1/reservations/${fixture.reservationId}/voucher/remove`), {
      headers: {
        ...ownerHeaders,
        "Idempotency-Key": newIdempotencyKey("customer-web-live-voucher-remove"),
      },
      data: {
        row_version: refreshedPreview.data.reservation.row_version || applied.data.reservation.row_version,
      },
    }),
    "POST /api/v1/reservations/{id}/voucher/remove",
  );

  const redeemPoints = Math.min(
    fixture.loyaltyPoints,
    Math.max(refreshedPreview.data.reservation.loyalty.min_redeem_points, refreshedPreview.data.reservation.loyalty.max_redeemable_points),
  );
  const redeemed = await expectOkJson<LoyaltyActionEnvelope>(
    await request.post(liveApiUrl(live, `/api/v1/reservations/${fixture.reservationId}/loyalty/redeem`), {
      headers: {
        ...ownerHeaders,
        "Idempotency-Key": newIdempotencyKey("customer-web-live-loyalty-redeem"),
      },
      data: {
        row_version: removed.data.reservation.row_version,
        points: redeemPoints,
      },
    }),
    "POST /api/v1/reservations/{id}/loyalty/redeem",
  );
  await expectOk(
    await request.post(liveApiUrl(live, `/api/v1/reservations/${fixture.reservationId}/loyalty/redeem/release`), {
      headers: {
        ...ownerHeaders,
        "Idempotency-Key": newIdempotencyKey("customer-web-live-loyalty-release"),
      },
      data: {
        row_version: redeemed.data.reservation.row_version,
      },
    }),
    "POST /api/v1/reservations/{id}/loyalty/redeem/release",
  );
}

async function provePrivacyWave2({
  request,
  customerHeaders,
}: {
  request: APIRequestContext;
  customerHeaders: CustomerBrowserAuthHeaders;
}): Promise<void> {
  const ownerHeaders = customerOwnerHeaders(customerHeaders);

  if (live.exercisePrivacyTools) {
    await expectOkJson<PrivacyRequestCollectionEnvelope>(
      await request.get(liveApiUrl(live, "/api/v1/me/privacy-requests?per_page=20"), {
        headers: ownerHeaders,
      }),
      "GET /api/v1/me/privacy-requests",
    );
    await expectOkJson<PrivacyRequestEnvelope>(
      await request.post(liveApiUrl(live, "/api/v1/me/privacy-requests"), {
        headers: {
          ...ownerHeaders,
          "Idempotency-Key": newIdempotencyKey("customer-web-live-privacy-request"),
        },
        data: {
          request_type: "anonymize",
          reason: "Customer-web live privacy request proof",
        },
      }),
      "POST /api/v1/me/privacy-requests",
    );
  }

  if (live.exerciseDataExport) {
    await expectOkJson<DataExportEnvelope>(
      await request.get(liveApiUrl(live, "/api/v1/me/data-export"), {
        headers: ownerHeaders,
      }),
      "GET /api/v1/me/data-export",
    );
  }
}

async function preparePositiveBillState({
  request,
  reservationId,
  reservationRowVersion,
  tableId,
  fixture,
}: {
  request: APIRequestContext;
  reservationId: number;
  reservationRowVersion: number;
  tableId: number;
  fixture: PositiveBillFixture;
}): Promise<void> {
  const staffHeaders = {
    Accept: "application/json",
    "X-Staff-Key": fixture.staffApiKey,
  };

  const checkIn = await request.post(liveApiUrl(live, `/api/v1/staff/reservations/${reservationId}/check-in`), {
    headers: {
      ...staffHeaders,
      "Idempotency-Key": newIdempotencyKey("customer-web-live-check-in"),
    },
    data: {
      table_ids: [tableId],
      checked_in_at: new Date().toISOString(),
      row_version: reservationRowVersion,
    },
  });
  const checkInPayload = await expectOkJson<StaffCheckInEnvelope>(checkIn, "POST /api/v1/staff/reservations/{id}/check-in");

  const createOrder = await request.post(liveApiUrl(live, `/api/v1/staff/tables/${tableId}/orders`), {
    headers: {
      ...staffHeaders,
      "Idempotency-Key": newIdempotencyKey("customer-web-live-order-create"),
    },
    data: {
      reservation_id: reservationId,
      row_version: checkInPayload.data.row_version,
      items: [
        {
          menu_item_id: fixture.menuItemId,
          qty: 1,
        },
      ],
    },
  });
  const createOrderPayload = await expectOkJson<StaffCreateOrderEnvelope>(createOrder, "POST /api/v1/staff/tables/{table_id}/orders");

  const billSnapshot = await request.post(liveApiUrl(live, `/api/v1/staff/orders/${createOrderPayload.data.order_id}/bill-snapshot`), {
    headers: {
      ...staffHeaders,
      "Idempotency-Key": newIdempotencyKey("customer-web-live-bill-snapshot"),
    },
    data: {
      row_version: createOrderPayload.data.row_version,
      notes: "Customer-web live positive bill proof",
    },
  });

  await expectOk(billSnapshot, "POST /api/v1/staff/orders/{order_id}/bill-snapshot");
}

const uatManifest = readUatManifest();
const availabilityFixture = resolveAvailabilityFixture(uatManifest);
const live = readLiveRuntimeConfig({
  guestCount: availabilityFixture.guestCount,
  durationMinutes: availabilityFixture.durationMinutes,
  startTime: availabilityFixture.startTimeLocal,
});
const positiveDepositFixture = resolvePositiveDepositFixture(uatManifest);
const positiveBillFixture = resolvePositiveBillFixture(uatManifest);
const waitingListFixture = live.exerciseWaitingList ? resolveWaitingListFixture(uatManifest) : null;
const accountBenefitsFixture = live.exerciseAccountBenefits ? resolveAccountBenefitsFixture(uatManifest) : null;

test.describe("Wave 1 live Laravel runtime", () => {
  test("proves the customer Wave 1 path without mocked network interception", async ({ page, request }) => {
    if (live.allowSkip && live.missing.length > 0) {
      test.skip(true, `Live runtime credentials missing: ${live.missing.join(", ")}`);
    }

    expect(live.missing, `Missing live runtime env vars: ${live.missing.join(", ")}`).toEqual([]);

    let health: APIResponse;

    try {
      health = await request.get(liveApiUrl(live, "/api/v1/health"), {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "customer-web-live-e2e",
        },
        timeout: 10_000,
      });
    } catch (error) {
      if (live.allowSkip) {
        test.skip(true, `Live Laravel health check failed before browser flow: ${error instanceof Error ? error.message : String(error)}`);
      }

      throw error;
    }

    if (live.allowSkip && !health.ok()) {
      test.skip(true, `Live Laravel health check returned HTTP ${health.status()}`);
    }

    await expectOk(health, "GET /api/v1/health");

    const menuItems = waitForApi(page, "/api/v1/menu/items");
    await page.goto("/");
    await expect(page.getByRole("heading", { name: "Browse the menu before your visit." })).toBeVisible();
    await expect(page.locator('[data-slot="badge"]').filter({ hasText: /^Live menu$/ })).toBeVisible();
    await expectOk(await menuItems, "GET /api/v1/menu/items");

    const login = waitForApi(page, "/api/v1/auth/customer/login", "POST");
    await page.goto("/login");
    await page.getByLabel("Email, phone, or customer id").fill(live.identifier as string);
    await page.getByLabel("Password").fill(live.password as string);
    await page.getByRole("button", { name: "Sign in" }).click();
    await expectOk(await login, "POST /api/v1/auth/customer/login");
    await expect(page).toHaveURL(/\/reservations$/);

    const sessionBootstrap = waitForApi(page, "/api/v1/auth/customer/me");
    await page.reload();
    await expect(page.getByRole("heading", { name: "Reservations" })).toBeVisible();
    await expectOk(await sessionBootstrap, "GET /api/v1/auth/customer/me");
    const customerHeaders = await readCustomerBrowserAuthHeaders(page);

    const positiveDepositDetail = waitForApi(page, `/api/v1/reservations/${positiveDepositFixture.reservationId}`);
    const positiveDepositPreview = waitForApi(page, `/api/v1/reservations/${positiveDepositFixture.reservationId}/deposit-preview`);

    await page.goto(`/reservations/${positiveDepositFixture.reservationId}`);
    await expect(page.getByRole("link", { name: "Back to reservations" })).toBeVisible();
    await expectOk(await positiveDepositDetail, "GET /api/v1/reservations/{id} for seeded deposit proof");
    await expectOk(await positiveDepositPreview, "GET /api/v1/reservations/{id}/deposit-preview for seeded deposit proof");

    const depositCard = page.locator('[data-slot="card"]').filter({
      has: page.locator('[data-slot="card-title"]').filter({ hasText: /^Deposit$/ }),
    }).first();
    await expect(depositCard.getByText("Amount due", { exact: true })).toBeVisible();
    await expect(depositCard.getByText(formatExpectedMoney(positiveDepositFixture.expectedAmount), { exact: true })).toBeVisible();
    await expect(depositCard.getByRole("button", { name: "Acknowledge deposit" })).toBeVisible();

    const acknowledgeDeposit = waitForApi(page, `/api/v1/reservations/${positiveDepositFixture.reservationId}/deposit/acknowledge`, "POST");
    await depositCard.getByRole("button", { name: "Acknowledge deposit" }).click();
    await expectOk(await acknowledgeDeposit, "POST /api/v1/reservations/{id}/deposit/acknowledge");

    await expect(depositCard.getByRole("button", { name: "Mark as self-pay" })).toBeVisible();
    const submitDepositIntent = waitForApi(page, `/api/v1/reservations/${positiveDepositFixture.reservationId}/deposit/intent`, "POST");
    await depositCard.getByRole("button", { name: "Mark as self-pay" }).click();
    await expectOk(await submitDepositIntent, "POST /api/v1/reservations/{id}/deposit/intent");

    await expect(depositCard.getByRole("button", { name: "Remove self-pay" })).toBeVisible();
    const revokeDepositIntent = waitForApi(page, `/api/v1/reservations/${positiveDepositFixture.reservationId}/deposit/intent/revoke`, "POST");
    await depositCard.getByRole("button", { name: "Remove self-pay" }).click();
    await expectOk(await revokeDepositIntent, "POST /api/v1/reservations/{id}/deposit/intent/revoke");

    await expect(depositCard.getByRole("button", { name: "Mark as self-pay" })).toBeVisible();
    const resubmitDepositIntent = waitForApi(page, `/api/v1/reservations/${positiveDepositFixture.reservationId}/deposit/intent`, "POST");
    await depositCard.getByRole("button", { name: "Mark as self-pay" }).click();
    await expectOk(await resubmitDepositIntent, "POST /api/v1/reservations/{id}/deposit/intent after revoke");

    await expect(depositCard.getByRole("button", { name: "Continue to deposit payment" })).toBeVisible();
    const createDepositSession = waitForApi(page, `/api/v1/reservations/${positiveDepositFixture.reservationId}/deposit/payment-sessions`, "POST");
    await depositCard.getByRole("button", { name: "Continue to deposit payment" }).click();
    const depositPaymentSession = await expectOkJson<DepositPaymentSessionEnvelope>(
      await createDepositSession,
      "POST /api/v1/reservations/{id}/deposit/payment-sessions",
    );
    await expect(depositCard.getByText("Deposit payment session", { exact: true })).toBeVisible();
    await expect(depositCard.getByText("Payment session open", { exact: true })).toBeVisible();
    await expectOk(
      await request.get(
        liveApiUrl(
          live,
          `/api/v1/reservations/${positiveDepositFixture.reservationId}/deposit/payment-sessions/${depositPaymentSession.data.payment_session.deposit_payment_session_id}`,
        ),
        { headers: customerHeaders },
      ),
      "GET /api/v1/reservations/{id}/deposit/payment-sessions/{session_id}",
    );
    const refreshDepositSession = waitForApi(
      page,
      `/api/v1/reservations/${positiveDepositFixture.reservationId}/deposit/payment-sessions/${depositPaymentSession.data.payment_session.deposit_payment_session_id}/refresh`,
      "POST",
    );
    await depositCard.getByRole("button", { name: "Refresh status" }).click();
    await expectOk(await refreshDepositSession, "POST /api/v1/reservations/{id}/deposit/payment-sessions/{session_id}/refresh");
    const confirmDepositSession = waitForApi(
      page,
      `/api/v1/reservations/${positiveDepositFixture.reservationId}/deposit/payment-sessions/${depositPaymentSession.data.payment_session.deposit_payment_session_id}/confirm`,
      "POST",
    );
    await depositCard.getByRole("button", { name: "Confirm payment" }).click();
    await expectOk(await confirmDepositSession, "POST /api/v1/reservations/{id}/deposit/payment-sessions/{session_id}/confirm");

    await page.goto("/booking");
    await page.getByLabel("Date and time").fill(live.startTime);
    await page.getByLabel("Guests").fill(String(live.guestCount));
    await page.getByLabel("Minutes").fill(String(live.durationMinutes));

    const availability = waitForApi(page, "/api/v1/tables/available");
    await page.getByRole("button", { name: "Search tables" }).click();
    const availabilityResponse = await availability;
    await expectOk(availabilityResponse, "GET /api/v1/tables/available");
    const availabilityPayload = await availabilityResponse.json() as AvailableTablesEnvelope;
    const availabilityCandidate = resolveAvailabilityCandidate(availabilityPayload, availabilityFixture, live.guestCount);

    const tableButton = page.locator("button").filter({
      hasText: availabilityCandidate.tableCode ?? `${availabilityCandidate.tableId}`,
    }).first();
    await expect(
      tableButton,
      `Live fixture must expose a selectable table for ${live.guestCount} guests. Preferred table ids: ${availabilityFixture.preferredTableIds.join(", ")}`,
    ).toBeVisible();
    await tableButton.click();

    const holdCreate = waitForApi(page, "/api/v1/table-holds", "POST");
    await page.getByRole("button", { name: "Create hold" }).click();
    await expectOk(await holdCreate, "POST /api/v1/table-holds");

    const continueLink = page.getByRole("link", { name: "Continue to reservation" });
    await expect(continueLink).toBeVisible();
    await expect(continueLink).toHaveAttribute("href", /hold_id=/);
    await continueLink.click();

    await expect(page.getByRole("heading", { name: "Create reservation" })).toBeVisible();
    await page.getByLabel("Guest name").fill(live.guestName);
    await page.getByLabel("Phone").fill(live.guestPhone);

    if (live.guestEmail) {
      await page.getByLabel("Email").fill(live.guestEmail);
    }

    const reservationCreate = waitForApi(page, "/api/v1/reservations", "POST");
    await page.getByRole("button", { name: "Create reservation" }).click();
    await expectOk(await reservationCreate, "POST /api/v1/reservations");
    await expect(page).toHaveURL(/\/reservations\/\d+$/);

    const reservationId = Number(page.url().match(/\/reservations\/(\d+)$/)?.[1]);
    expect(Number.isInteger(reservationId), "Reservation detail URL must include the live reservation id.").toBe(true);

    const reservationList = waitForApi(page, "/api/v1/reservations");
    await page.goto("/reservations");
    await expect(page.getByRole("heading", { name: "Reservations" })).toBeVisible();
    await expectOk(await reservationList, "GET /api/v1/reservations");
    await expect(page.locator(`a[href="/reservations/${reservationId}"]`).first()).toBeVisible();

    const reservationDetail = waitForApi(page, `/api/v1/reservations/${reservationId}`);
    const depositPreview = waitForApi(page, `/api/v1/reservations/${reservationId}/deposit-preview`);
    const activeOrder = waitForApi(page, `/api/v1/reservations/${reservationId}/active-order`);
    const billPreview = waitForApi(page, `/api/v1/reservations/${reservationId}/bill-preview`);
    const billDetail = maybeWaitForApi(page, `/api/v1/reservations/${reservationId}/bill`);

    await page.goto(`/reservations/${reservationId}`);
    await expect(page.getByRole("link", { name: "Back to reservations" })).toBeVisible();
    await expectOk(await reservationDetail, "GET /api/v1/reservations/{id}");
    await expectOk(await depositPreview, "GET /api/v1/reservations/{id}/deposit-preview");
    await expectOk(await activeOrder, "GET /api/v1/reservations/{id}/active-order");
    await expectOk(await billPreview, "GET /api/v1/reservations/{id}/bill-preview");

    const billDetailResponse = await billDetail;
    if (billDetailResponse) {
      await expectOk(billDetailResponse, "GET /api/v1/reservations/{id}/bill");
    } else {
      await expect(page.getByText("Active order", { exact: true })).toBeVisible();
      await expect(page.getByText("No active order", { exact: true })).toBeVisible();
      await expect(page.getByText("Nothing is due right now.", { exact: true })).toBeVisible();
    }

    await expect(page.locator('[data-slot="card-title"]').filter({ hasText: /^Deposit$/ })).toBeVisible();
    await expect(page.locator('[data-slot="card-title"]').filter({ hasText: /^Bill and active order$/ })).toBeVisible();

    const submitIntentButton = page.getByRole("button", { name: "Mark as self-pay" });

    if (await submitIntentButton.isVisible()) {
      const submitIntent = waitForApi(page, `/api/v1/reservations/${reservationId}/deposit/intent`, "POST");
      await submitIntentButton.click();
      await expectOk(await submitIntent, "POST /api/v1/reservations/{id}/deposit/intent");
    }

    await page.getByLabel("New start time").fill(addMinutesToLocalDateTimeInput(live.startTime, 30));
    await page.getByLabel("Reason or note").fill("Customer-web live reschedule proof");
    const reservationReschedule = waitForApi(page, `/api/v1/reservations/${reservationId}/reschedule`, "POST");
    await page.getByRole("button", { name: "Request new time" }).click();
    await expectOk(await reservationReschedule, "POST /api/v1/reservations/{id}/reschedule");
    await expect(page.getByText("Reservation rescheduled.", { exact: true })).toBeVisible();

    const reservationCancel = waitForApi(page, `/api/v1/reservations/${reservationId}/cancel`, "POST");
    await page.getByRole("button", { name: "Cancel reservation" }).click();
    await expectOk(await reservationCancel, "POST /api/v1/reservations/{id}/cancel");
    await expect(page.getByText("Reservation cancelled.", { exact: true })).toBeVisible();
    await expect(page.getByText("Online changes are no longer available", { exact: true })).toBeVisible();

    await preparePositiveBillState({
      request,
      reservationId: positiveBillFixture.reservationId,
      reservationRowVersion: positiveBillFixture.reservationRowVersion,
      tableId: positiveBillFixture.tableId,
      fixture: positiveBillFixture,
    });

    const positiveReservationDetail = waitForApi(page, `/api/v1/reservations/${positiveBillFixture.reservationId}`);
    const positiveActiveOrder = waitForApi(page, `/api/v1/reservations/${positiveBillFixture.reservationId}/active-order`);
    const positiveBillPreview = waitForApi(page, `/api/v1/reservations/${positiveBillFixture.reservationId}/bill-preview`);
    const positiveBillDetail = maybeWaitForApi(page, `/api/v1/reservations/${positiveBillFixture.reservationId}/bill`);

    await page.goto(`/reservations/${positiveBillFixture.reservationId}`);
    await expectOk(await positiveReservationDetail, "GET /api/v1/reservations/{id} after bill setup");
    await expectOk(await positiveActiveOrder, "GET /api/v1/reservations/{id}/active-order after bill setup");
    await expectOk(await positiveBillPreview, "GET /api/v1/reservations/{id}/bill-preview after bill setup");
    const positiveBillDetailResponse = await positiveBillDetail;
    if (positiveBillDetailResponse) {
      await expectOk(positiveBillDetailResponse, "GET /api/v1/reservations/{id}/bill after bill setup");
    }

    const billCard = page.locator('[data-slot="card"]').filter({
      has: page.locator('[data-slot="card-title"]').filter({ hasText: /^Bill and active order$/ }),
    }).first();
    await expect(billCard.getByText("Bill available", { exact: true })).toBeVisible();
    await expect(billCard.getByText("Active order", { exact: true })).toBeVisible();
    await expect(billCard.getByText(formatExpectedMoney(positiveBillFixture.expectedAmount), { exact: true })).toBeVisible();
    await expect(billCard.getByText("Order Active", { exact: true })).toBeVisible();
    await expect(page.getByRole("button", { name: "Continue to bill payment" })).toBeVisible();

    if (live.exerciseBillPaymentSession) {
      const billSession = waitForApi(page, `/api/v1/reservations/${positiveBillFixture.reservationId}/bill/payment-sessions`, "POST");
      await page.getByRole("button", { name: "Continue to bill payment" }).click();
      const billPaymentSession = await expectOkJson<BillPaymentSessionEnvelope>(
        await billSession,
        "POST /api/v1/reservations/{id}/bill/payment-sessions",
      );
      await expect(page.getByText("Bill payment session", { exact: true })).toBeVisible();
      await expect(page.getByText("Payment session open", { exact: true })).toBeVisible();
      await expectOk(
        await request.get(
          liveApiUrl(
            live,
            `/api/v1/reservations/${positiveBillFixture.reservationId}/bill/payment-sessions/${billPaymentSession.data.payment_session.bill_payment_session_id}`,
          ),
          { headers: customerHeaders },
        ),
        "GET /api/v1/reservations/{id}/bill/payment-sessions/{session_id}",
      );
      const refreshBillSession = waitForApi(
        page,
        `/api/v1/reservations/${positiveBillFixture.reservationId}/bill/payment-sessions/${billPaymentSession.data.payment_session.bill_payment_session_id}/refresh`,
        "POST",
      );
      await page.getByRole("button", { name: "Refresh status" }).click();
      await expectOk(await refreshBillSession, "POST /api/v1/reservations/{id}/bill/payment-sessions/{session_id}/refresh");
      const confirmBillSession = waitForApi(
        page,
        `/api/v1/reservations/${positiveBillFixture.reservationId}/bill/payment-sessions/${billPaymentSession.data.payment_session.bill_payment_session_id}/confirm`,
        "POST",
      );
      await page.getByRole("button", { name: "Confirm payment" }).click();
      await expectOk(await confirmBillSession, "POST /api/v1/reservations/{id}/bill/payment-sessions/{session_id}/confirm");
    }

    if (live.exerciseWaitingList) {
      expect(waitingListFixture, "Waiting-list live proof requires canonical UAT waiting-list prerequisites.").not.toBeNull();
      await proveWaitingListWave2({
        page,
        request,
        customerHeaders,
        fixture: waitingListFixture as WaitingListFixture,
      });
    }

    if (live.exerciseAccountBenefits) {
      expect(accountBenefitsFixture, "Account-benefits live proof requires canonical UAT benefits prerequisites.").not.toBeNull();
      await proveAccountBenefitsWave2({
        request,
        customerHeaders,
        fixture: accountBenefitsFixture as AccountBenefitsFixture,
      });
    }

    if (live.exercisePrivacyTools || live.exerciseDataExport) {
      await provePrivacyWave2({
        request,
        customerHeaders,
      });
    }
  });
});
