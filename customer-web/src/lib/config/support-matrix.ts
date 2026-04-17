export type SupportStatus =
  | "live-integrated"
  | "rollout-flagged"
  | "mock-dev-only"
  | "blocked";

export type SupportMatrixEntry = {
  feature: string;
  status: SupportStatus;
  evidence: string;
  routes: string[];
  requiredHeaders: Array<"X-Customer-Token" | "X-Session-Id" | "Idempotency-Key">;
  frontendDecision: string;
};

export const customerWebSupportMatrix: SupportMatrixEntry[] = [
  {
    feature: "Auth session",
    status: "live-integrated",
    evidence: "Generated SDK curated Auth batch and frozen OpenAPI full contract.",
    routes: [
      "POST /api/v1/auth/customer/login",
      "GET /api/v1/auth/customer/me",
      "POST /api/v1/auth/customer/refresh",
      "POST /api/v1/auth/customer/logout",
    ],
    requiredHeaders: ["X-Customer-Token"],
    frontendDecision: "Use explicit token storage and SDK auth headers. No cookies.",
  },
  {
    feature: "Menu catalog",
    status: "live-integrated",
    evidence: "Generated SDK curated Availability + Reservation batch.",
    routes: ["GET /api/v1/menu/categories", "GET /api/v1/menu/items", "GET /api/v1/menu/items/{id}"],
    requiredHeaders: [],
    frontendDecision: "Public browse and detail screens call live menu adapters.",
  },
  {
    feature: "Table availability and holds",
    status: "live-integrated",
    evidence: "Generated SDK plus mutation-contracts.md table hold idempotency/session rows.",
    routes: [
      "GET /api/v1/tables/available",
      "POST /api/v1/table-holds",
      "GET /api/v1/table-holds/{hold_id}",
      "PATCH /api/v1/table-holds/{hold_id}/refresh",
      "DELETE /api/v1/table-holds/{hold_id}",
    ],
    requiredHeaders: ["X-Session-Id", "Idempotency-Key"],
    frontendDecision: "Use browser session id propagation and idempotent mutations.",
  },
  {
    feature: "Reservations",
    status: "live-integrated",
    evidence: "Generated SDK reservation self-service routes and mutation-contracts.md row_version rules.",
    routes: [
      "POST /api/v1/reservations",
      "GET /api/v1/reservations",
      "GET /api/v1/reservations/{id}",
      "POST /api/v1/reservations/{id}/cancel",
      "POST /api/v1/reservations/{id}/reschedule",
    ],
    requiredHeaders: ["X-Customer-Token", "X-Session-Id", "Idempotency-Key"],
    frontendDecision: "Protected list/detail flows plus create/cancel/reschedule forms use live adapters.",
  },
  {
    feature: "Preorder",
    status: "live-integrated",
    evidence: "Generated SDK preorder preview, replace, clear, and reservation preorder routes.",
    routes: [
      "POST /api/v1/menu/preorder/preview",
      "GET /api/v1/reservations/{id}/preorder",
      "POST /api/v1/reservations/{id}/preorder/preview",
      "PUT /api/v1/reservations/{id}/preorder",
      "DELETE /api/v1/reservations/{id}/preorder",
    ],
    requiredHeaders: ["X-Customer-Token", "X-Session-Id", "Idempotency-Key"],
    frontendDecision: "Expose preorder management on reservation detail without fake success states.",
  },
  {
    feature: "Deposit self-pay",
    status: "live-integrated",
    evidence: "Generated SDK Deposit Self-Pay batch.",
    routes: [
      "GET /api/v1/reservations/{id}/deposit-preview",
      "POST /api/v1/reservations/{id}/deposit/acknowledge",
      "POST /api/v1/reservations/{id}/deposit/intent",
      "POST /api/v1/reservations/{id}/deposit/intent/revoke",
      "POST /api/v1/reservations/{reservation_id}/deposit/payment-sessions",
      "GET /api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}",
      "POST /api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh",
      "POST /api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm",
    ],
    requiredHeaders: ["X-Customer-Token", "X-Session-Id", "Idempotency-Key"],
    frontendDecision: "Render deposit preview and payment-session controls from backend state.",
  },
  {
    feature: "Bill and active order",
    status: "live-integrated",
    evidence: "Generated SDK Dine-In + Checkout customer routes.",
    routes: [
      "GET /api/v1/reservations/{reservation_id}/active-order",
      "GET /api/v1/reservations/{reservation_id}/bill-preview",
      "GET /api/v1/reservations/{reservation_id}/bill",
      "POST /api/v1/reservations/{reservation_id}/bill/payment-sessions",
      "GET /api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}",
      "POST /api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh",
      "POST /api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm",
    ],
    requiredHeaders: ["X-Customer-Token", "X-Session-Id", "Idempotency-Key"],
    frontendDecision: "Render bill/payment UX as live, retry-safe reservation detail panels.",
  },
  {
    feature: "Waiting list",
    status: "rollout-flagged",
    evidence: "Generated SDK Waiting List batch and owner-contract automated tests.",
    routes: [
      "GET /api/v1/waiting-list",
      "POST /api/v1/waiting-list",
      "GET /api/v1/waiting-list/{id}",
      "POST /api/v1/waiting-list/{id}/accept",
      "POST /api/v1/waiting-list/{id}/confirm-arrival",
      "POST /api/v1/waiting-list/{id}/decline",
      "POST /api/v1/waiting-list/{id}/cancel",
    ],
    requiredHeaders: ["X-Customer-Token", "Idempotency-Key"],
    frontendDecision: "Enabled by default behind NEXT_PUBLIC_ENABLE_WAITING_LIST for rollout control.",
  },
  {
    feature: "Account benefits",
    status: "rollout-flagged",
    evidence: "Generated SDK Benefits batch.",
    routes: [
      "GET /api/v1/me/loyalty",
      "GET /api/v1/me/vouchers",
      "GET /api/v1/reservations/{id}/benefits-preview",
      "POST /api/v1/reservations/{id}/voucher/apply",
      "POST /api/v1/reservations/{id}/voucher/remove",
      "POST /api/v1/reservations/{id}/loyalty/redeem",
      "POST /api/v1/reservations/{id}/loyalty/redeem/release",
    ],
    requiredHeaders: ["X-Customer-Token", "Idempotency-Key"],
    frontendDecision: "Loyalty and vouchers are live-integrated, flaggable for staged rollout.",
  },
  {
    feature: "Privacy and data export",
    status: "rollout-flagged",
    evidence: "Generated SDK Customer Privacy batch.",
    routes: ["GET /api/v1/me/data-export", "GET /api/v1/me/privacy-requests", "POST /api/v1/me/privacy-requests"],
    requiredHeaders: ["X-Customer-Token", "Idempotency-Key"],
    frontendDecision: "Account page exposes privacy tools behind NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS.",
  },
  {
    feature: "Dev mock adapter",
    status: "mock-dev-only",
    evidence: "Frontend-only adapter selected by NEXT_PUBLIC_ENABLE_DEV_MOCKS outside production.",
    routes: ["SDK-compatible in-memory fetch responses"],
    requiredHeaders: [],
    frontendDecision: "Used only for local/test resilience when backend runtime services are unavailable.",
  },
];

export function getSupportMatrixEntry(feature: string): SupportMatrixEntry | undefined {
  return customerWebSupportMatrix.find((entry) => entry.feature === feature);
}
