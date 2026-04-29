import { describe, expect, it } from "vitest";
import type {
  CustomerReservationBenefitsPreview,
  CustomerReservationPreorderPayload,
  ReservationSummary,
} from "@/lib/contracts/generated/restaurantpos-sdk";
import {
  getBenefitsPolicy,
  getBillPaymentSupportState,
  getBillingPolicy,
  getDepositPaymentSupportState,
  getPaymentSessionPolicy,
  getDepositPolicy,
  getPreorderPolicy,
  getReservationActionPolicy,
  getReservationBillSummaryState,
  getReservationDepositSummaryState,
  getReservationHoldSummaryState,
  getReservationWorkspaceStatus,
  parseActiveOrderContract,
  parseBillContract,
  parseDepositContract,
} from "./state";

function createReservation(overrides: Partial<ReservationSummary> = {}): ReservationSummary {
  return {
    reservation_id: 7,
    reservation_code: "RSV-7",
    status: "Confirmed",
    row_version: 4,
    guest_count: 4,
    deposit_status: "Pending",
    deposit_required_amount: "25.00",
    deposit_paid_amount: "0.00",
    final_bill_amount: "0.00",
    bill_currency: "USD",
    ...overrides,
  };
}

function createPreorderPayload(overrides: Partial<CustomerReservationPreorderPayload> = {}): CustomerReservationPreorderPayload {
  return {
    reservation_id: 7,
    reservation_code: "RSV-7",
    reservation_status: "Confirmed",
    reservation_row_version: 5,
    pre_order: {
      present: true,
      order_id: 88,
      order_row_version: 9,
      order_status: "Open",
      service_time: "2026-04-18T18:30:00Z",
      currency: "USD",
      lines: [],
      totals: {
        item_count: 1,
        quantity: 2,
        subtotal: "34.00",
      },
      normalized_pre_order_items: [],
    },
    management_policy: {
      can_manage: false,
      reservation_status: "Confirmed",
      cutoff_minutes: 30,
      service_start: "2026-04-18T18:30:00Z",
      manage_until: "2026-04-18T18:00:00Z",
      reasons: ["Preorder is locked for kitchen prep"],
    },
    ...overrides,
  };
}

function createBenefitsPreview(overrides: Partial<CustomerReservationBenefitsPreview> = {}): CustomerReservationBenefitsPreview {
  return {
    reservation: {
      reservation_id: 7,
      reservation_code: "RSV-7",
      user_id: 12,
      status: "Reserved",
      row_version: 4,
      bill: {
        subtotal_amount: "80.00",
        manual_discount_amount: "0.00",
        loyalty_discount_amount: "5.00",
        discount_amount: "5.00",
        payable_amount: "75.00",
        currency: "USD",
      },
      loyalty: {
        enabled: true,
        available_points: 120,
        redeemed_points: 0,
        discount_amount: 5,
        redeem_amount_per_point: "1.00",
        earn_amount_per_point: "1.00",
        min_redeem_points: 10,
        max_redeemable_points: 50,
        earn_preview_points: 7,
        earned_points_current: 0,
        can_redeem: false,
        can_release: false,
      },
      user: null,
    },
    available_vouchers: [],
    ...overrides,
  };
}

function createPaymentSession(overrides: Record<string, unknown> = {}) {
  return {
    deposit_payment_session_id: 301,
    reservation_id: 7,
    provider_code: "vnpay",
    provider_session_code: "dep-1",
    amount: "25.00",
    currency: "USD",
    session_status: "Pending",
    settlement_status: "Pending",
    row_version: 11,
    created_at: "2026-04-18T10:00:00Z",
    updated_at: "2026-04-18T10:00:15Z",
    ...overrides,
  };
}

describe("reservation state helpers", () => {
  it("allows cancel and reschedule for active reservations when self-service stays enabled", () => {
    const policy = getReservationActionPolicy(
      createReservation({
        status_flags: { is_active: true },
        customer_self_service: {
          can_attempt_cancel: true,
          can_attempt_reschedule: true,
        },
      }),
    );

    expect(policy.canCancel).toBe(true);
    expect(policy.canReschedule).toBe(true);
    expect(policy.manageState).toBe("available");
    expect(policy.manageMessage).toBeNull();
  });

  it("blocks online changes for inactive reservations", () => {
    const policy = getReservationActionPolicy(
      createReservation({
        status: "Cancelled",
        customer_self_service: {
          can_attempt_cancel: true,
          can_attempt_reschedule: true,
        },
      }),
    );

    expect(policy.canCancel).toBe(false);
    expect(policy.canReschedule).toBe(false);
    expect(policy.manageState).toBe("no_longer_manageable");
    expect(policy.manageTitle).toBe("Không còn thao tác trực tuyến");
  });

  it("maps reservation status semantics for the workspace header", () => {
    expect(getReservationWorkspaceStatus(createReservation({ status: "Reserved" })).title).toBe("Lượt ghé đang diễn ra");
    expect(getReservationWorkspaceStatus(createReservation({ status: "NoShow" })).description).toContain("không còn thao tác");
  });

  it("honors explicit deposit self-service denial instead of falling back to optimistic actions", () => {
    const reservation = createReservation();
    const deposit = parseDepositContract(
      {
        status: "Pending",
        outstanding_amount: "25.00",
        currency: "USD",
        self_service: {
          can_acknowledge: false,
          can_submit_intent: false,
          can_revoke_intent: false,
          can_create_payment_session: false,
        },
      },
      reservation,
    );
    const policy = getDepositPolicy(reservation, deposit);

    expect(policy.canAcknowledge).toBe(false);
    expect(policy.canSubmitIntent).toBe(false);
    expect(policy.canRevokeIntent).toBe(false);
    expect(policy.canCreatePaymentSession).toBe(false);
  });

  it("allows a deposit payment session after customer intent is submitted on the live self-pay path", () => {
    const reservation = createReservation();
    const deposit = parseDepositContract(
      {
        status: "Pending",
        outstanding_amount: "25.00",
        currency: "USD",
        deposit_intent_status: "Submitted",
        self_service: {
          supported: true,
          actionable: true,
          can_acknowledge: false,
          can_submit_intent: false,
          can_revoke_intent: true,
          requires_staff_payment_collection: true,
          next_step: "awaiting_staff_payment_collection",
        },
      },
      reservation,
    );
    const policy = getDepositPolicy(reservation, deposit);

    expect(policy.canRevokeIntent).toBe(true);
    expect(policy.canCreatePaymentSession).toBe(true);
    expect(policy.noActionMessage).toBeNull();
  });

  it("surfaces a not-enabled deposit payment message when the backend disables online payment for the reservation", () => {
    const reservation = createReservation();
    const deposit = parseDepositContract(
      {
        status: "Pending",
        outstanding_amount: "25.00",
        currency: "USD",
        self_service: {
          supported: false,
          can_create_payment_session: false,
        },
      },
      reservation,
    );
    const policy = getDepositPolicy(reservation, deposit);
    const support = getDepositPaymentSupportState({
      canCreatePaymentSession: policy.canCreatePaymentSession,
      deposit,
    });

    expect(support.state).toBe("not_enabled");
    expect(support.title).toBe("Chưa bật thanh toán trực tuyến");
  });

  it("suppresses bill payment actions when the live bill contract says self-payment is unavailable", () => {
    const policy = getBillingPolicy({
      reservation: createReservation(),
      bill: parseBillContract(
        {
          outstanding_amount: "54.00",
          currency: "USD",
          payment_status: "Pending",
          self_payment: {
            supported: true,
            available: false,
            next_step: "awaiting_staff_bill_lock",
            requires_locked_bill: true,
          },
        },
        createReservation(),
      ),
      activeOrder: parseActiveOrderContract(null),
    });

    expect(policy.canCreatePaymentSession).toBe(false);
    expect(policy.noActionMessage).toContain("nhân viên chốt hóa đơn");
    expect(policy.billSummary.state).toBe("available");
  });

  it("marks bill self-pay as waiting for live or seeded data when no payable bill session is exposed yet", () => {
    const support = getBillPaymentSupportState({
      bill: null,
      canCreatePaymentSession: false,
      hasBill: false,
      hasActiveOrder: false,
    });

    expect(support.state).toBe("seeded_uat_required");
    expect(support.title).toBe("Đang chờ hóa đơn");
  });

  it("uses one shared session state machine for refresh policy and runtime proof", () => {
    const autoPolicy = getPaymentSessionPolicy(createPaymentSession(), {
      now: new Date("2026-04-18T10:00:45Z"),
      surface: "bill",
    });
    const manualPolicy = getPaymentSessionPolicy(createPaymentSession(), {
      now: new Date("2026-04-18T10:02:00Z"),
      surface: "deposit",
    });
    const stoppedPolicy = getPaymentSessionPolicy(
      createPaymentSession({
        provider_code: "simulated",
        session_status: "Succeeded",
        settlement_status: "Succeeded",
        confirmed_at: "2026-04-18T10:00:20Z",
      }),
      {
        now: new Date("2026-04-18T10:02:00Z"),
        surface: "deposit",
      },
    );

    expect(autoPolicy.refreshMode).toBe("auto");
    expect(autoPolicy.providerSupport.state).toBe("provider_backed");
    expect(manualPolicy.refreshMode).toBe("manual");
    expect(stoppedPolicy.refreshMode).toBe("stopped");
    expect(stoppedPolicy.providerSupport.state).toBe("simulated");
    expect(stoppedPolicy.canConfirm).toBe(false);
  });

  it("treats generated applied settlement status as a terminal successful payment", () => {
    const policy = getPaymentSessionPolicy(
      createPaymentSession({
        session_status: "Pending",
        settlement_status: "Applied",
      }),
      {
        now: new Date("2026-04-18T10:02:00Z"),
        surface: "deposit",
      },
    );

    expect(policy.settlement).toBe("applied");
    expect(policy.lifecycle).toBe("succeeded");
    expect(policy.refreshMode).toBe("stopped");
    expect(policy.canConfirm).toBe(false);
  });

  it("normalizes hold, deposit, and bill summary states for the reservation workspace", () => {
    const reservation = createReservation({
      deposit_status: "NotRequired",
      final_bill_amount: null,
      hold_summary: {
        current: {
          hold_id: "hold-123",
          status: "Expired",
          expires_at: "2020-04-18T10:00:00Z",
          table_ids: [7, 8],
        },
      },
    });

    const holdState = getReservationHoldSummaryState(reservation);
    const depositState = getReservationDepositSummaryState(reservation);
    const billState = getReservationBillSummaryState(reservation);

    expect(holdState.state).toBe("expired");
    expect(holdState.title).toBe("Bàn giữ đã hết hạn");
    expect(depositState.state).toBe("not_required");
    expect(depositState.title).toBe("Không cần đặt cọc");
    expect(billState.state).toBe("unavailable");
    expect(billState.title).toBe("Chưa có hóa đơn");
  });

  it("maps refunded deposits and settled bills into non-actionable workspace summaries", () => {
    const refundedReservation = createReservation({
      deposit_status: "Refunded",
      payment_summary: {
        payment_status: "Paid",
        outstanding_amount: 0,
      },
      final_bill_amount: "54.00",
    });

    expect(getReservationDepositSummaryState(refundedReservation).state).toBe("refunded");
    expect(getReservationBillSummaryState(refundedReservation).state).toBe("settled");
  });

  it("keeps preorder read-only even when the backend exposes a preorder contract", () => {
    const policy = getPreorderPolicy(createPreorderPayload());

    expect(policy.state).toBe("read_only");
    expect(policy.message).toContain("chỉ dùng để xem lại");
    expect(policy.managementMessage).toContain("kitchen prep");
  });

  it("marks benefits as gated preview when loyalty data is present", () => {
    const policy = getBenefitsPolicy(createBenefitsPreview());

    expect(policy.state).toBe("read_only");
    expect(policy.title).toBe("Xem trước ưu đãi");
    expect(policy.readOnlyMessage).toContain("chỉ dùng để xem lại");
  });
});
