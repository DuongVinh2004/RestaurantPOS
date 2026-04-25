type SearchParamsLike = {
  toString(): string;
};

const defaultCustomerAuthRedirect = "/reservations";

export function sanitizeCustomerAuthRedirect(nextPath: string | null | undefined, fallback = defaultCustomerAuthRedirect): string {
  const candidate = (nextPath ?? "").trim();

  if (candidate === "" || !candidate.startsWith("/") || candidate.startsWith("//") || candidate.startsWith("/\\")) {
    return fallback;
  }

  if (candidate === "/login" || candidate.startsWith("/login?")) {
    return fallback;
  }

  return candidate;
}

export function buildCustomerAuthNext(
  pathname: string | null | undefined,
  searchParams: SearchParamsLike | null | undefined,
  fallback = defaultCustomerAuthRedirect,
): string {
  const basePath = sanitizeCustomerAuthRedirect(pathname, fallback);
  const query = searchParams?.toString().trim();

  if (!query) {
    return basePath;
  }

  return `${basePath}?${query}`;
}

export function buildCustomerLoginHref(nextPath: string | null | undefined): string {
  const safeNext = sanitizeCustomerAuthRedirect(nextPath);

  return `/login?next=${encodeURIComponent(safeNext)}`;
}
