import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RegisterPage } from "./register-page";

type RegisterClient = {
  postV1AuthCustomerRegister: ReturnType<typeof vi.fn>;
};

const mocks = vi.hoisted(() => {
  const client = {
    postV1AuthCustomerRegister: vi.fn(),
  };

  return {
    apiCall: vi.fn((operation: (client: RegisterClient) => Promise<unknown>) => operation(client)),
    client,
    ensureCustomerSessionId: vi.fn(),
    storeCustomerAuthSession: vi.fn(),
    markAuthenticated: vi.fn(),
    push: vi.fn(),
    searchParams: "next=/reservations",
    toastSuccess: vi.fn(),
    getCustomerAuthRuntimeBlock: vi.fn(),
  };
});

vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: mocks.push,
  }),
  useSearchParams: () => new URLSearchParams(mocks.searchParams),
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => ({
    markAuthenticated: mocks.markAuthenticated,
  }),
}));

vi.mock("@/lib/auth/runtime-block", () => ({
  getCustomerAuthRuntimeBlock: mocks.getCustomerAuthRuntimeBlock,
}));

vi.mock("@/lib/auth/storage", () => ({
  ensureCustomerSessionId: mocks.ensureCustomerSessionId,
  getCustomerToken: vi.fn(() => null),
  storeCustomerAuthSession: mocks.storeCustomerAuthSession,
}));

vi.mock("@/lib/api/sdk-client", () => ({
  apiCall: mocks.apiCall,
}));

vi.mock("sonner", () => ({
  toast: {
    success: mocks.toastSuccess,
  },
}));

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <RegisterPage />
    </QueryClientProvider>,
  );
}

async function fillValidRegistration(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText("Họ tên"), "Demo Customer");
  await user.type(screen.getByLabelText("Email"), "demo@example.test");
  await user.type(screen.getByLabelText("Mật khẩu"), "password123");
  await user.type(screen.getByLabelText("Nhập lại mật khẩu"), "password123");
}

describe("RegisterPage", () => {
  beforeEach(() => {
    mocks.apiCall.mockClear();
    mocks.apiCall.mockImplementation((operation: (client: typeof mocks.client) => Promise<unknown>) => operation(mocks.client));
    mocks.client.postV1AuthCustomerRegister.mockReset();
    mocks.ensureCustomerSessionId.mockReset();
    mocks.ensureCustomerSessionId.mockReturnValue("browser-session-1");
    mocks.storeCustomerAuthSession.mockReset();
    mocks.markAuthenticated.mockReset();
    mocks.push.mockReset();
    mocks.searchParams = "next=/reservations";
    mocks.toastSuccess.mockReset();
    mocks.getCustomerAuthRuntimeBlock.mockReset();
    mocks.getCustomerAuthRuntimeBlock.mockReturnValue(null);
  });

  it("renders the registration form", () => {
    renderPage();

    expect(screen.getByText("Đăng ký")).toBeInTheDocument();
    expect(screen.getByLabelText("Họ tên")).toBeInTheDocument();
    expect(screen.getByLabelText("Email")).toBeInTheDocument();
    expect(screen.getByLabelText("Số điện thoại")).toBeInTheDocument();
    expect(screen.getByLabelText("Mật khẩu")).toBeInTheDocument();
    expect(screen.getByLabelText("Nhập lại mật khẩu")).toBeInTheDocument();
  });

  it("shows client validation when password confirmation does not match", async () => {
    const user = userEvent.setup();

    renderPage();

    await user.type(screen.getByLabelText("Họ tên"), "Demo Customer");
    await user.type(screen.getByLabelText("Email"), "demo@example.test");
    await user.type(screen.getByLabelText("Mật khẩu"), "password123");
    await user.type(screen.getByLabelText("Nhập lại mật khẩu"), "password456");
    await user.click(screen.getByRole("button", { name: "Tạo tài khoản" }));

    expect(await screen.findByText("Mật khẩu nhập lại chưa khớp.")).toBeInTheDocument();
    expect(mocks.client.postV1AuthCustomerRegister).not.toHaveBeenCalled();
  });

  it("stores the session, marks authenticated, and redirects after registration", async () => {
    const user = userEvent.setup();
    const session = {
      data: {
        access_token: "token-1",
        session_id: "session-1",
        expires_at_utc: "2026-05-07T10:00:00Z",
        user: {
          user_id: 77,
          full_name: "Demo Customer",
          email: "demo@example.test",
          phone: null,
        },
      },
    };

    mocks.client.postV1AuthCustomerRegister.mockResolvedValue(session);

    renderPage();

    await fillValidRegistration(user);
    await user.click(screen.getByRole("button", { name: "Tạo tài khoản" }));

    await waitFor(() => {
      expect(mocks.client.postV1AuthCustomerRegister).toHaveBeenCalledWith({
        full_name: "Demo Customer",
        email: "demo@example.test",
        phone: undefined,
        password: "password123",
        password_confirmation: "password123",
        session_id: "browser-session-1",
        session_label: "customer-web",
      });
    });
    expect(mocks.storeCustomerAuthSession).toHaveBeenCalledWith(session);
    expect(mocks.markAuthenticated).toHaveBeenCalledWith(session);
    expect(mocks.toastSuccess).toHaveBeenCalledWith("Đã tạo tài khoản.");
    expect(mocks.push).toHaveBeenCalledWith("/reservations");
  });

  it("ignores duplicate form submits while registration is pending", async () => {
    const user = userEvent.setup();

    mocks.client.postV1AuthCustomerRegister.mockReturnValue(new Promise(() => {}));

    renderPage();

    await fillValidRegistration(user);
    const submitButton = screen.getByRole("button", { name: "Tạo tài khoản" });
    await user.click(submitButton);

    const form = submitButton.closest("form");
    expect(form).not.toBeNull();
    fireEvent.submit(form as HTMLFormElement);

    expect(mocks.client.postV1AuthCustomerRegister).toHaveBeenCalledTimes(1);
  });

  it("shows a user-facing backend validation error", async () => {
    const user = userEvent.setup();

    mocks.client.postV1AuthCustomerRegister.mockRejectedValue({
      kind: "validation",
      status: 422,
      message: "Email đã được sử dụng.",
      errorCode: "validation_error",
      categoryCode: "validation_error",
      requestId: "req-register-1",
      validationErrors: {
        email: ["Email đã được sử dụng."],
      },
    });

    renderPage();

    await fillValidRegistration(user);
    await user.click(screen.getByRole("button", { name: "Tạo tài khoản" }));

    expect(await screen.findByText("Email đã được sử dụng.")).toBeInTheDocument();
    expect(mocks.storeCustomerAuthSession).not.toHaveBeenCalled();
    expect(mocks.markAuthenticated).not.toHaveBeenCalled();
  });
});
