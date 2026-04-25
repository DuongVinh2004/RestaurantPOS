import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { PrivacyPanel } from "./privacy-panel";

const mocks = vi.hoisted(() => ({
  customerWebRollout: {
    privacyRequests: {
      enabled: false,
      disabledTitle: "Privacy tools are not in this rollout",
      disabledDescription:
        "Privacy requests stay off by default and only open during a dedicated QA, UAT, or Wave 2 rollout.",
    },
    dataExport: {
      enabled: false,
      disabledTitle: "Data export is not in this rollout",
      disabledDescription:
        "Data export stays off until the broader privacy rollout is ready. Keep it disabled unless QA or UAT specifically needs export proof.",
    },
  },
  listPrivacyRequests: vi.fn(),
  getDataExport: vi.fn(),
  createPrivacyRequest: vi.fn(),
}));

vi.mock("@/lib/config/feature-flags", () => ({
  customerWebRollout: mocks.customerWebRollout,
}));

vi.mock("./api", () => ({
  listPrivacyRequests: mocks.listPrivacyRequests,
  getDataExport: mocks.getDataExport,
  createPrivacyRequest: mocks.createPrivacyRequest,
}));

function renderPanel() {
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
      <PrivacyPanel />
    </QueryClientProvider>,
  );
}

describe("PrivacyPanel", () => {
  beforeEach(() => {
    mocks.customerWebRollout.privacyRequests.enabled = false;
    mocks.customerWebRollout.dataExport.enabled = false;
    mocks.listPrivacyRequests.mockReset();
    mocks.getDataExport.mockReset();
    mocks.createPrivacyRequest.mockReset();
  });

  it("renders a disabled rollout state when privacy tools are off", () => {
    renderPanel();

    expect(screen.getByText("Privacy and data export")).toBeInTheDocument();
    expect(screen.getByText("Privacy tools are not in this rollout")).toBeInTheDocument();
    expect(screen.getByText(/dedicated QA, UAT, or Wave 2 rollout/i)).toBeInTheDocument();
    expect(mocks.listPrivacyRequests).not.toHaveBeenCalled();
    expect(mocks.getDataExport).not.toHaveBeenCalled();
  });

  it("keeps data export disabled while allowing privacy requests when only privacy tools are enabled", async () => {
    mocks.customerWebRollout.privacyRequests.enabled = true;
    mocks.listPrivacyRequests.mockResolvedValue([]);

    renderPanel();

    await waitFor(() => {
      expect(mocks.listPrivacyRequests).toHaveBeenCalledTimes(1);
    });

    expect(await screen.findByText("Data export is not in this rollout")).toBeInTheDocument();
    expect(screen.getByText("Anonymization request lifecycle")).toBeInTheDocument();
    expect(screen.getByText(/may be irreversible/i)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Submit anonymization request" })).toBeInTheDocument();
    expect(mocks.getDataExport).not.toHaveBeenCalled();
  });

  it("loads data export only when both privacy and data export flags are enabled", async () => {
    mocks.customerWebRollout.privacyRequests.enabled = true;
    mocks.customerWebRollout.dataExport.enabled = true;
    mocks.listPrivacyRequests.mockResolvedValue([]);
    mocks.getDataExport.mockResolvedValue({
      customer: {
        user_id: 7,
      },
    });

    renderPanel();

    await waitFor(() => {
      expect(mocks.listPrivacyRequests).toHaveBeenCalledTimes(1);
      expect(mocks.getDataExport).toHaveBeenCalledTimes(1);
    });

    expect(await screen.findByText("Your latest export data is available for this rollout.")).toBeInTheDocument();
  });

  it("submits anonymization requests and refreshes the request lifecycle", async () => {
    const user = userEvent.setup();

    mocks.customerWebRollout.privacyRequests.enabled = true;
    mocks.listPrivacyRequests.mockResolvedValue([]);
    mocks.createPrivacyRequest.mockResolvedValue({
      request: {
        customer_privacy_request_id: 12,
        request_type: "anonymize",
        status: "requested",
        requested_at: "2026-04-19T10:00:00Z",
      },
      created: true,
    });

    renderPanel();

    await user.type(await screen.findByRole("textbox", { name: "Optional note" }), "Please anonymize my account.");
    await user.click(screen.getByRole("button", { name: "Submit anonymization request" }));

    await waitFor(() => {
      expect(mocks.createPrivacyRequest).toHaveBeenCalledWith("Please anonymize my account.");
      expect(mocks.listPrivacyRequests.mock.calls.length).toBeGreaterThanOrEqual(2);
    });
  });
});
