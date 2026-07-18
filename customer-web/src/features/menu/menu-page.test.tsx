import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import userEvent from "@testing-library/user-event";
import { render, screen, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { MenuPage } from "./menu-page";

const mocks = vi.hoisted(() => ({
  featureFlags: {
    menuCategories: false,
    menuItemDetail: true,
    preorder: false,
  },
  listMenuCategories: vi.fn(),
  listMenuItems: vi.fn(),
  previewMenuPreorder: vi.fn(),
  routerReplace: vi.fn(),
  toastError: vi.fn(),
  toastSuccess: vi.fn(),
  branchSelection: {
    branches: [{ branchId: 10, branchName: "Chi nhánh trung tâm" }],
    selectedBranch: { branchId: 10, branchName: "Chi nhánh trung tâm" },
    selectedBranchId: 10,
    isLoading: false,
    error: null,
    locationPermission: "idle",
    locationMessage: null,
    selectBranch: vi.fn(),
    findNearMe: vi.fn(),
    refetch: vi.fn(),
  },
}));

vi.mock("next/link", () => ({
  default: ({ children, href, ...props }: { children: ReactNode; href: string }) => <a href={href} {...props}>{children}</a>,
}));

vi.mock("next/navigation", () => ({
  usePathname: () => "/menu",
  useRouter: () => ({
    replace: mocks.routerReplace,
  }),
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock("sonner", () => ({
  toast: {
    error: mocks.toastError,
    success: mocks.toastSuccess,
  },
}));

vi.mock("@/features/branch/hooks", () => ({
  useBranchSelection: () => mocks.branchSelection,
}));

vi.mock("@/providers/auth-provider", () => ({
  useAuth: () => ({ isAuthenticated: false }),
}));

vi.mock("@/lib/config/feature-flags", () => ({
  featureFlags: mocks.featureFlags,
}));

vi.mock("./api", () => ({
  listMenuCategories: mocks.listMenuCategories,
  listMenuItems: mocks.listMenuItems,
  previewMenuPreorder: mocks.previewMenuPreorder,
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
      <MenuPage />
    </QueryClientProvider>,
  );
}

function menuItem(overrides: Record<string, unknown> = {}) {
  return {
    item_id: 42,
    name: "Pho Bo",
    description: "Beef noodle soup",
    img_url: null,
    category_name: "Noodles",
    is_available: true,
    price: {
      amount: "12.00",
      currency: "USD",
    },
    preorder: {
      enabled: true,
    },
    ...overrides,
  };
}

describe("MenuPage", () => {
  beforeEach(() => {
    mocks.featureFlags.menuCategories = false;
    mocks.featureFlags.menuItemDetail = true;
    mocks.featureFlags.preorder = false;
    mocks.listMenuCategories.mockReset();
    mocks.listMenuItems.mockReset();
    mocks.previewMenuPreorder.mockReset();
    mocks.routerReplace.mockReset();
    mocks.toastError.mockReset();
    mocks.toastSuccess.mockReset();
    mocks.branchSelection.selectedBranch = { branchId: 10, branchName: "Chi nhánh trung tâm" };
    mocks.branchSelection.selectedBranchId = 10;
    mocks.listMenuItems.mockResolvedValue([menuItem()]);
    mocks.listMenuCategories.mockResolvedValue([]);
    window.localStorage.clear();
    window.sessionStorage.clear();
  });

  it("links to menu item detail and renders Vietnamese menu display text", async () => {
    renderPage();

    expect(await screen.findByRole("searchbox", { name: "Tìm trong thực đơn" })).toBeInTheDocument();
    expect(await screen.findByText("Phở bò")).toBeInTheDocument();
    expect(screen.getByText("Món nước")).toBeInTheDocument();
    expect(screen.getByText("Phở bò với nước dùng trong, ăn kèm rau thơm.")).toBeInTheDocument();

    const detailLink = await screen.findByRole("link", { name: "Chi tiết" });

    expect(detailLink).toHaveAttribute("href", "/menu/42");
  });

  it("keeps menu item detail closed when the detail feature flag is disabled", async () => {
    mocks.featureFlags.menuItemDetail = false;

    renderPage();

    expect(await screen.findByText("Phở bò")).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Chi tiết" })).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Chi tiết" })).toBeDisabled();
  });

  it("keeps preorder preview closed on the public menu until preorder is enabled", async () => {
    renderPage();

    expect(await screen.findByText("Phở bò")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Thêm món" })).not.toBeInTheDocument();
    expect(screen.getAllByRole("link", { name: "Đặt bàn" }).length).toBeGreaterThan(0);

    expect(mocks.previewMenuPreorder).not.toHaveBeenCalled();
  });

  it("updates the side cart immediately after adding a preorder item", async () => {
    const user = userEvent.setup();
    mocks.featureFlags.preorder = true;

    renderPage();

    expect(await screen.findByText("Phở bò")).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Sẵn sàng thêm món" })).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Thêm món" }));

    expect(await screen.findByRole("heading", { name: "1 món đã chọn" })).toBeInTheDocument();
    expect(screen.getByText("1/1 khả dụng")).toBeInTheDocument();
    expect(mocks.toastSuccess).toHaveBeenCalledWith("Đã thêm Phở bò vào giỏ đặt trước.");
  });

  it("paginates long menu lists instead of rendering every item at once", async () => {
    const user = userEvent.setup();
    mocks.listMenuItems.mockResolvedValue(
      Array.from({ length: 11 }, (_, index) =>
        menuItem({
          item_id: index + 1,
          name: `Món ${index + 1}`,
          description: `Mô tả ${index + 1}`,
          category_name: "Món chính",
        }),
      ),
    );

    renderPage();

    expect(await screen.findByText("Món 1")).toBeInTheDocument();
    expect(screen.getByText("Hiển thị 1-9 trong 11 món")).toBeInTheDocument();
    expect(screen.queryByText("Món 10")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Trang sau" }));

    expect(await screen.findByText("Món 10")).toBeInTheDocument();
    expect(screen.queryByText("Món 1")).not.toBeInTheDocument();
    expect(screen.getByText("Hiển thị 10-11 trong 11 món")).toBeInTheDocument();
    await waitFor(() => {
      expect(mocks.routerReplace).toHaveBeenLastCalledWith("/menu?page=2", { scroll: false });
    });
  });
});
