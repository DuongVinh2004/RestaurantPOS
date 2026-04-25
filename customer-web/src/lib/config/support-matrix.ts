export type SupportStatus =
  | "live-ready"
  | "live-conditional"
  | "ci-safe-only"
  | "local-uat-only"
  | "rollout-gated"
  | "blocked";

export type ReleaseWave = "wave-1" | "wave-2" | "deferred" | "dev-only";

export type CustomerSurfaceId =
  | "auth-session"
  | "menu-catalog"
  | "table-availability-and-holds"
  | "reservations"
  | "preorder"
  | "deposit-self-pay"
  | "bill-and-active-order"
  | "waiting-list"
  | "account-benefits"
  | "privacy-requests"
  | "data-export"
  | "dev-mock-adapter";

export type RolloutEnvFlagName =
  | "enableDevMocks"
  | "enablePreorder"
  | "enableMenuCategories"
  | "enableMenuItemDetail"
  | "enableTableAvailability"
  | "enableTableHolds"
  | "enableWaitingList"
  | "enableAccountBenefits"
  | "enablePrivacyTools"
  | "enableDataExport";

export type SurfaceExposurePolicy = "default-on" | "env-flag" | "local-only" | "blocked";

export type SupportMatrixEntry = {
  id: CustomerSurfaceId;
  feature: string;
  releaseWave: ReleaseWave;
  status: SupportStatus;
  exposure: SurfaceExposurePolicy;
  evidence: string;
  routes: string[];
  requiredHeaders: Array<"X-Customer-Token" | "X-Session-Id" | "Idempotency-Key">;
  frontendDecision: string;
  gateFlag?: RolloutEnvFlagName;
  envFlags?: string[];
  disabledTitle?: string;
  disabledDescription?: string;
  liveProofSummary?: string;
};

export type SupportMatrixEnv = Partial<Record<RolloutEnvFlagName, boolean>>;

export type SurfaceRolloutDecision = {
  id: CustomerSurfaceId;
  feature: string;
  releaseWave: ReleaseWave;
  supportStatus: SupportStatus;
  exposure: SurfaceExposurePolicy;
  envFlag: RolloutEnvFlagName | null;
  envRequested: boolean;
  enabled: boolean;
  liveReady: boolean;
  liveConditional: boolean;
  ciSafeOnly: boolean;
  localUatOnly: boolean;
  rolloutGated: boolean;
  blocked: boolean;
  title: string;
  description: string;
  disabledTitle: string;
  disabledDescription: string;
  liveProofSummary: string;
};

export const customerWebSupportMatrix: SupportMatrixEntry[] = [
  {
    id: "auth-session",
    feature: "Auth session",
    releaseWave: "wave-1",
    status: "live-ready",
    exposure: "default-on",
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
    id: "menu-catalog",
    feature: "Menu catalog",
    releaseWave: "wave-1",
    status: "live-ready",
    exposure: "default-on",
    evidence: "Generated SDK curated Availability + Reservation batch.",
    routes: ["GET /api/v1/menu/categories", "GET /api/v1/menu/items", "GET /api/v1/menu/items/{id}"],
    requiredHeaders: [],
    frontendDecision: "Public browse stays live. Optional category and item-detail UI remains fail-closed behind explicit frontend flags.",
    envFlags: ["NEXT_PUBLIC_FEATURE_MENU_CATEGORIES", "NEXT_PUBLIC_FEATURE_MENU_ITEM_DETAIL"],
  },
  {
    id: "table-availability-and-holds",
    feature: "Table availability and holds",
    releaseWave: "wave-1",
    status: "live-ready",
    exposure: "default-on",
    evidence: "Generated SDK plus mutation-contracts.md table hold idempotency and session row rules.",
    routes: [
      "GET /api/v1/tables/available",
      "POST /api/v1/table-holds",
      "GET /api/v1/table-holds/{hold_id}",
      "PATCH /api/v1/table-holds/{hold_id}/refresh",
      "DELETE /api/v1/table-holds/{hold_id}",
    ],
    requiredHeaders: ["X-Session-Id", "Idempotency-Key"],
    frontendDecision: "Use browser session id propagation and idempotent mutations. Optional availability and hold UI remains fail-closed behind explicit frontend flags.",
    envFlags: ["NEXT_PUBLIC_FEATURE_TABLE_AVAILABILITY", "NEXT_PUBLIC_FEATURE_TABLE_HOLDS"],
  },
  {
    id: "reservations",
    feature: "Reservations",
    releaseWave: "wave-1",
    status: "live-ready",
    exposure: "default-on",
    evidence: "Generated SDK reservation self-service routes and mutation-contracts.md row_version rules.",
    routes: [
      "POST /api/v1/reservations",
      "GET /api/v1/reservations",
      "GET /api/v1/reservations/{id}",
      "POST /api/v1/reservations/{id}/cancel",
      "POST /api/v1/reservations/{id}/reschedule",
    ],
    requiredHeaders: ["X-Customer-Token", "X-Session-Id", "Idempotency-Key"],
    frontendDecision: "Wave 1 launch promise covers create, list, and detail. Cancel and reschedule remain contract-visible but are not part of the current launch promise.",
  },
  {
    id: "preorder",
    feature: "Preorder",
    releaseWave: "deferred",
    status: "ci-safe-only",
    exposure: "env-flag",
    evidence: "Generated SDK preorder preview, replace, clear, and reservation preorder routes.",
    routes: [
      "POST /api/v1/menu/preorder/preview",
      "GET /api/v1/reservations/{id}/preorder",
      "POST /api/v1/reservations/{id}/preorder/preview",
      "PUT /api/v1/reservations/{id}/preorder",
      "DELETE /api/v1/reservations/{id}/preorder",
    ],
    requiredHeaders: ["X-Customer-Token", "X-Session-Id", "Idempotency-Key"],
    frontendDecision: "Contract coverage exists, but preorder is outside the current go-live dependency chain and should not be treated as live launch proof.",
    gateFlag: "enablePreorder",
    envFlags: ["NEXT_PUBLIC_FEATURE_PREORDER"],
    disabledTitle: "Preorder is not in this rollout",
    disabledDescription:
      "Preorder stays off by default because live replace and clear proof is outside the current launch scope. Enable the preorder flag only for a focused contract or QA pass.",
    liveProofSummary: "Preorder remains contract-visible and CI-safe, but it is outside the current live launch proof boundary.",
  },
  {
    id: "deposit-self-pay",
    feature: "Deposit self-pay",
    releaseWave: "deferred",
    status: "live-conditional",
    exposure: "default-on",
    evidence:
      "Generated SDK Deposit Self-Pay batch plus contract-visible preview and payment-session routes. Runtime proof exists only on controlled provider paths, not as a day-1 launch promise.",
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
    frontendDecision:
      "Keep deposit preview truthful, but treat payment-session controls as contract-visible only until real provider evidence promotes them into launch scope.",
    liveProofSummary:
      "Deposit self-pay is contract-visible and runtime-conditional. Simulated-provider or local UAT proof does not make it part of the day-1 launch promise.",
  },
  {
    id: "bill-and-active-order",
    feature: "Bill and active order",
    releaseWave: "deferred",
    status: "live-conditional",
    exposure: "default-on",
    evidence:
      "Generated SDK Dine-In + Checkout customer routes plus contract-visible bill preview, bill detail, active-order visibility, and bill payment-session routes.",
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
    frontendDecision:
      "Keep bill preview and active-order visibility truthful, but do not treat customer bill payment-session controls as a day-1 launch promise while staff settlement remains canonical.",
    liveProofSummary:
      "Bill preview and active-order reads are contract-visible, but customer bill self-pay remains off by default for day 1. Any bill payment-session proof is contract-ready only until provider evidence and rollout approval promote it.",
  },
  {
    id: "waiting-list",
    feature: "Waiting list",
    releaseWave: "wave-2",
    status: "live-conditional",
    exposure: "env-flag",
    evidence: "Generated SDK Waiting List batch, owner-contract automated tests, and gated live proof with staff notify setup.",
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
    frontendDecision:
      "Keep customer waiting-list disabled by default for day 1. When Wave 2 QA enables it, use customer-token owner mutations, manual refresh, and staff-backed notification setup without faking realtime or final seating.",
    gateFlag: "enableWaitingList",
    envFlags: ["NEXT_PUBLIC_FEATURE_WAITING_LIST"],
    disabledTitle: "Waiting list is not in this rollout",
    disabledDescription:
      "This build keeps customer waiting-list access off by default. Enable the dedicated waiting-list rollout flag only for a focused QA, UAT, or Wave 2 pass.",
    liveProofSummary:
      "Waiting-list owner list, create, detail, accept, confirm-arrival, decline, cancel, stale row-version refresh, and owner denial have live proof behind the waiting-list flag. Day 1 still keeps customer waiting-list off; notification delivery, realtime updates, and final seat-result rollout remain backend runtime prerequisites.",
  },
  {
    id: "account-benefits",
    feature: "Account benefits",
    releaseWave: "wave-2",
    status: "live-conditional",
    exposure: "env-flag",
    evidence: "Generated SDK Benefits batch plus gated live proof for wallet reads and row-versioned voucher or loyalty mutations.",
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
    frontendDecision:
      "Keep loyalty and voucher benefits disabled by default. When QA enables the flag, account reads and reservation-level voucher or loyalty mutations use owner scope, Idempotency-Key, and latest row_version.",
    gateFlag: "enableAccountBenefits",
    envFlags: ["NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS"],
    disabledTitle: "Benefits are not in this rollout",
    disabledDescription:
      "Loyalty and vouchers stay off by default. Enable the account-benefits rollout flag only for a deliberate QA or Wave 2 pass.",
    liveProofSummary:
      "Loyalty, vouchers, reservation benefits preview, voucher apply/remove, loyalty redeem/release, stale row-version refresh, and reservation benefit state merge have live proof behind the account-benefits flag.",
  },
  {
    id: "privacy-requests",
    feature: "Privacy requests",
    releaseWave: "wave-2",
    status: "live-conditional",
    exposure: "env-flag",
    evidence: "Generated SDK Customer Privacy batch plus gated live proof for list and idempotent anonymization request creation.",
    routes: ["GET /api/v1/me/privacy-requests", "POST /api/v1/me/privacy-requests"],
    requiredHeaders: ["X-Customer-Token", "Idempotency-Key"],
    frontendDecision: "Privacy request entry points stay disabled by default and only open when the privacy-tools flag is enabled.",
    gateFlag: "enablePrivacyTools",
    envFlags: ["NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS"],
    disabledTitle: "Privacy tools are not in this rollout",
    disabledDescription:
      "Privacy requests stay off by default and only open during a dedicated QA, UAT, or Wave 2 rollout.",
    liveProofSummary:
      "Privacy request list and idempotent anonymization request creation have live proof behind the privacy-tools flag. Request processing remains an operator lifecycle outside browser proof.",
  },
  {
    id: "data-export",
    feature: "Data export",
    releaseWave: "wave-2",
    status: "live-conditional",
    exposure: "env-flag",
    evidence: "Generated SDK Customer Privacy batch plus gated live proof for customer data export reads.",
    routes: ["GET /api/v1/me/data-export"],
    requiredHeaders: ["X-Customer-Token"],
    frontendDecision: "Data export remains an explicit Wave 2 extra and should never become a go-live dependency for booking core.",
    gateFlag: "enableDataExport",
    envFlags: ["NEXT_PUBLIC_FEATURE_DATA_EXPORT"],
    disabledTitle: "Data export is not in this rollout",
    disabledDescription:
      "Data export stays off until the broader privacy rollout is ready. Keep it disabled unless QA or UAT specifically needs export proof.",
    liveProofSummary:
      "Customer data export reads have live proof behind the data-export flag and still require the broader privacy-tools flag in the UI.",
  },
  {
    id: "dev-mock-adapter",
    feature: "Dev mock adapter",
    releaseWave: "dev-only",
    status: "local-uat-only",
    exposure: "local-only",
    evidence: "Frontend-only adapter selected by NEXT_PUBLIC_ENABLE_DEV_MOCKS outside production.",
    routes: ["SDK-compatible in-memory fetch responses"],
    requiredHeaders: [],
    frontendDecision: "Use mock adapters only for local or controlled UAT diagnostics when the backend is unavailable. They are never live proof.",
    gateFlag: "enableDevMocks",
    envFlags: ["NEXT_PUBLIC_ENABLE_DEV_MOCKS"],
    disabledTitle: "Mock adapters are off",
    disabledDescription: "Live API paths stay active unless mock mode is explicitly enabled outside production.",
    liveProofSummary: "Mock adapters are local or UAT-only diagnostics and never count as production-ready runtime proof.",
  },
];

const supportMatrixById = new Map(customerWebSupportMatrix.map((entry) => [entry.id, entry] as const));

export function getSupportMatrixEntry(featureOrId: string): SupportMatrixEntry | undefined {
  return customerWebSupportMatrix.find((entry) => entry.feature === featureOrId || entry.id === featureOrId);
}

export function getSupportMatrixEntryById(id: CustomerSurfaceId): SupportMatrixEntry | undefined {
  return supportMatrixById.get(id);
}

export function getSupportMatrixByReleaseWave(releaseWave: ReleaseWave): SupportMatrixEntry[] {
  return customerWebSupportMatrix.filter((entry) => entry.releaseWave === releaseWave);
}

export function resolveSupportMatrixDecisions(flags: SupportMatrixEnv = {}): Record<CustomerSurfaceId, SurfaceRolloutDecision> {
  return customerWebSupportMatrix.reduce<Record<CustomerSurfaceId, SurfaceRolloutDecision>>((accumulator, entry) => {
    const envRequested = entry.gateFlag ? Boolean(flags[entry.gateFlag]) : false;
    const enabled = resolveExposure(entry, envRequested);

    accumulator[entry.id] = {
      id: entry.id,
      feature: entry.feature,
      releaseWave: entry.releaseWave,
      supportStatus: entry.status,
      exposure: entry.exposure,
      envFlag: entry.gateFlag ?? null,
      envRequested,
      enabled,
      liveReady: entry.status === "live-ready",
      liveConditional: entry.status === "live-conditional",
      ciSafeOnly: entry.status === "ci-safe-only",
      localUatOnly: entry.status === "local-uat-only",
      rolloutGated: entry.status === "rollout-gated",
      blocked: entry.status === "blocked",
      title: supportStatusTitle(entry.status),
      description: entry.frontendDecision,
      disabledTitle: entry.disabledTitle ?? defaultDisabledTitle(entry),
      disabledDescription: entry.disabledDescription ?? defaultDisabledDescription(entry),
      liveProofSummary: entry.liveProofSummary ?? defaultLiveProofSummary(entry),
    };

    return accumulator;
  }, {} as Record<CustomerSurfaceId, SurfaceRolloutDecision>);
}

export function resolveSupportMatrixDecision(
  surfaceId: CustomerSurfaceId,
  flags: SupportMatrixEnv = {},
): SurfaceRolloutDecision {
  const entry = getSupportMatrixEntryById(surfaceId);

  if (!entry) {
    return createUnknownSupportMatrixDecision(surfaceId);
  }

  return resolveSupportMatrixDecisions(flags)[surfaceId];
}

export function createUnknownSupportMatrixDecision(feature: string): SurfaceRolloutDecision {
  return {
    id: "reservations",
    feature,
    releaseWave: "deferred",
    supportStatus: "blocked",
    exposure: "blocked",
    envFlag: null,
    envRequested: false,
    enabled: false,
    liveReady: false,
    liveConditional: false,
    ciSafeOnly: false,
    localUatOnly: false,
    rolloutGated: false,
    blocked: true,
    title: "Blocked",
    description: "This surface is not declared in the support matrix.",
    disabledTitle: `${feature} is not available`,
    disabledDescription: "Unknown customer-web surfaces fail closed until the support matrix explicitly allows them.",
    liveProofSummary: "Unknown surfaces never count as rollout or runtime proof.",
  };
}

function resolveExposure(entry: SupportMatrixEntry, envRequested: boolean): boolean {
  switch (entry.exposure) {
    case "default-on":
      return entry.status !== "blocked";
    case "env-flag":
    case "local-only":
      return entry.status !== "blocked" && envRequested;
    case "blocked":
    default:
      return false;
  }
}

function supportStatusTitle(status: SupportStatus): string {
  switch (status) {
    case "live-ready":
      return "Live-ready";
    case "live-conditional":
      return "Live-conditional";
    case "ci-safe-only":
      return "CI-safe only";
    case "local-uat-only":
      return "Local or UAT only";
    case "rollout-gated":
      return "Rollout gated";
    case "blocked":
    default:
      return "Blocked";
  }
}

function defaultDisabledTitle(entry: SupportMatrixEntry): string {
  switch (entry.status) {
    case "rollout-gated":
      return `${entry.feature} is not in this rollout`;
    case "local-uat-only":
      return `${entry.feature} is local or UAT only`;
    case "ci-safe-only":
      return `${entry.feature} is outside live launch proof`;
    case "blocked":
      return `${entry.feature} is blocked`;
    case "live-ready":
    case "live-conditional":
    default:
      return `${entry.feature} is not available`;
  }
}

function defaultDisabledDescription(entry: SupportMatrixEntry): string {
  switch (entry.status) {
    case "rollout-gated":
      return "This surface stays off by default until its dedicated rollout flag is intentionally enabled.";
    case "local-uat-only":
      return "This proof path is for local or controlled UAT diagnostics only and does not count as live rollout support.";
    case "ci-safe-only":
      return "This surface may stay visible for contract or CI coverage, but it is outside the live launch proof boundary.";
    case "blocked":
      return "This surface stays disabled until backend support and rollout prerequisites are proven.";
    case "live-ready":
    case "live-conditional":
    default:
      return "This surface is not available in the current runtime.";
  }
}

function defaultLiveProofSummary(entry: SupportMatrixEntry): string {
  switch (entry.status) {
    case "live-ready":
      return "This surface is within the current live rollout boundary.";
    case "live-conditional":
      return "This surface is live only when its runtime prerequisites are genuinely present.";
    case "ci-safe-only":
      return "This surface is safe for deterministic contract or CI checks, but it does not count as live launch proof.";
    case "local-uat-only":
      return "This surface is only valid for local or controlled UAT diagnostics.";
    case "rollout-gated":
      return "This surface stays disabled until its rollout gate is explicitly enabled.";
    case "blocked":
    default:
      return "This surface stays blocked until support is proven.";
  }
}
