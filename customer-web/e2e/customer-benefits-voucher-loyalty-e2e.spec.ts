import path from "node:path";
import { readFileSync } from "node:fs";
import { expect, test, type APIRequestContext, type APIResponse, type Page } from "@playwright/test";

type LiveConfig = {
  apiBaseUrl: string;
  identifier: string;
  password: string;
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
    customer_primary?: {
      username?: string | null;
      password?: string | null;
    };
  };
  scenarios?: {
    benefits?: {
      reservation_id?: number;
      user_voucher_id?: number;
      loyalty_points?: number;
    };
  };
  benefits?: {
    voucher?: {
      voucher_code?: string;
    };
  };
};

type BrowserAuth = {
  token: string;
  sessionId: string;
  expiresAtUtc: string | null;
};

function liveApiUrl(config: LiveConfig, pathName: string): string {
  return `${config.apiBaseUrl.replace(/\/+$/, "")}${pathName}`;
}

function readManifest(): Manifest {
  const manifestPath = path.resolve(process.cwd(), "..", "storage", "app", "uat", "scenario-pack.json");
  return JSON.parse(readFileSync(manifestPath, "utf8")) as Manifest;
}

function readLiveConfig(manifest: Manifest): LiveConfig {
  const identifier = process.env.CUSTOMER_WEB_LIVE_IDENTIFIER?.trim() || manifest.auth?.customer_primary?.username?.trim();
  const password = process.env.CUSTOMER_WEB_LIVE_PASSWORD?.trim() || manifest.auth?.customer_primary?.password?.trim();

  if (!identifier || !password) {
    throw new Error("CUSTOMER_WEB_LIVE_IDENTIFIER/PASSWORD or auth.customer_primary credentials required for benefits live proof.");
  }

  return {
    apiBaseUrl: process.env.NEXT_PUBLIC_API_BASE_URL ?? process.env.CUSTOMER_WEB_LIVE_API_BASE_URL ?? "http://127.0.0.1:8000",
    identifier,
    password,
  };
}

async function expectOkJson<T>(response: APIResponse, label: string): Promise<T> {
  if (!response.ok()) {
    const body = await response.text().catch(() => "");
    throw new Error(`${label} returned HTTP ${response.status()}${body ? `: ${body.slice(0, 500)}` : ""}`);
  }
  return (await response.json()) as T;
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
      session_label: "customer-web-benefits-live",
    },
  });
  const payload = await expectOkJson<AuthEnvelope>(response, "POST /api/v1/auth/customer/login");
  const token = payload.data.access_token?.trim();
  const sessionId = payload.data.session_id?.trim();

  if (!token || !sessionId) {
    throw new Error("Customer login did not return access token/session id.");
  }

  return { token, sessionId, expiresAtUtc: payload.data.expires_at_utc ?? null };
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

test("customer benefits live flow with UI (voucher and loyalty points)", async ({ page, request }) => {
  const manifest = readManifest();
  const config = readLiveConfig(manifest);
  
  const reservationId = manifest.scenarios?.benefits?.reservation_id;
  const voucherCode = manifest.benefits?.voucher?.voucher_code;
  const loyaltyPoints = manifest.scenarios?.benefits?.loyalty_points;

  if (!reservationId || !voucherCode || !loyaltyPoints) {
    throw new Error("Missing UAT benefits fixture data (reservation_id, voucher_code, loyalty_points).");
  }

  const auth = await loginCustomer(request, config);
  await seedBrowserAuth(page, auth);

  await page.goto(`/reservations/${reservationId}`);
  
  // Wait for benefits panel to load
  await expect(page.getByRole("heading", { name: "Ưu đãi" })).toBeVisible({ timeout: 15_000 });
  await expect(page.getByText(voucherCode)).toBeVisible();

  // Test Voucher Application
  const applyVoucherBtn = page.getByRole("button", { name: "Áp dụng voucher" }).first();
  await applyVoucherBtn.click();
  await expect(page.getByText("Đã áp dụng voucher.")).toBeVisible();
  
  // Test Loyalty Points
  const loyaltyInput = page.getByLabel("Số điểm muốn dùng");
  await expect(loyaltyInput).toBeVisible();
  
  // We will input minimum required points to redeem
  await loyaltyInput.fill(loyaltyPoints.toString());
  
  const redeemPointsBtn = page.getByRole("button", { name: "Dùng điểm" });
  await redeemPointsBtn.click();
  await expect(page.getByText("Đã dùng điểm thưởng.")).toBeVisible();

  // Clean up: Release loyalty points
  const releaseLoyaltyBtn = page.getByRole("button", { name: "Gỡ điểm" });
  await expect(releaseLoyaltyBtn).toBeVisible();
  await releaseLoyaltyBtn.click();
  await expect(page.getByText("Đã gỡ điểm thưởng.")).toBeVisible();

  // Clean up: Remove voucher
  const removeVoucherBtn = page.getByRole("button", { name: "Gỡ voucher" });
  await expect(removeVoucherBtn).toBeVisible();
  await removeVoucherBtn.click();
  await expect(page.getByText("Đã gỡ voucher.")).toBeVisible();
});
