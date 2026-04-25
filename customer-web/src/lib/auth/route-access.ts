export type CustomerRouteAccessMode = "account" | "customer_session";

export type CustomerRouteAccess = {
  mode: CustomerRouteAccessMode;
  title: string;
  description: string;
};

const accountRouteAccess: CustomerRouteAccess = {
  mode: "account",
  title: "Sign in to continue",
  description: "Your customer account is needed to access this page.",
};

const customerSessionRouteAccess: CustomerRouteAccess = {
  mode: "customer_session",
  title: "Booking session needed",
  description: "Open this page from your table hold flow, or sign in to use account reservations.",
};

export function getCustomerRouteAccess(pathname: string | null | undefined): CustomerRouteAccess {
  return isCustomerSessionReservationRoute(normalizePathname(pathname))
    ? customerSessionRouteAccess
    : accountRouteAccess;
}

export function allowsCustomerSessionAccess(access: CustomerRouteAccess): boolean {
  return access.mode === "customer_session";
}

function normalizePathname(pathname: string | null | undefined): string {
  const trimmed = (pathname ?? "").trim();
  const withoutQuery = trimmed.split("?")[0]?.split("#")[0] ?? "";
  const normalized = withoutQuery.startsWith("/") ? withoutQuery : `/${withoutQuery}`;

  return normalized.length > 1 ? normalized.replace(/\/+$/, "") : normalized;
}

function isCustomerSessionReservationRoute(pathname: string): boolean {
  if (pathname === "/reservations" || pathname === "/reservations/new") {
    return true;
  }

  return /^\/reservations\/[1-9]\d*$/.test(pathname);
}
