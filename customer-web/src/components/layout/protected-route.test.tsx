import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ProtectedRoute } from "./protected-route";

const mocks = vi.hoisted(() => ({
  retryBootstrap: vi.fn(),
  logout: vi.fn(),
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
    },
  },
}));

vi.mock("next/navigation", () => ({
  usePathname: () => "/reservations",
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
    expect(mocks.logout).toHaveBeenCalledTimes(1);

    await user.click(screen.getByRole("button", { name: "Reset session" }));
    expect(mocks.logout).toHaveBeenCalledTimes(2);
  });
});
