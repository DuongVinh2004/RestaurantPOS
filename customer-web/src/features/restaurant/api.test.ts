import { beforeEach, describe, expect, it, vi } from "vitest";
import { getRestaurantProfile } from "./api";

const mocks = vi.hoisted(() => ({
  apiCall: vi.fn(),
  getV1RestaurantProfile: vi.fn(),
}));

vi.mock("@/lib/api/sdk-client", () => ({
  apiCall: mocks.apiCall,
}));

describe("restaurant api adapter", () => {
  beforeEach(() => {
    mocks.apiCall.mockReset();
    mocks.getV1RestaurantProfile.mockReset();
    mocks.apiCall.mockImplementation(async (operation: (client: unknown) => unknown) =>
      operation({
        getV1RestaurantProfile: mocks.getV1RestaurantProfile,
      }),
    );
  });

  it("reads public restaurant profile through the generated contract", async () => {
    mocks.getV1RestaurantProfile.mockResolvedValue({ data: { branch_name: "RestaurantPOS" } });

    await expect(getRestaurantProfile()).resolves.toEqual({ branch_name: "RestaurantPOS" });

    expect(mocks.getV1RestaurantProfile).toHaveBeenCalledWith();
  });
});
