import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { LoginPage } from "./login-page";

const mocks = vi.hoisted(() => ({
  loginCustomer: vi.fn(),
  markAuthenticated: vi.fn(),
  push: vi.fn(),
  toastSuccess: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: mocks.push,
  }),
  useSearchParams: () => new URLSearchParams("next=/reservations"),
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => ({
    markAuthenticated: mocks.markAuthenticated,
  }),
}));

vi.mock("sonner", () => ({
  toast: {
    success: mocks.toastSuccess,
  },
}));

vi.mock("./api", () => ({
  loginCustomer: mocks.loginCustomer,
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
      <LoginPage />
    </QueryClientProvider>,
  );
}

describe("LoginPage", () => {
  beforeEach(() => {
    mocks.loginCustomer.mockReset();
    mocks.markAuthenticated.mockReset();
    mocks.push.mockReset();
    mocks.toastSuccess.mockReset();
  });

  it("signs in and redirects to the requested next route", async () => {
    const user = userEvent.setup();
    const session = {
      data: {
        access_token: "token-1",
        session_id: "session-1",
      },
    };

    mocks.loginCustomer.mockResolvedValue(session);

    renderPage();

    await user.type(screen.getByLabelText("Email, phone, or customer id"), "demo@example.test");
    await user.type(screen.getByLabelText("Password"), "password123");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    await waitFor(() => {
      expect(mocks.loginCustomer).toHaveBeenCalledWith({
        identifier: "demo@example.test",
        password: "password123",
      }, expect.any(Object));
    });
    expect(mocks.markAuthenticated).toHaveBeenCalledWith(session);
    expect(mocks.toastSuccess).toHaveBeenCalledWith("Signed in.");
    expect(mocks.push).toHaveBeenCalledWith("/reservations");
  });

  it("shows the backend validation message when credentials are rejected", async () => {
    const user = userEvent.setup();

    mocks.loginCustomer.mockRejectedValue({
      kind: "validation",
      status: 422,
      message: "Invalid credentials.",
      errorCode: "validation_error",
      categoryCode: "validation_error",
      requestId: "req-login-1",
      validationErrors: {
        identifier: ["Invalid credentials."],
      },
    });

    renderPage();

    await user.type(screen.getByLabelText("Email, phone, or customer id"), "demo@example.test");
    await user.type(screen.getByLabelText("Password"), "wrong-password");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByText("Invalid credentials.")).toBeInTheDocument();
    expect(mocks.markAuthenticated).not.toHaveBeenCalled();
  });
});
