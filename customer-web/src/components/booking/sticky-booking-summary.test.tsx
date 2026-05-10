import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { StickyBookingSummary } from "./sticky-booking-summary";

describe("StickyBookingSummary", () => {
  it("keeps long summary values inside the card without manual hold actions", () => {
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
      />,
    );

    const holdValue = screen.getByText(holdCode);
    expect(holdValue).toHaveClass("min-w-0", "truncate");
    expect(holdValue).toHaveAttribute("title", holdCode);
    expect(holdValue.closest("div")).toHaveClass("min-w-0");
    const summary = screen.getByRole("complementary");
    expect(summary.className).not.toContain("bottom-3");
    expect(summary.className).toContain("xl:sticky");
    expect(screen.queryByRole("button", { name: "Gia hạn giữ bàn" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Hủy giữ bàn" })).not.toBeInTheDocument();
  });
});
