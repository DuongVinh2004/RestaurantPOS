export const queryKeys = {
  auth: {
    current: ["auth", "current"] as const,
  },
  backend: {
    health: ["backend", "health"] as const,
  },
  restaurant: {
    profile: ["restaurant", "profile"] as const,
    branches: ["restaurant", "branches"] as const,
  },
  menu: {
    categories: (params?: unknown) => ["menu", "categories", params ?? {}] as const,
    items: (params?: unknown) => ["menu", "items", params ?? {}] as const,
    item: (id: number) => ["menu", "item", id] as const,
  },
  reservations: {
    lists: ["reservations", "list"] as const,
    list: (bucket = "upcoming") => ["reservations", "list", bucket] as const,
    detail: (id: number) => ["reservations", "detail", id] as const,
    preorder: (id: number) => ["reservations", "preorder", id] as const,
    deposit: (id: number) => ["reservations", "deposit", id] as const,
    depositPaymentSession: (id: number, sessionId: number | null) => ["reservations", "deposit-payment-session", id, sessionId] as const,
    activeOrder: (id: number) => ["reservations", "active-order", id] as const,
    bill: (id: number) => ["reservations", "bill", id] as const,
    billPreview: (id: number) => ["reservations", "bill-preview", id] as const,
    billPaymentSession: (id: number, sessionId: number | null) => ["reservations", "bill-payment-session", id, sessionId] as const,
    benefits: (id: number) => ["reservations", "benefits", id] as const,
  },
  tableBooking: {
    availability: (params?: unknown) => ["tables", "availability", params ?? {}] as const,
    hold: (id: string) => ["tables", "hold", id] as const,
  },
  waitingList: {
    list: ["waiting-list", "list"] as const,
    detail: (id: number) => ["waiting-list", "detail", id] as const,
  },
  account: {
    loyalty: ["account", "loyalty"] as const,
    vouchers: ["account", "vouchers"] as const,
    dataExport: ["account", "data-export"] as const,
    privacyRequests: ["account", "privacy-requests"] as const,
  },
};
