import { formatLocalDateTimeInput } from "@/lib/contracts/datetime";
import { stringValue } from "@/lib/contracts/format";
import { asRecord, booleanValue, numberValue, recordValue } from "@/lib/contracts/loose";
import {
  depositStatusValues,
  paymentStatusValues,
  reservationBillPaymentSessionStatusValues,
  reservationBillPaymentSettlementStatusValues,
  reservationDepositPaymentSessionStatusValues,
  reservationDepositPaymentSettlementStatusValues,
  restaurantPosEnumStateMap,
  tableHoldStatusValues,
} from "@/lib/contracts/generated/restaurantpos-enums";
import type {
  CustomerBillPaymentSession,
  CustomerDepositPaymentSession,
  CustomerReservationBenefitsPreview,
  CustomerReservationPreorderPayload,
  ReservationSummary,
} from "@/lib/contracts/generated/restaurantpos-sdk";

const reservationStatusHints = restaurantPosEnumStateMap.ReservationStatus.stateHints;
const paymentSessionStatusValues = [
  ...reservationDepositPaymentSessionStatusValues,
  ...reservationBillPaymentSessionStatusValues,
];
const paymentSettlementStatusValues = [
  ...reservationDepositPaymentSettlementStatusValues,
  ...reservationBillPaymentSettlementStatusValues,
];

const ACTIVE_RESERVATION_STATUSES = new Set<string>(reservationStatusHints.active_db_values);
const SETTLED_DEPOSIT_STATUSES = new Set<string>(
  depositStatusValues.filter((status) => status !== "Pending" && status !== "NotRequired"),
);
const INACTIVE_HOLD_STATUSES = new Set<string>([
  ...tableHoldStatusValues.filter((status) => status !== "Holding" && status !== "Pending"),
  "Released",
]);
const SETTLED_BILL_PAYMENT_STATUSES = new Set<string>([
  ...paymentStatusValues.filter((status) => status === "Success" || status === "Refunded"),
  "Paid",
  "Succeeded",
]);
const PAYMENT_PENDING_SESSION_STATUSES = new Set<string>([
  ...paymentSessionStatusValues.filter((status) => status === "Pending" || status === "Created"),
  "Ready",
  "Open",
  "Processing",
  "Submitted",
]);
const PAYMENT_CONFIRMED_SESSION_STATUSES = new Set<string>(["Confirmed"]);
const PAYMENT_SUCCEEDED_SESSION_STATUSES = new Set<string>([
  ...paymentSessionStatusValues.filter((status) => status === "Succeeded"),
  ...paymentStatusValues.filter((status) => status === "Success"),
  "Paid",
  "Completed",
]);
const PAYMENT_FAILED_SESSION_STATUSES = new Set<string>(paymentSessionStatusValues.filter((status) => status === "Failed"));
const PAYMENT_CANCELLED_SESSION_STATUSES = new Set<string>(paymentSessionStatusValues.filter((status) => status === "Cancelled"));
const PAYMENT_EXPIRED_SESSION_STATUSES = new Set<string>(paymentSessionStatusValues.filter((status) => status === "Expired"));
const SETTLEMENT_APPLIED_STATUSES = new Set<string>([
  ...paymentSettlementStatusValues.filter((status) => status === "Applied"),
  "Succeeded",
  "Paid",
  "Success",
  "Completed",
]);
const SETTLEMENT_FAILED_STATUSES = new Set<string>(["Failed"]);
const SETTLEMENT_CANCELLED_STATUSES = new Set<string>(["Cancelled"]);
const SETTLEMENT_EXPIRED_STATUSES = new Set<string>(["Expired"]);
const SESSION_AUTO_REFRESH_INTERVAL_MS = 5_000;
const SESSION_AUTO_REFRESH_WINDOW_MS = 60_000;

export type ReservationStatusFlagsContract = {
  isActive: boolean | null;
  activeOnly: boolean | null;
};

export type ReservationSelfServiceContract = {
  canAttemptCancel: boolean | null;
  canAttemptReschedule: boolean | null;
};

export type DepositSelfServiceContract = {
  canAcknowledge: boolean | null;
  canSubmitIntent: boolean | null;
  canRevokeIntent: boolean | null;
  canCreatePaymentSession: boolean | null;
  supported: boolean | null;
  actionable: boolean | null;
  nextStep: string | null;
  requiresStaffPaymentCollection: boolean | null;
};

export type DepositContractState = {
  status: string;
  currency: string;
  amount: string | null;
  outstandingAmount: number | null;
  depositAcknowledged: boolean | null;
  intentStatus: string | null;
  selfService: DepositSelfServiceContract;
};

export type ActiveOrderContractState = {
  present: boolean;
  orderId: number | null;
  status: string | null;
  rowVersion: number | null;
};

export type BillContractState = {
  amount: string | null;
  currency: string;
  outstandingAmount: number | null;
  paymentStatus: string | null;
  selfPayment: {
    supported: boolean | null;
    available: boolean | null;
    disabledReason: string | null;
    nextStep: string | null;
    requiresLockedBill: boolean | null;
    awaitingStaffFinalization: boolean | null;
  };
};

export type ReservationHoldState = {
  holdId: string | null;
  status: string | null;
  expiresAt: string | null;
  tableIds: number[];
  confirmedReservationId: number | null;
  isExpired: boolean;
  isActive: boolean;
};

export type ReservationDepositSummaryState = {
  state: "pending" | "paid" | "refunded" | "not_required";
  label: string;
  title: string;
  description: string;
  amount: string | null;
  currency: string;
  outstandingAmount: number | null;
  requiresAction: boolean;
};

export type ReservationBillSummaryState = {
  state: "available" | "unavailable" | "settled";
  label: string;
  title: string;
  description: string;
  amount: string | null;
  currency: string;
  outstandingAmount: number | null;
  paymentStatus: string | null;
  available: boolean;
};

export type ReservationWorkspaceStatusState = {
  state: "confirmed" | "reserved" | "completed" | "cancelled" | "no_show" | "expired" | "unknown";
  label: string;
  title: string;
  description: string;
  manageable: boolean;
};

export type ReservationHoldSummaryState = {
  state: "active" | "expired" | "released" | "unavailable";
  label: string;
  title: string;
  description: string;
  expiresAt: string | null;
  tableCount: number;
};

export type ReservationActiveOrderSummaryState = {
  state: "present" | "absent";
  label: string;
  description: string;
  orderId: number | null;
  status: string | null;
};

export type PaymentSurface = "deposit" | "bill";

export type PaymentSessionLifecycle = "pending" | "confirmed" | "succeeded" | "failed" | "expired" | "cancelled";

export type PaymentSettlementState = "applied" | "unapplied" | "failed" | "expired" | "cancelled";

export type PaymentRefreshMode = "auto" | "manual" | "stopped";

export type PaymentProviderSupportState = {
  state: "not_enabled" | "conditional" | "seeded_uat_required" | "simulated" | "provider_backed";
  title: string;
  description: string;
};

export type PaymentSessionPolicy = {
  lifecycle: PaymentSessionLifecycle;
  settlement: PaymentSettlementState;
  refreshMode: PaymentRefreshMode;
  autoRefreshMs: number | null;
  canRefresh: boolean;
  canConfirm: boolean;
  terminal: boolean;
  failureMessage: string | null;
  statusMessage: string | null;
  title: string;
  description: string;
  settlementTitle: string;
  settlementDescription: string;
  refreshTitle: string;
  refreshDescription: string;
  providerSupport: PaymentProviderSupportState;
};

export function parseReservationStatusFlags(value: unknown): ReservationStatusFlagsContract {
  const record = asRecord(value);

  return {
    isActive: booleanValue(record, ["is_active"]),
    activeOnly: booleanValue(record, ["active_only"]),
  };
}

export function parseReservationSelfService(value: unknown): ReservationSelfServiceContract {
  const record = asRecord(value);

  return {
    canAttemptCancel: booleanValue(record, ["can_attempt_cancel"]),
    canAttemptReschedule: booleanValue(record, ["can_attempt_reschedule"]),
  };
}

function parseDepositSelfService(value: unknown): DepositSelfServiceContract {
  const record = asRecord(value);

  return {
    canAcknowledge: booleanValue(record, ["can_acknowledge", "can_acknowledge_requirement"]),
    canSubmitIntent: booleanValue(record, ["can_submit_intent", "can_submit_payment_intent"]),
    canRevokeIntent: booleanValue(record, ["can_revoke_intent"]),
    canCreatePaymentSession: booleanValue(record, ["can_create_payment_session", "can_pay"]),
    supported: booleanValue(record, ["supported"]),
    actionable: booleanValue(record, ["actionable"]),
    nextStep: stringValue(record, ["next_step"]),
    requiresStaffPaymentCollection: booleanValue(record, ["requires_staff_payment_collection"]),
  };
}

export function parseDepositContract(value: unknown, reservation: ReservationSummary): DepositContractState {
  const record = asRecord(value);
  const selfService = parseDepositSelfService(recordValue(record, ["self_service", "deposit_self_service"]));
  const status = stringValue(record, ["status", "deposit_status"]) ?? reservation.deposit_status ?? "Pending";
  const currency = stringValue(record, ["currency"]) ?? reservation.bill_currency ?? "USD";
  const amount =
    stringValue(record, ["outstanding_amount", "amount_due", "required_amount", "amount"]) ??
    reservation.deposit_required_amount ??
    null;
  const outstandingAmount =
    numberValue(record, ["outstanding_amount", "amount_due", "required_amount", "amount"]) ??
    (() => {
      const required = Number(reservation.deposit_required_amount ?? "");
      const paid = Number(reservation.deposit_paid_amount ?? "");

      if (!Number.isFinite(required) || !Number.isFinite(paid)) {
        return null;
      }

      return Math.max(0, required - paid);
    })();

  return {
    status,
    currency,
    amount,
    outstandingAmount,
    depositAcknowledged:
      booleanValue(record, ["deposit_acknowledged"]) ??
      booleanValue(recordValue(record, ["self_service", "deposit_self_service"]), ["acknowledged", "deposit_acknowledged"]),
    intentStatus: stringValue(record, ["deposit_intent_status", "intent_status"]),
    selfService,
  };
}

export function parseActiveOrderContract(value: unknown): ActiveOrderContractState {
  const record = asRecord(value);

  return {
    present: Boolean(record),
    orderId: numberValue(record, ["order_id", "active_order_id"]),
    status: stringValue(record, ["status", "order_status"]),
    rowVersion: numberValue(record, ["row_version"]),
  };
}

export function parseBillContract(value: unknown, reservation: ReservationSummary): BillContractState | null {
  const record = asRecord(value);

  if (!record) {
    return null;
  }

  const totals = recordValue(record, ["totals", "financial_summary"]);
  const selfPayment = recordValue(record, ["self_payment", "payment_session_support"]);

  return {
    amount:
      stringValue(record, ["outstanding", "outstanding_amount", "amount_due", "total_due", "total"]) ??
      stringValue(totals, ["outstanding", "amount_due", "total_due", "total"]) ??
      reservation.final_bill_amount ??
      null,
    currency:
      stringValue(record, ["currency"]) ??
      stringValue(totals, ["currency"]) ??
      reservation.bill_currency ??
      "USD",
    outstandingAmount:
      numberValue(record, ["outstanding", "outstanding_amount", "amount_due", "total_due", "total"]) ??
      numberValue(totals, ["outstanding", "amount_due", "total_due", "total"]),
    paymentStatus:
      stringValue(record, ["payment_status", "status"]) ??
      stringValue(totals, ["payment_status", "status"]),
    selfPayment: {
      supported: booleanValue(selfPayment, ["supported"]),
      available: booleanValue(selfPayment, ["available"]),
      disabledReason: stringValue(selfPayment, ["disabled_reason"]),
      nextStep: stringValue(selfPayment, ["next_step"]),
      requiresLockedBill: booleanValue(selfPayment, ["requires_locked_bill"]),
      awaitingStaffFinalization: booleanValue(selfPayment, ["awaiting_staff_finalization"]),
    },
  };
}

export function parseReservationHoldState(value: unknown): ReservationHoldState {
  const record = asRecord(value);
  const status = stringValue(record, ["status", "hold_status"]);
  const expiresAt = stringValue(record, ["expires_at", "expire_at"]);
  const tableIds = parseReservationHoldTableIds(record);
  const timestamp = expiresAt ? Date.parse(expiresAt) : Number.NaN;
  const expiredByTime = Number.isFinite(timestamp) && timestamp <= Date.now();
  const isExpired = status === "Expired" || expiredByTime;

  return {
    holdId: stringValue(record, ["hold_id"]),
    status,
    expiresAt: expiresAt ?? null,
    tableIds,
    confirmedReservationId: numberValue(record, ["confirmed_reservation_id"]),
    isExpired,
    isActive: Boolean(status && !INACTIVE_HOLD_STATUSES.has(status) && !isExpired),
  };
}

export function isReservationActive(reservation: ReservationSummary): boolean {
  return ACTIVE_RESERVATION_STATUSES.has(reservation.status);
}

function actionUnavailableReason(reservation: ReservationSummary, action: "cancel" | "reschedule"): string | null {
  if (!isReservationActive(reservation)) {
    return "Chỉ có thể đổi lịch khi lịch đặt còn hiệu lực.";
  }

  const statusFlags = parseReservationStatusFlags(reservation.status_flags);
  const selfService = parseReservationSelfService(reservation.customer_self_service);
  const canAttempt = action === "cancel" ? selfService.canAttemptCancel : selfService.canAttemptReschedule;

  if (statusFlags.isActive === false || statusFlags.activeOnly === false) {
    return "Lịch đặt này không còn hiệu lực.";
  }

  if (canAttempt === false) {
    return action === "cancel"
      ? "Bạn không thể hủy lịch đặt này trực tuyến nữa."
      : "Bạn không thể đổi giờ lịch đặt này trực tuyến nữa.";
  }

  return null;
}

export function getReservationActionPolicy(reservation: ReservationSummary) {
  const cancelReason = actionUnavailableReason(reservation, "cancel");
  const rescheduleReason = actionUnavailableReason(reservation, "reschedule");
  const manageMessage =
    cancelReason && rescheduleReason
      ? rescheduleReason === cancelReason
        ? cancelReason
        : `${rescheduleReason} ${cancelReason}`
      : cancelReason ?? rescheduleReason;
  const canManage = cancelReason === null || rescheduleReason === null;

  return {
    canCancel: cancelReason === null,
    canReschedule: rescheduleReason === null,
    cancelReason,
    rescheduleReason,
    manageMessage,
    manageState: canManage ? ("available" as const) : ("no_longer_manageable" as const),
    manageTitle: canManage ? "Quản lý lịch đặt trực tuyến" : "Không còn thao tác trực tuyến",
    manageDescription:
      manageMessage ?? "Bạn có thể hủy hoặc yêu cầu đổi giờ khi lịch đặt còn trong thời gian cho phép.",
  };
}

export function getReservationDurationMinutes(reservation: ReservationSummary, fallbackMinutes = 90): number {
  if (!reservation.start_time || !reservation.end_time) {
    return fallbackMinutes;
  }

  const start = new Date(reservation.start_time);
  const end = new Date(reservation.end_time);
  const diff = end.getTime() - start.getTime();

  if (!Number.isFinite(diff) || diff <= 0) {
    return fallbackMinutes;
  }

  return Math.max(30, Math.round(diff / 60_000));
}

export function reservationStartInputValue(reservation: ReservationSummary): string {
  if (!reservation.start_time) {
    return "";
  }

  const start = new Date(reservation.start_time);

  if (Number.isNaN(start.getTime())) {
    return "";
  }

  return formatLocalDateTimeInput(start);
}

export function getDepositPolicy(reservation: ReservationSummary, deposit: DepositContractState) {
  const { amount, currency, depositAcknowledged, intentStatus, outstandingAmount, status } = deposit;
  const needsDeposit = status !== "NotRequired";
  const hasOutstandingBalance = outstandingAmount === null ? Boolean(amount && amount !== "0" && amount !== "0.00") : outstandingAmount > 0;
  const isSettled = SETTLED_DEPOSIT_STATUSES.has(status);
  const canAcknowledgeExplicit = deposit.selfService.canAcknowledge;
  const canSubmitIntentExplicit = deposit.selfService.canSubmitIntent;
  const canRevokeIntentExplicit = deposit.selfService.canRevokeIntent;
  const canCreatePaymentSessionExplicit = deposit.selfService.canCreatePaymentSession;
  const otherExplicitStepsAvailable =
    canAcknowledgeExplicit === true || canSubmitIntentExplicit === true || canRevokeIntentExplicit === true;
  const submittedSelfPayIntent =
    intentStatus === "Submitted" || deposit.selfService.nextStep === "awaiting_staff_payment_collection";
  const canAcknowledge =
    canAcknowledgeExplicit ??
    (isReservationActive(reservation) && needsDeposit && hasOutstandingBalance && !isSettled && depositAcknowledged !== true);
  const canSubmitIntent =
    canSubmitIntentExplicit ??
    (isReservationActive(reservation) && needsDeposit && hasOutstandingBalance && !isSettled && intentStatus !== "Submitted");
  const canRevokeIntent = canRevokeIntentExplicit ?? (isReservationActive(reservation) && intentStatus === "Submitted");
  const canCreatePaymentSession =
    canCreatePaymentSessionExplicit ??
    (isReservationActive(reservation) &&
      needsDeposit &&
      hasOutstandingBalance &&
      !isSettled &&
      deposit.selfService.supported !== false &&
      deposit.selfService.actionable !== false &&
      (submittedSelfPayIntent || (deposit.selfService.requiresStaffPaymentCollection !== true && !otherExplicitStepsAvailable)));

  let noActionMessage: string | null = null;

  if (!needsDeposit) {
    noActionMessage = "Lịch đặt này không cần đặt cọc.";
  } else if (isSettled || !hasOutstandingBalance) {
    noActionMessage = "Khoản đặt cọc đã được xử lý.";
  } else if (!isReservationActive(reservation)) {
    noActionMessage = "Không thể tự xử lý đặt cọc cho lịch đặt này nữa.";
  } else if (deposit.selfService.supported === false) {
    noActionMessage = "Nhà hàng hiện chưa bật thanh toán trực tuyến. Quý khách vui lòng thanh toán tại quầy.";
  } else if (deposit.selfService.requiresStaffPaymentCollection === true && !canCreatePaymentSession) {
    noActionMessage = "Đặt cọc trực tuyến chưa sẵn sàng. Vui lòng làm theo hướng dẫn hiện tại hoặc hỏi nhà hàng.";
  }

  return {
    amount,
    currency,
    status,
    outstandingAmount,
    depositAcknowledged,
    intentStatus,
    canAcknowledge,
    canSubmitIntent,
    canRevokeIntent,
    canCreatePaymentSession,
    noActionMessage,
  };
}

export function getBillingPolicy({
  reservation,
  bill,
  activeOrder,
}: {
  reservation: ReservationSummary;
  bill: BillContractState | null;
  activeOrder: ActiveOrderContractState;
}) {
  const amount = bill?.amount ?? null;
  const currency = bill?.currency ?? reservation.bill_currency ?? "USD";
  const outstandingAmount = bill?.outstandingAmount ?? null;
  const paymentStatus = bill?.paymentStatus ?? stringValue(asRecord(reservation.payment_summary), ["payment_status"]);
  const hasBill = Boolean(bill);
  const hasActiveOrder = activeOrder.present;
  const selfPaymentAvailable = bill?.selfPayment.available;
  const selfPaymentSupported = bill?.selfPayment.supported;
  const billSummary = getBillSummaryState({ reservation, bill });
  const activeOrderSummary = getActiveOrderSummaryState(activeOrder);
  const canCreatePaymentSession =
    hasBill &&
    selfPaymentSupported !== false &&
    selfPaymentAvailable !== false &&
    (outstandingAmount === null ? !SETTLED_BILL_PAYMENT_STATUSES.has(paymentStatus ?? "") : outstandingAmount > 0);

  let noActionMessage: string | null = null;

  if (!hasBill && !hasActiveOrder) {
    noActionMessage = "Hóa đơn cuối chưa sẵn sàng.";
  } else if (!hasBill && hasActiveOrder) {
    noActionMessage = "Đơn tại bàn vẫn đang mở. Hóa đơn cuối sẽ xuất hiện sau khi nhân viên chốt đơn.";
  } else if (outstandingAmount !== null && outstandingAmount <= 0) {
    noActionMessage = "Hiện không còn khoản cần thanh toán.";
  } else if (paymentStatus === "Paid" || paymentStatus === "Succeeded") {
    noActionMessage = "Hóa đơn này đã được thanh toán.";
  } else if (selfPaymentSupported === false) {
    noActionMessage = bill?.selfPayment.disabledReason ?? "Nhà hàng hiện chưa bật thanh toán trực tuyến. Quý khách vui lòng thanh toán tại quầy.";
  } else if (selfPaymentAvailable === false) {
    noActionMessage = billSelfPaymentUnavailableMessage(bill);
  }

  return {
    amount,
    currency,
    paymentStatus,
    hasActiveOrder,
    hasBill,
    billSummary,
    activeOrderSummary,
    canCreatePaymentSession,
    noActionMessage,
  };
}

export function getReservationHoldState(reservation: ReservationSummary): ReservationHoldState {
  const record = asRecord(reservation as unknown);

  return parseReservationHoldState(
    recordValue(record, ["hold"]) ??
      recordValue(record, ["table_hold"]) ??
      recordValue(record, ["active_hold"]) ??
      nestedRecordValue(record, ["hold_summary", "current"]) ??
      nestedRecordValue(record, ["hold_summary", "latest"]) ??
      nestedRecordValue(record, ["hold_contract", "current"]) ??
      nestedRecordValue(record, ["hold_contract", "latest"]),
  );
}

export function getReservationWorkspaceStatus(reservation: ReservationSummary): ReservationWorkspaceStatusState {
  switch (reservation.status) {
    case "Confirmed":
      return {
        state: "confirmed",
        label: "Đã xác nhận",
        title: "Lịch đặt đã xác nhận",
        description: "Lịch đặt đang hiệu lực và vẫn có thể quản lý trong thời gian nhà hàng cho phép.",
        manageable: true,
      };
    case "Reserved":
      return {
        state: "reserved",
        label: "Đang phục vụ",
        title: "Lượt ghé đang diễn ra",
        description: "Bạn đang trong khung giờ ghé nhà hàng. Thông tin món và thanh toán sẽ cập nhật khi nhà hàng chốt.",
        manageable: true,
      };
    case "Completed":
      return {
        state: "completed",
        label: "Hoàn tất",
        title: "Lượt ghé đã hoàn tất",
        description: "Lịch đặt đã đóng. Bạn vẫn có thể xem lại các thông tin thanh toán còn hiển thị.",
        manageable: false,
      };
    case "Cancelled":
      return {
        state: "cancelled",
        label: "Đã hủy",
        title: "Lịch đặt đã hủy",
        description: "Lịch đặt này không còn hiệu lực và không thể thay đổi trực tuyến.",
        manageable: false,
      };
    case "NoShow":
      return {
        state: "no_show",
        label: "Không đến",
        title: "Lịch đặt được ghi nhận là không đến",
        description: "Nhà hàng đã ghi nhận lượt đặt này bị lỡ, nên không còn thao tác trực tuyến.",
        manageable: false,
      };
    case "Expired":
      return {
        state: "expired",
        label: "Đã hết hạn",
        title: "Lịch đặt đã hết hạn",
        description: "Khung giờ đặt đã qua, nên chỉ còn một số thông tin lịch sử.",
        manageable: false,
      };
    default:
      return {
        state: "unknown",
        label: reservation.status ?? "Chưa rõ",
        title: "Chưa rõ trạng thái lịch đặt",
        description: "Hệ thống chưa phân loại được trạng thái hiện tại của lịch đặt này.",
        manageable: isReservationActive(reservation),
      };
  }
}

export function getReservationHoldSummaryState(reservation: ReservationSummary): ReservationHoldSummaryState {
  const hold = getReservationHoldState(reservation);
  const tableCount = hold.tableIds.length;

  if (!hold.holdId) {
    return {
      state: "unavailable",
      label: "Chưa giữ bàn",
      title: "Chưa có bàn giữ",
      description: "Lịch đặt này hiện chưa có bàn giữ tạm thời.",
      expiresAt: null,
      tableCount: 0,
    };
  }

  if (hold.isExpired) {
    return {
      state: "expired",
      label: "Đã hết hạn",
      title: "Bàn giữ đã hết hạn",
      description: "Bàn giữ tạm thời đã hết hạn và không còn đảm bảo bàn trống cho lịch đặt này.",
      expiresAt: hold.expiresAt,
      tableCount,
    };
  }

  if (!hold.isActive) {
    return {
      state: "released",
      label: hold.status ?? "Đã nhả bàn",
      title: "Bàn giữ đã được nhả",
      description:
        hold.confirmedReservationId !== null
          ? "Bàn giữ tạm thời đã được chuyển thành lịch đặt xác nhận hoặc được nhân viên nhả."
          : "Bàn giữ tạm thời không còn hiệu lực cho lịch đặt này.",
      expiresAt: hold.expiresAt,
      tableCount,
    };
  }

  return {
    state: "active",
    label: hold.status ?? "Đang giữ",
    title: "Bàn đang được giữ",
    description:
      tableCount > 0
        ? `Nhà hàng đang giữ bàn cho lịch đặt này${hold.expiresAt ? " đến thời hạn hiện tại." : "."}`
        : "Bàn giữ tạm thời đang hiệu lực cho lịch đặt này.",
    expiresAt: hold.expiresAt,
    tableCount,
  };
}

export function getReservationDepositSummaryState(reservation: ReservationSummary): ReservationDepositSummaryState {
  const depositState = parseDepositContract(reservation.deposit_summary, reservation);
  return getDepositSummaryState({ reservation, deposit: depositState });
}

export function getDepositSummaryState({
  reservation,
  deposit,
}: {
  reservation: ReservationSummary;
  deposit: DepositContractState;
}): ReservationDepositSummaryState {
  const depositPolicy = getDepositPolicy(reservation, deposit);
  const summaryState = mapDepositSummaryState(deposit.status);
  const summaryDescription = depositSummaryDescription({
    status: deposit.status,
    requiresAction:
      depositPolicy.canAcknowledge ||
      depositPolicy.canSubmitIntent ||
      depositPolicy.canRevokeIntent ||
      depositPolicy.canCreatePaymentSession,
  });

  return {
    state: summaryState,
    label: depositSummaryLabel(deposit.status),
    title: depositSummaryTitle(summaryState),
    description: summaryDescription,
    amount: depositPolicy.amount,
    currency: depositPolicy.currency,
    outstandingAmount: depositPolicy.outstandingAmount,
    requiresAction:
      depositPolicy.canAcknowledge ||
      depositPolicy.canSubmitIntent ||
      depositPolicy.canRevokeIntent ||
      depositPolicy.canCreatePaymentSession,
  };
}

export function getReservationBillSummaryState(reservation: ReservationSummary): ReservationBillSummaryState {
  return getBillSummaryState({ reservation, bill: null });
}

export function getBillSummaryState({
  reservation,
  bill,
}: {
  reservation: ReservationSummary;
  bill: BillContractState | null;
}): ReservationBillSummaryState {
  const amount = bill?.amount ?? reservation.final_bill_amount ?? null;
  const currency = bill?.currency ?? reservation.bill_currency ?? "USD";
  const paymentStatus = bill?.paymentStatus ?? stringValue(asRecord(reservation.payment_summary), ["payment_status"]);
  const outstandingAmount =
    bill?.outstandingAmount ?? numberValue(asRecord(reservation.payment_summary), ["outstanding_amount", "amount_due", "total_due"]);
  const billedAt = stringValue(asRecord(reservation as unknown), ["billed_at"]);
  const available = Boolean(bill) || amount !== null || billedAt !== null;
  const state = mapBillSummaryState({ amount, paymentStatus, outstandingAmount, available });

  return {
    state,
    label: available ? (amount ? amount : "Sẵn sàng") : "Chưa có",
    title: billSummaryTitle(state),
    description: billSummaryDescription(state),
    amount,
    currency,
    outstandingAmount,
    paymentStatus,
    available,
  };
}

export function getActiveOrderSummaryState(activeOrder: ActiveOrderContractState): ReservationActiveOrderSummaryState {
  if (!activeOrder.present) {
    return {
      state: "absent",
      label: "Chưa có đơn đang mở",
      description: "Hiện chưa có đơn tại bàn đang mở cho lịch đặt này.",
      orderId: null,
      status: null,
    };
  }

  return {
    state: "present",
    label: activeOrder.status ? `Đơn ${activeOrder.status}` : "Đơn đang mở",
    description: "Đơn tại bàn vẫn đang mở, nên hóa đơn cuối có thể còn thay đổi.",
    orderId: activeOrder.orderId,
    status: activeOrder.status,
  };
}

export function getDepositPaymentSupportState({
  canCreatePaymentSession,
  deposit,
}: {
  canCreatePaymentSession: boolean;
  deposit: DepositContractState;
}): PaymentProviderSupportState {
  if (deposit.selfService.supported === false) {
    return {
      state: "not_enabled",
      title: "Chưa bật thanh toán trực tuyến",
      description: "Nhà hàng hiện chưa bật thanh toán trực tuyến. Quý khách vui lòng thanh toán tại quầy.",
    };
  }

  if (!canCreatePaymentSession) {
    return {
      state: "conditional",
      title: "Chưa thể mở thanh toán",
      description:
        deposit.selfService.requiresStaffPaymentCollection === true || deposit.selfService.nextStep === "awaiting_staff_payment_collection"
          ? "Nhà hàng cần chuyển lịch đặt sang bước thanh toán tiếp theo trước khi bạn có thể trả đặt cọc."
          : "Lịch đặt này chưa sẵn sàng để mở phiên thanh toán đặt cọc.",
    };
  }

  return {
    state: "conditional",
    title: "Có thể thanh toán",
    description: "Bạn có thể mở thanh toán đặt cọc cho lịch đặt này.",
  };
}

export function getBillPaymentSupportState({
  bill,
  canCreatePaymentSession,
  hasBill,
  hasActiveOrder,
}: {
  bill: BillContractState | null;
  canCreatePaymentSession: boolean;
  hasBill: boolean;
  hasActiveOrder: boolean;
}): PaymentProviderSupportState {
  if (bill?.selfPayment.supported === false) {
    return {
      state: "not_enabled",
      title: "Chưa bật thanh toán trực tuyến",
      description: "Nhà hàng hiện chưa bật thanh toán trực tuyến. Quý khách vui lòng thanh toán tại quầy.",
    };
  }

  if (!canCreatePaymentSession) {
    if (!hasBill && !hasActiveOrder) {
      return {
        state: "seeded_uat_required",
        title: "Đang chờ hóa đơn",
        description:
          "Nhà hàng chưa gửi hóa đơn có thể thanh toán trực tuyến cho lượt ghé này.",
      };
    }

    return {
      state: "conditional",
      title: "Chưa thể mở thanh toán",
      description:
        bill?.selfPayment.awaitingStaffFinalization === true ||
        bill?.selfPayment.requiresLockedBill === true ||
        hasActiveOrder ||
        bill?.selfPayment.nextStep === "awaiting_staff_bill_lock"
          ? "Nhân viên cần chốt hóa đơn hiện tại trước khi bạn có thể thanh toán trực tuyến."
          : "Lịch đặt này chưa sẵn sàng để mở phiên thanh toán hóa đơn.",
    };
  }

  return {
    state: "conditional",
    title: "Có thể thanh toán",
    description: "Bạn có thể mở thanh toán cho hóa đơn này.",
  };
}

export function getPaymentSessionPolicy(
  session: CustomerDepositPaymentSession | CustomerBillPaymentSession,
  options: {
    surface?: PaymentSurface;
    now?: Date;
  } = {},
): PaymentSessionPolicy {
  const surface = options.surface ?? "deposit";
  const now = options.now ?? new Date();
  const failureMessage = session.failure_message ?? (session.failure_code ? `Nhà cung cấp báo lỗi ${session.failure_code}.` : null);
  const providerSupport = getRuntimeProviderSupportState(surface, session.provider_code);
  const settlement = getSettlementState(session);
  const lifecycle = getPaymentLifecycleState(session, settlement, now);
  const terminal =
    lifecycle === "succeeded" || lifecycle === "failed" || lifecycle === "expired" || lifecycle === "cancelled";
  const refreshMode = getPaymentRefreshMode({ session, lifecycle, now });
  const autoRefreshMs = refreshMode === "auto" ? SESSION_AUTO_REFRESH_INTERVAL_MS : null;
  const canRefresh = refreshMode !== "stopped";
  const canConfirm = !terminal && settlement !== "applied";
  const title = paymentLifecycleTitle(lifecycle);
  const description = paymentLifecycleDescription({ lifecycle, failureMessage, settlement });
  const settlementTitle = paymentSettlementTitle(settlement);
  const settlementDescription = paymentSettlementDescription({ lifecycle, settlement });
  const refreshTitle = paymentRefreshTitle(refreshMode);
  const refreshDescription = paymentRefreshDescription(refreshMode);
  const statusMessage =
    failureMessage ??
    (settlement === "applied" ? "Thanh toán đã được ghi nhận vào lịch đặt." : null) ??
    (session.confirmed_at ? "Nhà cung cấp đã xác nhận thanh toán." : null);

  return {
    lifecycle,
    settlement,
    refreshMode,
    autoRefreshMs,
    canRefresh,
    canConfirm,
    terminal,
    failureMessage,
    statusMessage,
    title,
    description,
    settlementTitle,
    settlementDescription,
    refreshTitle,
    refreshDescription,
    providerSupport,
  };
}

export function getPreorderPolicy(payload: CustomerReservationPreorderPayload) {
  const hasPreorder = payload.pre_order.present;
  const canManage = payload.management_policy.can_manage === true;
  const reasons = payload.management_policy.reasons.filter(Boolean);
  const launchMessage = "Nhà hàng chưa mở chỉnh sửa món đặt trước cho lịch đặt này.";
  const managementMessage = canManage
    ? "Bạn có thể chọn món, xem trước và thay thế danh sách món đặt trước hiện tại."
    : reasons.length > 0
      ? reasons.join(". ")
      : launchMessage;

  return {
    hasPreorder,
    canManage,
    state: canManage ? ("manageable" as const) : hasPreorder ? ("read_only" as const) : ("empty" as const),
    title: canManage
      ? hasPreorder
        ? "Có thể cập nhật món đặt trước"
        : "Chọn món đặt trước"
      : hasPreorder
        ? "Tóm tắt món đặt trước"
        : "Chưa có món đặt trước",
    message: canManage
      ? hasPreorder
        ? "Bạn có thể xem trước rồi thay thế danh sách món đặt trước hiện tại."
        : "Chọn món, xem trước tổng tiền và gửi món đặt trước cho lịch đặt này."
      : hasPreorder
        ? "Thông tin món đặt trước hiện đang ở chế độ chỉ xem."
        : "Nếu nhà hàng ghi nhận món đặt trước, thông tin sẽ hiển thị tại đây.",
    launchMessage,
    managementMessage,
  };
}

export function getBenefitsPolicy(preview: CustomerReservationBenefitsPreview) {
  const loyaltyEnabled = preview.reservation.loyalty.enabled;
  const canRedeem = preview.reservation.loyalty.can_redeem;
  const canRelease = preview.reservation.loyalty.can_release;
  const hasVouchers = preview.available_vouchers.length > 0;

  let summaryMessage = "Bạn có thể xem ưu đãi cho lịch đặt này.";

  if (!loyaltyEnabled && !hasVouchers) {
    summaryMessage = "Hiện chưa có điểm thưởng hoặc voucher cho lịch đặt này.";
  } else if (!canRedeem && !canRelease && !hasVouchers) {
    summaryMessage = "Có thông tin ưu đãi, nhưng hiện chưa có thao tác cần làm.";
  }

  return {
    loyaltyEnabled,
    canRedeem,
    canRelease,
    hasVouchers,
    state: hasVouchers || loyaltyEnabled ? ("read_only" as const) : ("empty" as const),
    title: hasVouchers || loyaltyEnabled ? "Xem trước ưu đãi" : "Chưa có ưu đãi",
    summaryMessage,
    readOnlyMessage: "Thông tin điểm thưởng và voucher hiện chỉ dùng để xem lại.",
  };
}

export function getBenefitsGateState() {
  return {
    title: "Ưu đãi chưa khả dụng",
    description: "Tính năng điểm thưởng và voucher sẽ hiển thị khi nhà hàng bật cho khách hàng.",
  };
}

function getRuntimeProviderSupportState(surface: PaymentSurface, providerCode: string | null | undefined): PaymentProviderSupportState {
  if (providerCode === "simulated") {
    return {
      state: "simulated",
      title: "Thanh toán mô phỏng",
      description: "Phiên này chỉ dùng để kiểm thử, chưa phải thanh toán thật.",
    };
  }

  return {
    state: "provider_backed",
    title: "Thanh toán đã kết nối",
    description:
      surface === "deposit"
        ? "Nhà hàng đã mở phiên thanh toán đặt cọc qua nhà cung cấp."
        : "Nhà hàng đã mở phiên thanh toán hóa đơn qua nhà cung cấp.",
  };
}

function getSettlementState(session: CustomerDepositPaymentSession | CustomerBillPaymentSession): PaymentSettlementState {
  if (SETTLEMENT_APPLIED_STATUSES.has(session.settlement_status)) {
    return "applied";
  }

  if (SETTLEMENT_FAILED_STATUSES.has(session.settlement_status)) {
    return "failed";
  }

  if (SETTLEMENT_CANCELLED_STATUSES.has(session.settlement_status)) {
    return "cancelled";
  }

  if (SETTLEMENT_EXPIRED_STATUSES.has(session.settlement_status)) {
    return "expired";
  }

  return "unapplied";
}

function getPaymentLifecycleState(
  session: CustomerDepositPaymentSession | CustomerBillPaymentSession,
  settlement: PaymentSettlementState,
  now: Date,
): PaymentSessionLifecycle {
  if (settlement === "applied" || PAYMENT_SUCCEEDED_SESSION_STATUSES.has(session.session_status)) {
    return "succeeded";
  }

  if (settlement === "failed" || PAYMENT_FAILED_SESSION_STATUSES.has(session.session_status)) {
    return "failed";
  }

  if (settlement === "cancelled" || PAYMENT_CANCELLED_SESSION_STATUSES.has(session.session_status)) {
    return "cancelled";
  }

  if (
    settlement === "expired" ||
    PAYMENT_EXPIRED_SESSION_STATUSES.has(session.session_status) ||
    hasExpiredPaymentProviderWindow(session.provider_expires_at, now)
  ) {
    return "expired";
  }

  if (session.confirmed_at || PAYMENT_CONFIRMED_SESSION_STATUSES.has(session.session_status)) {
    return "confirmed";
  }

  if (PAYMENT_PENDING_SESSION_STATUSES.has(session.session_status) || session.session_status.trim() === "") {
    return "pending";
  }

  return "pending";
}

function getPaymentRefreshMode({
  session,
  lifecycle,
  now,
}: {
  session: CustomerDepositPaymentSession | CustomerBillPaymentSession;
  lifecycle: PaymentSessionLifecycle;
  now: Date;
}): PaymentRefreshMode {
  if (lifecycle === "succeeded" || lifecycle === "failed" || lifecycle === "expired" || lifecycle === "cancelled") {
    return "stopped";
  }

  const activityTimestamp = [session.confirmed_at, session.updated_at, session.created_at]
    .map((value) => (value ? Date.parse(value) : Number.NaN))
    .find((value) => Number.isFinite(value));

  if (typeof activityTimestamp === "number" && Number.isFinite(activityTimestamp)) {
    return now.getTime() - activityTimestamp <= SESSION_AUTO_REFRESH_WINDOW_MS ? "auto" : "manual";
  }

  return "manual";
}

function hasExpiredPaymentProviderWindow(expiresAt: string | null | undefined, now: Date): boolean {
  if (!expiresAt) {
    return false;
  }

  const timestamp = Date.parse(expiresAt);

  return Number.isFinite(timestamp) && timestamp <= now.getTime();
}

function paymentLifecycleTitle(lifecycle: PaymentSessionLifecycle): string {
  switch (lifecycle) {
    case "confirmed":
      return "Đã xác nhận thanh toán";
    case "succeeded":
      return "Đã ghi nhận thanh toán";
    case "failed":
      return "Thanh toán không thành công";
    case "expired":
      return "Phiên thanh toán đã hết hạn";
    case "cancelled":
      return "Thanh toán đã hủy";
    case "pending":
    default:
      return "Đang mở thanh toán";
  }
}

function paymentLifecycleDescription({
  lifecycle,
  failureMessage,
  settlement,
}: {
  lifecycle: PaymentSessionLifecycle;
  failureMessage: string | null;
  settlement: PaymentSettlementState;
}): string {
  switch (lifecycle) {
    case "confirmed":
      return settlement === "applied"
        ? "Thanh toán đã được xác nhận và ghi nhận vào lịch đặt."
        : "Nhà cung cấp đã xác nhận thanh toán. Hệ thống đang cập nhật vào lịch đặt.";
    case "succeeded":
      return "Nhà hàng đã nhận và ghi nhận khoản thanh toán này.";
    case "failed":
      return failureMessage ?? "Thanh toán chưa hoàn tất.";
    case "expired":
      return "Phiên thanh toán đã hết hạn trước khi khoản thanh toán được ghi nhận.";
    case "cancelled":
      return "Phiên thanh toán đã bị hủy trước khi khoản thanh toán được ghi nhận.";
    case "pending":
    default:
      return "Phiên thanh toán đang mở. Hoàn tất thanh toán rồi kiểm tra trạng thái mới nhất tại đây.";
  }
}

function paymentSettlementTitle(settlement: PaymentSettlementState): string {
  switch (settlement) {
    case "applied":
      return "Đã ghi nhận";
    case "failed":
      return "Không thành công";
    case "expired":
      return "Đã hết hạn";
    case "cancelled":
      return "Đã hủy";
    case "unapplied":
    default:
      return "Đang chờ ghi nhận";
  }
}

function paymentSettlementDescription({
  lifecycle,
  settlement,
}: {
  lifecycle: PaymentSessionLifecycle;
  settlement: PaymentSettlementState;
}): string {
  switch (settlement) {
    case "applied":
      return "Thanh toán đã được ghi nhận vào lịch đặt.";
    case "failed":
      return "Thanh toán chưa được ghi nhận thành công.";
    case "expired":
      return "Phiên thanh toán đã hết hạn trước khi được ghi nhận.";
    case "cancelled":
      return "Phiên thanh toán đã hủy trước khi được ghi nhận.";
    case "unapplied":
    default:
      return lifecycle === "confirmed"
    ? "Thanh toán đã xác nhận, lịch đặt đang được cập nhật."
        : "Chưa có kết quả ghi nhận cuối cùng từ nhà cung cấp.";
  }
}

function paymentRefreshTitle(refreshMode: PaymentRefreshMode): string {
  switch (refreshMode) {
    case "auto":
      return "Tự động cập nhật";
    case "manual":
      return "Cập nhật thủ công";
    case "stopped":
    default:
      return "Đã dừng cập nhật";
  }
}

function paymentRefreshDescription(refreshMode: PaymentRefreshMode): string {
  switch (refreshMode) {
    case "auto":
      return "Hệ thống sẽ tự kiểm tra trạng thái trong khoảng một phút tới. Bạn vẫn có thể bấm cập nhật ngay.";
    case "manual":
      return "Tự động cập nhật đã tạm dừng. Bấm cập nhật trạng thái khi bạn muốn kiểm tra lại.";
    case "stopped":
    default:
      return "Phiên thanh toán đã có trạng thái cuối, nên không cần cập nhật thêm.";
  }
}

function parseReservationHoldTableIds(record: Record<string, unknown> | null): number[] {
  if (!record) {
    return [];
  }

  const rawTableIds = record.table_ids;
  if (Array.isArray(rawTableIds)) {
    return rawTableIds.filter((value): value is number => Number.isInteger(value) && value > 0);
  }

  const rawTables = record.tables;
  if (!Array.isArray(rawTables)) {
    return [];
  }

  return rawTables
    .map((table) => numberValue(asRecord(table), ["table_id"]))
    .filter((value): value is number => value !== null);
}

function nestedRecordValue(record: Record<string, unknown> | null, path: string[]): Record<string, unknown> | null {
  let current = record;

  for (const segment of path) {
    current = asRecord(current?.[segment]);

    if (!current) {
      return null;
    }
  }

  return current;
}

function mapDepositSummaryState(status: string): ReservationDepositSummaryState["state"] {
  if (status === "NotRequired") {
    return "not_required";
  }

  if (status === "Refunded" || status === "PartiallyRefunded") {
    return "refunded";
  }

  if (SETTLED_DEPOSIT_STATUSES.has(status)) {
    return "paid";
  }

  return "pending";
}

function depositSummaryLabel(status: string): string {
  if (status === "NotRequired") {
    return "Không cần đặt cọc";
  }

  if (status === "Pending") return "Cần đặt cọc";
  if (status === "Paid" || status === "Succeeded" || status === "Success") return "Đã thanh toán";
  if (status === "Refunded") return "Đã hoàn tiền";
  if (status === "PartiallyRefunded") return "Hoàn một phần";

  return status;
}

function depositSummaryTitle(state: ReservationDepositSummaryState["state"]): string {
  switch (state) {
    case "not_required":
      return "Không cần đặt cọc";
    case "paid":
      return "Đặt cọc đã xử lý";
    case "refunded":
      return "Đặt cọc đã hoàn tiền";
    case "pending":
    default:
      return "Cần xử lý đặt cọc";
  }
}

function depositSummaryDescription({
  status,
  requiresAction,
}: {
  status: string;
  requiresAction: boolean;
}): string {
  if (status === "NotRequired") {
    return "Lịch đặt này không cần đặt cọc trước khi đến.";
  }

  if (status === "Refunded" || status === "PartiallyRefunded") {
    return "Khoản hoàn tiền đặt cọc đã được ghi nhận cho lịch đặt này.";
  }

  if (SETTLED_DEPOSIT_STATUSES.has(status)) {
    return "Khoản đặt cọc đã được xử lý cho lịch đặt này.";
  }

  if (requiresAction) {
    return "Lịch đặt này vẫn còn bước đặt cọc cần xử lý.";
  }

  return "Bạn có thể xem thông tin đặt cọc, nhưng hiện chưa có thao tác trực tuyến cần làm.";
}

function mapBillSummaryState({
  amount,
  paymentStatus,
  outstandingAmount,
  available,
}: {
  amount: string | null;
  paymentStatus: string | null;
  outstandingAmount: number | null;
  available: boolean;
}): ReservationBillSummaryState["state"] {
  if (
    (outstandingAmount !== null && outstandingAmount <= 0) ||
    SETTLED_BILL_PAYMENT_STATUSES.has(paymentStatus ?? "")
  ) {
    return "settled";
  }

  if (!available || amount === null) {
    return "unavailable";
  }

  return "available";
}

function billSummaryTitle(state: ReservationBillSummaryState["state"]): string {
  switch (state) {
    case "settled":
      return "Hóa đơn đã thanh toán";
    case "available":
      return "Hóa đơn đã sẵn sàng";
    case "unavailable":
    default:
      return "Chưa có hóa đơn";
  }
}

function billSummaryDescription(state: ReservationBillSummaryState["state"]): string {
  switch (state) {
    case "settled":
      return "Hóa đơn hiện tại đã thanh toán hoặc không còn khoản cần trả.";
    case "available":
      return "Bạn có thể xem thông tin hóa đơn cho lịch đặt này.";
    case "unavailable":
    default:
      return "Hóa đơn cuối chưa sẵn sàng cho lịch đặt này.";
  }
}

function billSelfPaymentUnavailableMessage(bill: BillContractState | null): string {
  if (!bill) {
    return "Hóa đơn chưa sẵn sàng để thanh toán.";
  }

  if (bill.selfPayment.disabledReason) {
    return bill.selfPayment.disabledReason;
  }

  if (bill.selfPayment.awaitingStaffFinalization || bill.selfPayment.requiresLockedBill || bill.selfPayment.nextStep === "awaiting_staff_bill_lock") {
    return "Hóa đơn chưa sẵn sàng để tự thanh toán. Vui lòng chờ nhân viên chốt hóa đơn.";
  }

  return "Hóa đơn chưa sẵn sàng để thanh toán.";
}
