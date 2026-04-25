import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { clearStoredCustomerAuth, getCustomerSessionId, getCustomerToken, storeCustomerAuthSession } from "@/lib/auth/storage";
import { AuthProvider, useAuth } from "./auth-provider";

const mocks = vi.hoisted(() => ({
  bootstrapCustomerSession: vi.fn(),
  getCustomerAuthRuntimeBlock: vi.fn(),
  logoutCustomer: vi.fn(),
  push: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: mocks.push,
  }),
}));

vi.mock("@/features/auth/api", () => ({
  bootstrapCustomerSession: mocks.bootstrapCustomerSession,
  logoutCustomer: mocks.logoutCustomer,
}));

vi.mock("@/lib/auth/runtime-block", () => ({
  getCustomerAuthRuntimeBlock: mocks.getCustomerAuthRuntimeBlock,
}));

function AuthProbe() {
  const { authError, isAuthenticated, isBootstrapping, logout, profile } = useAuth();

  if (isBootstrapping) {
    return <p>bootstrapping</p>;
  }

  if (authError) {
    return <p>{`error:${authError.restoreKind}`}</p>;
  }

  return (
    <>
      <p>{isAuthenticated ? `authenticated:${profile?.name ?? "Customer"}` : "signed-out"}</p>
      <button type="button" onClick={() => void logout()}>
        sign-out
      </button>
    </>
  );
}

function renderProvider() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <AuthProbe />
      </AuthProvider>
    </QueryClientProvider>,
  );
}

describe("AuthProvider", () => {
  beforeEach(() => {
    clearStoredCustomerAuth();
    window.localStorage.clear();
    window.sessionStorage.clear();
    mocks.bootstrapCustomerSession.mockReset();
    mocks.getCustomerAuthRuntimeBlock.mockReset();
    mocks.getCustomerAuthRuntimeBlock.mockReturnValue(null);
    mocks.logoutCustomer.mockReset();
    mocks.push.mockReset();
  });

  it("restores an authenticated customer after bootstrap succeeds", async () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-restored",
        access_session_id: 90,
        session_id: "session-restored",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 90, full_name: "Restored Customer" },
      },
    });

    mocks.bootstrapCustomerSession.mockResolvedValue({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-restored",
        access_session_id: 90,
        session_id: "session-restored",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 90, full_name: "Restored Customer" },
      },
    });

    renderProvider();

    expect(await screen.findByText("authenticated:Restored Customer")).toBeInTheDocument();
    expect(mocks.bootstrapCustomerSession).toHaveBeenCalledTimes(1);
    expect(getCustomerToken()).toBe("token-restored");
    expect(getCustomerSessionId()).toBe("session-restored");
  });

  it("syncs refreshed session metadata from bootstrap without dropping the stored token", async () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-bootstrap",
        access_session_id: 94,
        session_id: "session-bootstrap-old",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 94, full_name: "Bootstrap Customer" },
      },
    });

    mocks.bootstrapCustomerSession.mockResolvedValue({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: null,
        access_session_id: 95,
        session_id: "session-bootstrap-new",
        expires_at_utc: "2031-04-18T00:00:00Z",
        user: { user_id: 94, full_name: "Bootstrap Customer" },
      },
    });

    renderProvider();

    expect(await screen.findByText("authenticated:Bootstrap Customer")).toBeInTheDocument();
    expect(getCustomerToken()).toBe("token-bootstrap");
    expect(getCustomerSessionId()).toBe("session-bootstrap-new");
  });

  it("clears stored auth when bootstrap ends in an unauthorized state", async () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-expired",
        access_session_id: 91,
        session_id: "session-expired",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 91, full_name: "Expired Customer" },
      },
    });

    mocks.bootstrapCustomerSession.mockRejectedValue({
      kind: "unauthorized",
      status: 401,
      message: "Please sign in again to continue.",
      errorCode: "authentication_required",
      categoryCode: "authentication_required",
      requestId: "req-auth-1",
      validationErrors: null,
    });

    renderProvider();

    expect(await screen.findByText("error:invalid_session")).toBeInTheDocument();
    await waitFor(() => {
      expect(getCustomerToken()).toBeNull();
      expect(getCustomerSessionId()).toBeNull();
    });
  });

  it("classifies locally expired tokens before session bootstrap runs", async () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-expired",
        access_session_id: 92,
        session_id: "session-expired",
        expires_at_utc: "2020-04-18T00:00:00Z",
        user: { user_id: 92, full_name: "Expired Customer" },
      },
    });

    renderProvider();

    expect(await screen.findByText("error:token_expired")).toBeInTheDocument();
    expect(mocks.bootstrapCustomerSession).not.toHaveBeenCalled();
    expect(getCustomerToken()).toBeNull();
    expect(getCustomerSessionId()).toBeNull();
  });

  it("keeps stored auth and surfaces owner-scope restore failures without treating them as invalid sessions", async () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-owner-scope",
        access_session_id: 96,
        session_id: "session-owner-scope",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 96, full_name: "Owner Scope Customer" },
      },
    });

    mocks.bootstrapCustomerSession.mockRejectedValue({
      kind: "forbidden",
      status: 403,
      message: "The authenticated actor is outside the required owner scope.",
      errorCode: "forbidden",
      categoryCode: "owner_scope_denied",
      requestId: "req-owner-scope-1",
      validationErrors: null,
    });

    renderProvider();

    expect(await screen.findByText("error:unauthorized_owner_access")).toBeInTheDocument();
    expect(getCustomerToken()).toBe("token-owner-scope");
    expect(getCustomerSessionId()).toBe("session-owner-scope");
  });

  it("clears both token and session id on logout", async () => {
    const user = userEvent.setup();

    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-logout",
        access_session_id: 93,
        session_id: "session-logout",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 93, full_name: "Logout Customer" },
      },
    });

    mocks.bootstrapCustomerSession.mockResolvedValue({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-logout",
        access_session_id: 93,
        session_id: "session-logout",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 93, full_name: "Logout Customer" },
      },
    });
    mocks.logoutCustomer.mockResolvedValue(undefined);

    renderProvider();

    await screen.findByText("authenticated:Logout Customer");
    await user.click(screen.getByRole("button", { name: "sign-out" }));

    await waitFor(() => {
      expect(getCustomerToken()).toBeNull();
      expect(getCustomerSessionId()).toBeNull();
    });
    expect(mocks.push).toHaveBeenCalledWith("/login");
  });

  it("fails closed before bootstrap when the runtime points at a local API host from a non-local app host", async () => {
    storeCustomerAuthSession({
      data: {
        auth_mode: "customer_access_session",
        token_type: "opaque",
        auth_header: "X-Customer-Token",
        access_token: "token-runtime-blocked",
        access_session_id: 97,
        session_id: "session-runtime-blocked",
        expires_at_utc: "2030-04-18T00:00:00Z",
        user: { user_id: 97, full_name: "Runtime Blocked Customer" },
      },
    });

    mocks.getCustomerAuthRuntimeBlock.mockReturnValue({
      kind: "backend_unavailable",
      restoreKind: "backend_unavailable",
      status: null,
      message:
        "This app is running on uat.customer-web.example, but NEXT_PUBLIC_API_BASE_URL still points to http://127.0.0.1:8000. Update the customer-web runtime configuration to the correct API host and reload the page.",
      errorCode: "api_base_url_misconfigured",
      categoryCode: "api_base_url_misconfigured",
      requestId: null,
      validationErrors: null,
    });

    renderProvider();

    expect(await screen.findByText("error:backend_unavailable")).toBeInTheDocument();
    expect(mocks.bootstrapCustomerSession).not.toHaveBeenCalled();
    expect(getCustomerToken()).toBe("token-runtime-blocked");
    expect(getCustomerSessionId()).toBe("session-runtime-blocked");
  });
});
