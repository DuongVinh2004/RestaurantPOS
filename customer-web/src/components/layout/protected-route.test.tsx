import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { clearStoredCustomerAuth, ensureCustomerSessionId } from "@/lib/auth/storage";
import { ProtectedRoute } from "./protected-route";

const mocks = vi.hoisted(() => ({
  retryBootstrap: vi.fn(),
  logout: vi.fn(),
  pathname: "/reservations",
  searchParams: "",
  authState: {
    isAuthenticated: false,
    isBootstrapping: false,
    profile: null,
    session: null,
    authError: {
      kind: "backend_unavailable",
      restoreKind: "backend_unavailable",
      status: null as number | null,
      message: "The restaurant service is not reachable right now.",
      errorCode: "backend_unavailable",
      categoryCode: "backend_unavailable",
      requestId: null,
      validationErrors: null,
    } as null | {
      kind: string;
      restoreKind: string;
      status: number | null;
      message: string;
      errorCode: string;
      categoryCode: string;
      requestId: null;
      validationErrors: null;
    },
  },
}));

vi.mock("next/navigation", () => ({
  usePathname: () => mocks.pathname,
  useSearchParams: () => new URLSearchParams(mocks.searchParams),
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => ({
    ...mocks.authState,
    markAuthenticated: vi.fn(),
    retryBootstrap: mocks.retryBootstrap,
    logout: mocks.logout,
  }),
}));

describe("ProtectedRoute", () => {
  beforeEach(() => {
    clearStoredCustomerAuth();
    window.localStorage.clear();
    window.sessionStorage.clear();
    mocks.retryBootstrap.mockReset();
    mocks.logout.mockReset();
    mocks.pathname = "/reservations";
    mocks.searchParams = "";
    mocks.authState.isAuthenticated = false;
    mocks.authState.isBootstrapping = false;
    mocks.authState.profile = null;
    mocks.authState.session = null;
    mocks.authState.authError = null;
  });

  it("shows a retry-first restore state when the backend is unavailable", async () => {
    const user = userEvent.setup();

    mocks.authState.authError = {
      kind: "backend_unavailable",
      restoreKind: "backend_unavailable",
      status: null,
      message: "The restaurant service is not reachable right now.",
      errorCode: "backend_unavailable",
      categoryCode: "backend_unavailable",
      requestId: null,
      validationErrors: null,
    };

    render(<ProtectedRoute><div>secret content</div></ProtectedRoute>);

    expect(screen.getByText("We could not reach the restaurant service")).toBeInTheDocument();
    expect(screen.queryByText("Sign in to continue")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Retry session check" }));
    expect(mocks.retryBootstrap).toHaveBeenCalledTimes(1);

    await user.click(screen.getByRole("button", { name: "Reset session" }));
    expect(mocks.logout).toHaveBeenCalledTimes(1);
  });

  it("shows retry and reset actions when session restore fails", async () => {
    const user = userEvent.setup();

    mocks.authState.authError = {
      kind: "unauthorized",
      restoreKind: "token_expired",
      status: 401,
      message: "Your saved sign-in has expired.",
      errorCode: "session_expired",
      categoryCode: "authentication_required",
      requestId: null,
      validationErrors: null,
    };

    render(<ProtectedRoute><div>secret content</div></ProtectedRoute>);

    expect(screen.getByText("Your session expired")).toBeInTheDocument();
    expect(screen.queryByText("Sign in to continue")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Go to sign in" }));
    expect(mocks.logout).toHaveBeenCalledWith({ nextPath: "/reservations" });

    await user.click(screen.getByRole("button", { name: "Reset session" }));
    expect(mocks.logout).toHaveBeenLastCalledWith();
  });

  it("preserves the current query string when sending signed-out users to login", () => {
    mocks.authState.authError = null;
    mocks.authState.isAuthenticated = false;
    mocks.pathname = "/account";
    mocks.searchParams = "view=profile";

    render(<ProtectedRoute><div>secret content</div></ProtectedRoute>);

    expect(screen.getByRole("link", { name: "Sign in" })).toHaveAttribute(
      "href",
      "/login?next=%2Faccount%3Fview%3Dprofile",
    );
  });

  it("allows signed-out reservation routes when the browser has a customer session id", () => {
    ensureCustomerSessionId();
    mocks.pathname = "/reservations/new";
    mocks.searchParams = "hold_id=hold-123";
    mocks.authState.authError = null;
    mocks.authState.isAuthenticated = false;

    render(<ProtectedRoute><div>reservation form</div></ProtectedRoute>);

    expect(screen.getByText("reservation form")).toBeInTheDocument();
    expect(screen.queryByText("Sign in to continue")).not.toBeInTheDocument();
  });

  it("keeps account-only routes behind sign-in even when a customer session id exists", () => {
    ensureCustomerSessionId();
    mocks.pathname = "/account";
    mocks.authState.authError = null;
    mocks.authState.isAuthenticated = false;

    render(<ProtectedRoute><div>account content</div></ProtectedRoute>);

    expect(screen.queryByText("account content")).not.toBeInTheDocument();
    expect(screen.getByText("Sign in to continue")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Sign in" })).toHaveAttribute("href", "/login?next=%2Faccount");
  });

  it("does not create a guest reservation session just to pass the route guard", () => {
    mocks.pathname = "/reservations/new";
    mocks.searchParams = "hold_id=hold-123";
    mocks.authState.authError = null;
    mocks.authState.isAuthenticated = false;

    render(<ProtectedRoute><div>reservation form</div></ProtectedRoute>);

    expect(screen.queryByText("reservation form")).not.toBeInTheDocument();
    expect(screen.getByText("Booking session needed")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Find a table" })).toHaveAttribute("href", "/booking");
    expect(screen.getByRole("link", { name: "Sign in" })).toHaveAttribute(
      "href",
      "/login?next=%2Freservations%2Fnew%3Fhold_id%3Dhold-123",
    );
  });

  it("routes misconfigured-runtime restores back through login with the preserved next path", async () => {
    const user = userEvent.setup();

    mocks.searchParams = "view=active";
    mocks.authState.authError = {
      kind: "backend_unavailable",
      restoreKind: "backend_unavailable",
      status: null,
      message:
        "This app is running on uat.customer-web.example, but NEXT_PUBLIC_API_BASE_URL still points to http://127.0.0.1:8000.",
      errorCode: "api_base_url_misconfigured",
      categoryCode: "api_base_url_misconfigured",
      requestId: null,
      validationErrors: null,
    };

    render(<ProtectedRoute><div>secret content</div></ProtectedRoute>);

    expect(await screen.findByText("Sign-in is blocked by runtime configuration")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Go to sign in" }));

    expect(mocks.logout).toHaveBeenCalledWith({ nextPath: "/reservations?view=active" });
  });
});
