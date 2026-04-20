import { describe, expect, it } from "vitest";
import {
  createRoundedFutureLocalDateTimeInput,
  formatLocalDateTimeInput,
  localDateTimeRangeToUtc,
  parseLocalDateTimeInput,
  toUtcIsoFromLocalDateTimeInput,
} from "./datetime";

describe("local datetime helpers", () => {
  it("parses local datetime-local values into local Date instances", () => {
    const parsed = parseLocalDateTimeInput("2026-04-18T18:30");

    expect(parsed).not.toBeNull();
    expect(formatLocalDateTimeInput(parsed as Date)).toBe("2026-04-18T18:30");
  });

  it("rejects invalid local datetime-local values", () => {
    expect(parseLocalDateTimeInput("2026-02-30T18:30")).toBeNull();
    expect(parseLocalDateTimeInput("bad-input")).toBeNull();
  });

  it("converts local datetime-local values into UTC ISO ranges", () => {
    const expectedStart = new Date(2026, 3, 18, 18, 30, 0, 0).toISOString();
    const expectedEnd = new Date(2026, 3, 18, 20, 0, 0, 0).toISOString();

    expect(toUtcIsoFromLocalDateTimeInput("2026-04-18T18:30")).toBe(expectedStart);
    expect(localDateTimeRangeToUtc("2026-04-18T18:30", 90)).toEqual({
      start_time: expectedStart,
      end_time: expectedEnd,
    });
  });

  it("creates a valid rounded future input value for datetime-local fields", () => {
    const value = createRoundedFutureLocalDateTimeInput();

    expect(parseLocalDateTimeInput(value)).not.toBeNull();
    expect(value).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:00$/);
  });
});
