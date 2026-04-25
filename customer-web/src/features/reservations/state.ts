import { formatLocalDateTimeInput } from "@/lib/contracts/datetime";
import { stringValue } from "@/lib/contracts/format";
import { asRecord, booleanValue, numberValue, recordValue } from "@/lib/contracts/loose";
import { getSupportMatrixEntryById } from "@/lib/config/support-matrix";
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

const preorderSupport = getSupportMatrixEntryById("preorder");
const benefitsSupport = getSupportMatrixEntryById("account-benefits");

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
    return "Online changes are only available while a reservation is active.";
  }

  const statusFlags = parseReservationStatusFlags(reservation.status_flags);
  const selfService = parseReservationSelfService(reservation.customer_self_service);
  const canAttempt = action === "cancel" ? selfService.canAttemptCancel : selfService.canAttemptReschedule;

  if (statusFlags.isActive === false || statusFlags.activeOnly === false) {
    return "This reservation is no longer active.";
  }

  if (canAttempt === false) {
    return action === "cancel"
      ? "Cancellation is no longer available online for this reservation."
      : "Rescheduling is no longer available online for this reservation.";
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
    manageTitle: canManage ? "Manage reservation online" : "Online changes are no longer available",
    manageDescription:
      manageMessage ?? "Cancel or request a new time while this reservation still stays active in customer self-service.",
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
    noActionMessage = "This reservation does not require a deposit.";
  } else if (isSettled || !hasOutstandingBalance) {
    noActionMessage = "The deposit is already settled.";
  } else if (!isReservationActive(reservation)) {
    noActionMessage = "Deposit self-service is no longer available for this reservation.";
  } else if (deposit.selfService.supported === false) {
    noActionMessage = "Online deposit payment is not available for this reservation.";
  } else if (deposit.selfService.requiresStaffPaymentCollection === true && !canCreatePaymentSession) {
    noActionMessage = "Deposit payment is not ready online yet. Follow the current deposit step or check with the restaurant.";
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
    noActionMessage = "The final bill is not ready yet.";
  } else if (!hasBill && hasActiveOrder) {
    noActionMessage = "An active order is still open. The final bill will appear after staff closes it.";
  } else if (outstandingAmount !== null && outstandingAmount <= 0) {
    noActionMessage = "Nothing is due right now.";
  } else if (paymentStatus === "Paid" || paymentStatus === "Succeeded") {
    noActionMessage = "This bill is already settled.";
  } else if (selfPaymentSupported === false) {
    noActionMessage = bill?.selfPayment.disabledReason ?? "Online bill payment is not available for this reservation.";
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
        label: "Confirmed",
        title: "Reservation confirmed",
        description: "Your booking is active and can still be managed while it stays within the online self-service window.",
        manageable: true,
      };
    case "Reserved":
      return {
        state: "reserved",
        label: "Reserved",
        title: "Reservation in service",
        description: "The reservation has moved into the visit window. Payment and order details may appear as the restaurant finalizes them.",
        manageable: true,
      };
    case "Completed":
      return {
        state: "completed",
        label: "Completed",
        title: "Visit completed",
        description: "The reservation is closed. You can still review any final payment details that remain available here.",
        manageable: false,
      };
    case "Cancelled":
      return {
        state: "cancelled",
        label: "Cancelled",
        title: "Reservation cancelled",
        description: "This booking is no longer active and cannot be changed from customer self-service.",
        manageable: false,
      };
    case "NoShow":
      return {
        state: "no_show",
        label: "No-show",
        title: "Reservation marked as no-show",
        description: "The restaurant recorded this booking as missed, so online changes are no longer available.",
        manageable: false,
      };
    case "Expired":
      return {
        state: "expired",
        label: "Expired",
        title: "Reservation expired",
        description: "The reservation window ended without a live visit session, so only limited history may remain available.",
        manageable: false,
      };
    default:
      return {
        state: "unknown",
        label: reservation.status ?? "Unknown",
        title: "Reservation status unavailable",
        description: "The current reservation status could not be classified for this workspace.",
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
      label: "No active hold",
      title: "No active hold",
      description: "This reservation does not currently have a live table hold linked to it.",
      expiresAt: null,
      tableCount: 0,
    };
  }

  if (hold.isExpired) {
    return {
      state: "expired",
      label: "Expired",
      title: "Hold expired",
      description: "The temporary table hold has expired and is no longer protecting table availability for this reservation.",
      expiresAt: hold.expiresAt,
      tableCount,
    };
  }

  if (!hold.isActive) {
    return {
      state: "released",
      label: hold.status ?? "Released",
      title: "Hold released",
      description:
        hold.confirmedReservationId !== null
          ? "The temporary hold has already been converted into a confirmed reservation or released by staff."
          : "The temporary hold is no longer active for this reservation.",
      expiresAt: hold.expiresAt,
      tableCount,
    };
  }

  return {
    state: "active",
    label: hold.status ?? "Active",
    title: "Hold active",
    description:
      tableCount > 0
        ? `Tables are currently being held for this reservation${hold.expiresAt ? " until the current hold deadline." : "."}`
        : "A temporary table hold is currently active for this reservation.",
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
    label: available ? (amount ? amount : "Ready") : "Not available yet",
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
      label: "No active order",
      description: "There is no open dine-in order linked to this reservation right now.",
      orderId: null,
      status: null,
    };
  }

  return {
    state: "present",
    label: activeOrder.status ? `Order ${activeOrder.status}` : "Active order",
    description: "An active dine-in order is still open, so the final bill may continue to change.",
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
      title: "Online payment not enabled",
      description: "Deposit details are available, but online deposit payment is not enabled for this reservation yet.",
    };
  }

  if (!canCreatePaymentSession) {
    return {
      state: "conditional",
      title: "Payment session not enabled yet",
      description:
        deposit.selfService.requiresStaffPaymentCollection === true || deposit.selfService.nextStep === "awaiting_staff_payment_collection"
          ? "Deposit self-pay is supported for this surface, but the restaurant still needs to move this reservation to the next payment step before a session can open."
          : "Deposit self-pay is supported here, but this reservation is not ready to open an online payment session yet.",
    };
  }

  return {
    state: "conditional",
    title: "Payment session ready",
    description: "This reservation can open a deposit payment session now. Provider proof will appear after the session opens.",
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
      title: "Online payment not enabled",
      description: "Bill details are available, but online bill payment is not enabled for this reservation yet.",
    };
  }

  if (!canCreatePaymentSession) {
    if (!hasBill && !hasActiveOrder) {
      return {
        state: "seeded_uat_required",
        title: "Waiting for live bill data",
        description:
          "Bill self-pay stays enabled for this surface, but the current live or seeded UAT data does not expose a payable bill session yet.",
      };
    }

    return {
      state: "conditional",
      title: "Payment session not enabled yet",
      description:
        bill?.selfPayment.awaitingStaffFinalization === true ||
        bill?.selfPayment.requiresLockedBill === true ||
        hasActiveOrder ||
        bill?.selfPayment.nextStep === "awaiting_staff_bill_lock"
          ? "Bill self-pay is supported here, but staff still needs to finalize and lock the current bill before a session can open."
          : "Bill self-pay is supported here, but this reservation is not ready to open an online payment session yet.",
    };
  }

  return {
    state: "conditional",
    title: "Payment session ready",
    description: "This bill can open a payment session now. Provider proof will appear after the session opens.",
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
  const failureMessage = session.failure_message ?? (session.failure_code ? `Provider reported ${session.failure_code}.` : null);
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
    (settlement === "applied" ? "Settlement has been applied to the reservation." : null) ??
    (session.confirmed_at ? "Payment confirmation was received from the provider." : null);

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
  const reasons = payload.management_policy.reasons.filter(Boolean);
  const launchMessage =
    preorderSupport?.frontendDecision ?? "Preorder is visible here for reference, but online preorder changes are not part of the current launch.";
  const managementMessage = reasons.length > 0 ? reasons.join(". ") : launchMessage;

  return {
    hasPreorder,
    state: hasPreorder ? ("read_only" as const) : ("empty" as const),
    title: hasPreorder ? "Preorder summary" : "No preorder attached",
    message: hasPreorder
      ? "Preorder details are shown here for reference only while this workspace keeps preorder changes gated."
      : "If the restaurant attaches a preorder, it will appear here. Online preorder changes stay gated from this workspace.",
    launchMessage,
    managementMessage,
  };
}

export function getBenefitsPolicy(preview: CustomerReservationBenefitsPreview) {
  const loyaltyEnabled = preview.reservation.loyalty.enabled;
  const canRedeem = preview.reservation.loyalty.can_redeem;
  const canRelease = preview.reservation.loyalty.can_release;
  const hasVouchers = preview.available_vouchers.length > 0;

  let summaryMessage = "Benefits can be reviewed for this reservation.";

  if (!loyaltyEnabled && !hasVouchers) {
    summaryMessage = "No loyalty or voucher benefits are available for this reservation right now.";
  } else if (!canRedeem && !canRelease && !hasVouchers) {
    summaryMessage = "Benefits are visible, but there is nothing actionable for this reservation yet.";
  }

  return {
    loyaltyEnabled,
    canRedeem,
    canRelease,
    hasVouchers,
    state: hasVouchers || loyaltyEnabled ? ("read_only" as const) : ("empty" as const),
    title: hasVouchers || loyaltyEnabled ? "Benefits preview" : "No benefits available",
    summaryMessage,
    readOnlyMessage:
      benefitsSupport?.frontendDecision ??
      "Loyalty and voucher details stay read-only in this workspace until the broader account-benefits rollout is enabled.",
  };
}

export function getBenefitsGateState() {
  return {
    title: "Benefits are not available yet",
    description:
      benefitsSupport?.frontendDecision ??
      "Loyalty and voucher surfaces stay off by default until the account-benefits rollout is ready.",
  };
}

function getRuntimeProviderSupportState(surface: PaymentSurface, providerCode: string | null | undefined): PaymentProviderSupportState {
  if (providerCode === "simulated") {
    return {
      state: "simulated",
      title: "Simulated payment proof",
      description: "This payment session uses the simulated provider for local or UAT proof only. It is not evidence of a production-ready payment provider.",
    };
  }

  return {
    state: "provider_backed",
    title: "Provider runtime confirmed",
    description:
      surface === "deposit"
        ? "The backend opened a provider-backed deposit payment session for this reservation. This is real runtime support, not a simulated-only proof."
        : "The backend opened a provider-backed bill payment session for this reservation. This is real runtime support, not a simulated-only proof.",
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
      return "Payment confirmed";
    case "succeeded":
      return "Payment applied";
    case "failed":
      return "Payment failed";
    case "expired":
      return "Payment session expired";
    case "cancelled":
      return "Payment cancelled";
    case "pending":
    default:
      return "Payment session open";
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
        ? "Payment was confirmed and applied to this reservation."
        : "The provider confirmed the payment. We are waiting for the reservation settlement to apply.";
    case "succeeded":
      return "The payment was received and applied to this reservation.";
    case "failed":
      return failureMessage ?? "The provider reported that this payment did not complete.";
    case "expired":
      return "This payment session expired before the payment was fully applied.";
    case "cancelled":
      return "This payment session was cancelled before the payment was applied.";
    case "pending":
    default:
      return "The provider session is open. Complete the payment, then check for the latest status here.";
  }
}

function paymentSettlementTitle(settlement: PaymentSettlementState): string {
  switch (settlement) {
    case "applied":
      return "Final status applied";
    case "failed":
      return "Final status failed";
    case "expired":
      return "Final status expired";
    case "cancelled":
      return "Final status cancelled";
    case "unapplied":
    default:
      return "Final status pending";
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
      return "Settlement has been applied to this reservation.";
    case "failed":
      return "The payment did not reach a final applied state.";
    case "expired":
      return "The payment session expired before settlement could be applied.";
    case "cancelled":
      return "The payment session was cancelled before settlement could be applied.";
    case "unapplied":
    default:
      return lifecycle === "confirmed"
        ? "Payment was confirmed, but settlement is still being applied."
        : "The provider has not reported a final applied settlement result yet.";
  }
}

function paymentRefreshTitle(refreshMode: PaymentRefreshMode): string {
  switch (refreshMode) {
    case "auto":
      return "Refreshing automatically";
    case "manual":
      return "Refresh manually";
    case "stopped":
    default:
      return "Refresh stopped";
  }
}

function paymentRefreshDescription(refreshMode: PaymentRefreshMode): string {
  switch (refreshMode) {
    case "auto":
      return "We will keep checking this payment session for the next minute. You can still refresh now if you need an immediate check.";
    case "manual":
      return "Automatic refresh paused to avoid extra requests. Use Refresh status when you want to check again.";
    case "stopped":
    default:
      return "This payment session reached a final status, so refresh controls are stopped.";
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
    return "Not required";
  }

  return status;
}

function depositSummaryTitle(state: ReservationDepositSummaryState["state"]): string {
  switch (state) {
    case "not_required":
      return "Deposit not required";
    case "paid":
      return "Deposit settled";
    case "refunded":
      return "Deposit refunded";
    case "pending":
    default:
      return "Deposit pending";
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
    return "This reservation does not require a deposit before the visit.";
  }

  if (status === "Refunded" || status === "PartiallyRefunded") {
    return "A refund has already been recorded against this reservation deposit.";
  }

  if (SETTLED_DEPOSIT_STATUSES.has(status)) {
    return "The deposit is already settled for this reservation.";
  }

  if (requiresAction) {
    return "A deposit step is still open for this reservation.";
  }

  return "Deposit details are available, but no further online deposit action is open right now.";
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
      return "Bill settled";
    case "available":
      return "Bill available";
    case "unavailable":
    default:
      return "Bill unavailable";
  }
}

function billSummaryDescription(state: ReservationBillSummaryState["state"]): string {
  switch (state) {
    case "settled":
      return "The current bill has already been settled or nothing is due right now.";
    case "available":
      return "Current bill details are available from this reservation workspace.";
    case "unavailable":
    default:
      return "The final bill is not ready yet for this reservation.";
  }
}

function billSelfPaymentUnavailableMessage(bill: BillContractState | null): string {
  if (!bill) {
    return "The bill is not ready for payment yet.";
  }

  if (bill.selfPayment.disabledReason) {
    return bill.selfPayment.disabledReason;
  }

  if (bill.selfPayment.awaitingStaffFinalization || bill.selfPayment.requiresLockedBill || bill.selfPayment.nextStep === "awaiting_staff_bill_lock") {
    return "The bill is not ready for self-payment yet. Wait for staff to finalize it.";
  }

  return "The bill is not ready for payment yet.";
}
