import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { StickyBookingSummary } from "./sticky-booking-summary";

describe("StickyBookingSummary", () => {
  it("keeps long summary values and hold actions inside the card", () => {
    const holdCode = "974ea6b3-da54-4b96-8478-123456789abc";

    render(
      <StickyBookingSummary
        title="Tóm tắt trước khi xác nhận"
        items={[
          { label: "Ngày giờ", value: "11:00 30 thg 4, 2026" },
          { label: "Số khách", value: "2 khách" },
        ]}
        holdCode={holdCode}
        holdExpiresAt={new Date(Date.now() + 5 * 60_000).toISOString()}
        holdStatusLabel="Đang giữ bàn"
        primaryActionLabel="Xác nhận đặt bàn"
        onPrimaryAction={vi.fn()}
        onRefreshHold={vi.fn()}
      />,
    );

    const holdValue = screen.getByText(holdCode);
    expect(holdValue).toHaveClass("min-w-0", "truncate");
    expect(holdValue).toHaveAttribute("title", holdCode);
    expect(holdValue.closest("div")).toHaveClass("grid", "min-w-0");
    expect(screen.getByRole("button", { name: "Gia hạn giữ bàn" })).toHaveClass("w-full");
  });
});
