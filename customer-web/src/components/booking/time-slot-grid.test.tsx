import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { formatSelectedDateLabel, TimeSlotGrid } from "./time-slot-grid";

describe("TimeSlotGrid", () => {
  it("formats the selected date deterministically for server and client render", () => {
    expect(formatSelectedDateLabel("2026-05-01")).toBe("Th 6, 01/05");
  });

  it("falls back for invalid selected dates", () => {
    expect(formatSelectedDateLabel("2026-02-30")).toBe("ngày đã chọn");
    expect(formatSelectedDateLabel("not-a-date")).toBe("ngày đã chọn");
  });

  it("renders the deterministic Vietnamese selected-date label", () => {
    render(<TimeSlotGrid selectedDate="2026-05-01" selectedValue="2026-05-01T18:00" onSelect={vi.fn()} />);

    expect(screen.getByText("Chọn nhanh khung giờ trưa hoặc tối cho Th 6, 01/05.")).toBeInTheDocument();
  });
});
