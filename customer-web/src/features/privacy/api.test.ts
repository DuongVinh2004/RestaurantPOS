import { beforeEach, describe, expect, it, vi } from "vitest";
import { createPrivacyRequest, getDataExport, listPrivacyRequests } from "./api";

const mocks = vi.hoisted(() => ({
  apiCall: vi.fn(),
  idempotentOptions: vi.fn(),
  getV1MeDataExport: vi.fn(),
  getV1MePrivacyRequests: vi.fn(),
  postV1MePrivacyRequests: vi.fn(),
}));

vi.mock("@/lib/api/sdk-client", () => ({
  apiCall: mocks.apiCall,
  idempotentOptions: mocks.idempotentOptions,
}));

describe("privacy api adapter", () => {
  beforeEach(() => {
    mocks.apiCall.mockReset();
    mocks.idempotentOptions.mockReset();
    mocks.getV1MeDataExport.mockReset();
    mocks.getV1MePrivacyRequests.mockReset();
    mocks.postV1MePrivacyRequests.mockReset();

    mocks.idempotentOptions.mockImplementation((scope: string) => ({ idempotencyKey: `idem:${scope}` }));
    mocks.apiCall.mockImplementation(async (operation: (client: unknown) => unknown) =>
      operation({
        getV1MeDataExport: mocks.getV1MeDataExport,
        getV1MePrivacyRequests: mocks.getV1MePrivacyRequests,
        postV1MePrivacyRequests: mocks.postV1MePrivacyRequests,
      }),
    );
  });

  it("reads the customer data export through the generated contract", async () => {
    mocks.getV1MeDataExport.mockResolvedValue({
      data: {
        customer: {
          user_id: 7,
        },
      },
    });

    const result = await getDataExport();

    expect(mocks.getV1MeDataExport).toHaveBeenCalledWith();
    expect(result).toEqual({
      customer: {
        user_id: 7,
      },
    });
  });

  it("lists privacy requests with the customer contract pagination cap", async () => {
    mocks.getV1MePrivacyRequests.mockResolvedValue({
      data: [
        {
          customer_privacy_request_id: 11,
          request_type: "anonymize",
          status: "requested",
        },
      ],
    });

    const result = await listPrivacyRequests();

    expect(mocks.getV1MePrivacyRequests).toHaveBeenCalledWith({ per_page: 20 });
    expect(result).toEqual([
      {
        customer_privacy_request_id: 11,
        request_type: "anonymize",
        status: "requested",
      },
    ]);
  });

  it("creates privacy requests with idempotency and the anonymization request type", async () => {
    mocks.postV1MePrivacyRequests.mockResolvedValue({
      data: {
        request: {
          customer_privacy_request_id: 12,
          request_type: "anonymize",
          status: "requested",
        },
        created: true,
      },
    });

    const result = await createPrivacyRequest("Please remove my customer profile.");

    expect(mocks.idempotentOptions).toHaveBeenCalledWith("privacy-request-create");
    expect(mocks.postV1MePrivacyRequests).toHaveBeenCalledWith(
      {
        request_type: "anonymize",
        reason: "Please remove my customer profile.",
      },
      { idempotencyKey: "idem:privacy-request-create" },
    );
    expect(result.request.customer_privacy_request_id).toBe(12);
    expect(result.created).toBe(true);
  });
});
