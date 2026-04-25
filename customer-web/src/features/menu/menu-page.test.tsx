import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { MenuPage } from "./menu-page";

const mocks = vi.hoisted(() => ({
  featureFlags: {
    menuCategories: false,
    menuItemDetail: true,
  },
  listMenuCategories: vi.fn(),
  listMenuItems: vi.fn(),
  previewMenuPreorder: vi.fn(),
  toastError: vi.fn(),
  toastSuccess: vi.fn(),
}));

vi.mock("next/link", () => ({
  default: ({ children, href, ...props }: { children: ReactNode; href: string }) => <a href={href} {...props}>{children}</a>,
}));

vi.mock("sonner", () => ({
  toast: {
    error: mocks.toastError,
    success: mocks.toastSuccess,
  },
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

function menuItem() {
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
  };
}

describe("MenuPage", () => {
  beforeEach(() => {
    mocks.featureFlags.menuCategories = false;
    mocks.featureFlags.menuItemDetail = true;
    mocks.listMenuCategories.mockReset();
    mocks.listMenuItems.mockReset();
    mocks.previewMenuPreorder.mockReset();
    mocks.toastError.mockReset();
    mocks.toastSuccess.mockReset();
    mocks.listMenuItems.mockResolvedValue([menuItem()]);
    mocks.listMenuCategories.mockResolvedValue([]);
  });

  it("links to menu item detail when the item detail contract is enabled", async () => {
    renderPage();

    expect(await screen.findByRole("searchbox", { name: "Search menu items" })).toBeInTheDocument();

    const detailLink = await screen.findByRole("link", { name: "Details" });

    expect(detailLink).toHaveAttribute("href", "/menu/42");
  });

  it("keeps menu item detail closed when the detail feature flag is disabled", async () => {
    mocks.featureFlags.menuItemDetail = false;

    renderPage();

    expect(await screen.findByText("Pho Bo")).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Details" })).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Details" })).toBeDisabled();
  });
});
