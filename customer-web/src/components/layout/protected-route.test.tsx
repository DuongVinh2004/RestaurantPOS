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
    authError: null as null | {
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

  it("shows retry-first restore actions when the backend is unavailable", async () => {
    const user = userEvent.setup();

    mocks.authState.authError = {
      kind: "backend_unavailable",
      restoreKind: "backend_unavailable",
      status: null,
      message: "Hiện chưa kết nối được dịch vụ nhà hàng.",
      errorCode: "backend_unavailable",
      categoryCode: "backend_unavailable",
      requestId: null,
      validationErrors: null,
    };

    render(<ProtectedRoute><div>secret content</div></ProtectedRoute>);

    expect(screen.queryByText("secret content")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Kiểm tra lại" }));
    expect(mocks.retryBootstrap).toHaveBeenCalledTimes(1);

    await user.click(screen.getByRole("button", { name: "Đặt lại phiên" }));
    expect(mocks.logout).toHaveBeenCalledTimes(1);
  });

  it("routes expired sessions through sign-in with the preserved next path", async () => {
    const user = userEvent.setup();

    mocks.authState.authError = {
      kind: "unauthorized",
      restoreKind: "token_expired",
      status: 401,
      message: "Phiên đăng nhập đã hết hạn.",
      errorCode: "session_expired",
      categoryCode: "authentication_required",
      requestId: null,
      validationErrors: null,
    };

    render(<ProtectedRoute><div>secret content</div></ProtectedRoute>);

    await user.click(screen.getByRole("button", { name: "Đăng nhập" }));
    expect(mocks.logout).toHaveBeenCalledWith({ nextPath: "/reservations" });

    await user.click(screen.getByRole("button", { name: "Đặt lại phiên" }));
    expect(mocks.logout).toHaveBeenLastCalledWith();
  });

  it("preserves the current query string when sending signed-out users to login", () => {
    mocks.authState.isAuthenticated = false;
    mocks.pathname = "/account";
    mocks.searchParams = "view=profile";

    render(<ProtectedRoute><div>secret content</div></ProtectedRoute>);

    expect(screen.getByRole("link", { name: "Đăng nhập" })).toHaveAttribute(
      "href",
      "/login?next=%2Faccount%3Fview%3Dprofile",
    );
  });

  it("allows signed-out reservation routes when the browser has a customer session id", async () => {
    ensureCustomerSessionId();
    mocks.pathname = "/reservations/new";
    mocks.searchParams = "hold_id=hold-123";
    mocks.authState.isAuthenticated = false;

    render(<ProtectedRoute><div>reservation form</div></ProtectedRoute>);

    expect(await screen.findByText("reservation form")).toBeInTheDocument();
    expect(screen.queryByText("Đăng nhập để tiếp tục")).not.toBeInTheDocument();
  });

  it("keeps account-only routes behind sign-in even when a customer session id exists", () => {
    ensureCustomerSessionId();
    mocks.pathname = "/account";
    mocks.authState.isAuthenticated = false;

    render(<ProtectedRoute><div>account content</div></ProtectedRoute>);

    expect(screen.queryByText("account content")).not.toBeInTheDocument();
    expect(screen.getByText("Đăng nhập để tiếp tục")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Đăng nhập" })).toHaveAttribute("href", "/login?next=%2Faccount");
  });

  it("starts a guest reservation session only after explicit customer action", async () => {
    const user = userEvent.setup();

    mocks.pathname = "/reservations/new";
    mocks.searchParams = "hold_id=hold-123";
    mocks.authState.isAuthenticated = false;

    render(<ProtectedRoute><div>reservation form</div></ProtectedRoute>);

    expect(screen.queryByText("reservation form")).not.toBeInTheDocument();
    expect(await screen.findByText("Tiếp tục với tư cách khách hoặc đăng nhập")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Tiếp tục với tư cách khách" })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Đăng nhập" })).toHaveAttribute(
      "href",
      "/login?next=%2Freservations%2Fnew%3Fhold_id%3Dhold-123",
    );

    await user.click(screen.getByRole("button", { name: "Tiếp tục với tư cách khách" }));

    expect(await screen.findByText("reservation form")).toBeInTheDocument();
  });

  it("routes misconfigured-runtime restores back through login with the preserved next path", async () => {
    const user = userEvent.setup();

    mocks.searchParams = "view=active";
    mocks.authState.authError = {
      kind: "backend_unavailable",
      restoreKind: "backend_unavailable",
      status: null,
      message:
        "Ứng dụng đang chạy trên uat.customer-web.example, nhưng NEXT_PUBLIC_API_BASE_URL vẫn trỏ tới http://127.0.0.1:8000.",
      errorCode: "api_base_url_misconfigured",
      categoryCode: "api_base_url_misconfigured",
      requestId: null,
      validationErrors: null,
    };

    render(<ProtectedRoute><div>secret content</div></ProtectedRoute>);

    await user.click(screen.getByRole("button", { name: "Đăng nhập" }));

    expect(mocks.logout).toHaveBeenCalledWith({ nextPath: "/reservations?view=active" });
  });
});
