import { render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { PreorderCartPanel } from "./cart-panel";
import { writeLocalPreorderCart, type LocalPreorderCart, type LocalPreorderCartItem } from "./local-cart";

vi.mock("next/link", () => ({
  default: ({ children, href, ...props }: { children: ReactNode; href: string }) => <a href={href} {...props}>{children}</a>,
}));

function cartItem(overrides: Partial<LocalPreorderCartItem> = {}): LocalPreorderCartItem {
  return {
    item_id: 101,
    name: "Phở bò",
    quantity: 1,
    note: "",
    price_amount: "65000",
    currency: "VND",
    image_url: null,
    is_available: true,
    preorder_enabled: true,
    updated_at: "2026-01-01T00:00:00.000Z",
    ...overrides,
  };
}

function seedCart(items: LocalPreorderCartItem[], overrides: Partial<LocalPreorderCart> = {}) {
  const sessionId = ensureCustomerSessionId();

  writeLocalPreorderCart({
    version: 1,
    session_id: sessionId,
    branch_id: 10,
    serve_timing: "when_arrived",
    serve_note: "",
    items,
    updated_at: "2026-01-01T00:00:00.000Z",
    ...overrides,
  });
}

describe("PreorderCartPanel", () => {
  beforeEach(() => {
    window.localStorage.clear();
    window.sessionStorage.clear();
  });

  it("renders a polished empty cart state with a menu action", async () => {
    render(<PreorderCartPanel branchId={10} branchName="Nhà hàng Quận 1" />);

    expect(await screen.findByRole("heading", { name: "Sẵn sàng thêm món" })).toBeInTheDocument();
    expect(screen.getByText("Giỏ món đặt trước đang trống")).toBeInTheDocument();
    expect(screen.getByText("Nhà hàng Quận 1")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Chọn món từ thực đơn" })).toHaveAttribute("href", "/menu");
  });

  it("summarizes selected preorder items and keeps only available items submittable", async () => {
    seedCart([
      cartItem({ quantity: 2, note: "Ít hành" }),
      cartItem({
        item_id: 202,
        name: "Bánh flan",
        quantity: 1,
        price_amount: "35000",
        is_available: false,
      }),
    ]);

    render(<PreorderCartPanel branchId={10} branchName="Nhà hàng Quận 1" compact />);

    expect(await screen.findByRole("heading", { name: "3 món đã chọn" })).toBeInTheDocument();
    expect(screen.getByText("2/3 khả dụng")).toBeInTheDocument();
    expect(screen.getByText("Phở bò")).toBeInTheDocument();
    expect(screen.getByText("Bánh flan")).toBeInTheDocument();
    expect(screen.getByText("Sửa ghi chú")).toBeInTheDocument();
    expect(screen.getByText("1 món có thể gửi")).toBeInTheDocument();
    expect(screen.getAllByText("2 phần")).toHaveLength(1);
    expect(screen.getByLabelText("Danh sách món trong giỏ đặt trước")).toHaveClass("overflow-y-auto");
    expect(screen.getByRole("link", { name: "Tiếp tục đặt bàn" })).toHaveAttribute("href", "/reservations/new");
  });
});
