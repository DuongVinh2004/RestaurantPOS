import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
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
    mocks.retryBootstrap.mockReset();
    mocks.logout.mockReset();
    mocks.pathname = "/reservations";
    mocks.searchParams = "";
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
    mocks.searchParams = "bucket=upcoming&view=calendar";

    render(<ProtectedRoute><div>secret content</div></ProtectedRoute>);

    expect(screen.getByRole("link", { name: "Sign in" })).toHaveAttribute(
      "href",
      "/login?next=%2Freservations%3Fbucket%3Dupcoming%26view%3Dcalendar",
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
