"use client";

import Link from "next/link";
import { zodResolver } from "@hookform/resolvers/zod";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm, useWatch } from "react-hook-form";
import { CheckCircle2, Clock3, RefreshCw, Search, ShieldCheck } from "lucide-react";
import { toast } from "sonner";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { BookingProgress } from "@/components/booking/booking-progress";
import { StickyBookingSummary } from "@/components/booking/sticky-booking-summary";
import { EmptyState } from "@/components/states/state-blocks";
import { SelectedBranchEntry } from "@/features/branch/branch-selector";
import { useBranchSelection } from "@/features/branch/hooks";
import {
  clearCustomerBookingDraft,
  clearCustomerBookingDraftHold,
  readCustomerBookingDraft,
  storeCustomerBookingDraft,
} from "@/features/booking/booking-draft-storage";
import {
  clearLocalPreorderCart,
  localCartSubmitItems,
  readLocalPreorderCart,
} from "@/features/preorder/local-cart";
import {
  storePendingReservationPreorderDraft,
} from "@/features/preorder/reservation-draft-storage";
import type { PreorderCartItem } from "@/features/preorder/cart";
import {
  createTableHold,
  getTableHold,
  refreshTableHold,
  searchAvailableTables,
  type AvailableTablesResult,
} from "@/features/table-booking/api";
import {
  clearStoredActiveTableHoldSnapshot,
  createActiveTableHoldSnapshot,
  parseTableHoldState,
  storeActiveTableHoldSnapshot,
} from "@/features/table-booking/state";
import { createReservation } from "@/features/reservations/api";
import {
  customerFriendlyHoldMessage,
  isExpiredHoldApiError,
  isHoldConflictApiError,
  isHoldScopeMismatchApiError,
  isHoldSessionMismatchApiError,
} from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import {
  createRoundedFutureLocalDateTimeInput,
  formatLocalDateTimeInput,
  parseLocalDateTimeInput,
} from "@/lib/contracts/datetime";
import { formatDateTime } from "@/lib/contracts/format";
import type { TableHold } from "@/lib/contracts/generated/restaurantpos-sdk";
import { formatCustomerTableName } from "@/lib/i18n/customer-display";
import { useAuth } from "@/providers/auth-provider";
import { reservationFormSchemaForCustomer, type ReservationFormValues } from "./schemas";

type HoldUxState =
  | "idle"
  | "creating"
  | "active"
  | "expiringSoon"
  | "refreshing"
  | "extended"
  | "recovering"
  | "recovered"
  | "expired"
  | "conflicted"
  | "invalidSession"
  | "failed";

const holdRefreshLeadMs = 120_000;
const expiringSoonMs = 90_000;

function parseTableIds(value: string | null): number[] {
  return [...new Set((value ?? "")
    .split(",")
    .map((item) => Number(item.trim()))
    .filter((item) => Number.isInteger(item) && item > 0))]
    .sort((left, right) => left - right);
}

function parsePositiveInteger(value: string | null): number | null {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function parseHoldStartTime(value: string | null): string | null {
  return value && parseLocalDateTimeInput(value) ? value : null;
}

function parseHoldExpiresAt(value: string | null): string | null {
  if (!value) {
    return null;
  }

  const parsed = Date.parse(value);

  return Number.isFinite(parsed) ? new Date(parsed).toISOString() : null;
}

function tableIdKey(tableIds: number[]): string {
  return [...new Set(tableIds)].sort((left, right) => left - right).join(",");
}

function getTableIdsFromHold(
  hold: { tables?: Array<{ table_id: number }> | null } | null,
  fallback: number[],
): number[] {
  if (!hold || !Array.isArray(hold.tables)) {
    return fallback;
  }

  const tableIds = hold.tables
    .map((table) => table.table_id)
    .filter((tableId): tableId is number => Number.isInteger(tableId) && tableId > 0);

  return tableIds.length > 0 ? [...new Set(tableIds)].sort((left, right) => left - right) : fallback;
}

function formatHeldTables(
  hold: { tables?: Array<{ table_id: number; table_code?: string | null; zone?: string | null }> | null } | null,
  fallback: number[],
): string {
  if (hold?.tables?.length) {
    return hold.tables
      .map((table) => formatCustomerTableName(table.table_code ?? null, table.zone ?? null, table.table_id))
      .join(", ");
  }

  return fallback.length > 0
    ? fallback.map((tableId) => formatCustomerTableName(null, null, tableId)).join(", ")
    : "Chưa chọn bàn";
}

function formatVisitStartForCustomer(value: string | null | undefined): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value ?? "");

  if (!match) {
    return "Chưa chọn";
  }

  const [, year, month, day, hour, minute] = match;

  return `${hour}:${minute}, ${day}/${month}/${year}`;
}

function formatRemaining(ms: number | null): string | null {
  if (ms === null) {
    return null;
  }

  const totalSeconds = Math.max(0, Math.ceil(ms / 1000));
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;

  return `${minutes}:${String(seconds).padStart(2, "0")}`;
}

function useRemainingTime(expiresAt?: string | null): number | null {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!expiresAt) {
      return;
    }

    const timer = window.setInterval(() => setNow(Date.now()), 1000);

    return () => window.clearInterval(timer);
  }, [expiresAt]);

  if (!expiresAt) {
    return null;
  }

  const expiresAtMs = Date.parse(expiresAt);

  return Number.isFinite(expiresAtMs) ? expiresAtMs - now : null;
}

function availabilityIncludesTables(result: AvailableTablesResult, tableIds: number[]): boolean {
  if (tableIds.length === 0) {
    return false;
  }

  const tableSet = new Set(
    result.tables
      .map((table) => table.table_id)
      .filter((tableId) => Number.isInteger(tableId) && tableId > 0),
  );

  if (tableIds.every((tableId) => tableSet.has(tableId))) {
    return true;
  }

  const meta = result.meta as { suggestions?: Array<{ table_ids?: number[] }> } | undefined;

  return Boolean(meta?.suggestions?.some((suggestion) => tableIdKey(suggestion.table_ids ?? []) === tableIdKey(tableIds)));
}

function holdStateFromError(error: unknown): HoldUxState {
  if (isHoldConflictApiError(error)) {
    return "conflicted";
  }

  if (isHoldSessionMismatchApiError(error)) {
    return "invalidSession";
  }

  if (isHoldScopeMismatchApiError(error)) {
    return "invalidSession";
  }

  if (isExpiredHoldApiError(error)) {
    return "expired";
  }

  return "failed";
}

function isRecoverableHoldError(error: unknown): boolean {
  return (
    isExpiredHoldApiError(error) ||
    isHoldConflictApiError(error) ||
    isHoldSessionMismatchApiError(error) ||
    isHoldScopeMismatchApiError(error)
  );
}

function holdStatusCopy(state: HoldUxState, remainingLabel: string | null): { title: string; description: string; tone: "default" | "warning" | "destructive" } {
  switch (state) {
    case "creating":
      return {
        title: "Mình đang giữ bàn cho bạn.",
        description: "Mộc Sen đang xác nhận lại bàn đã chọn.",
        tone: "default",
      };
    case "active":
      return {
        title: "Bàn đang được giữ cho bạn.",
        description: remainingLabel ? `Còn ${remainingLabel} để hoàn tất đặt bàn.` : "Hoàn tất thông tin liên hệ để xác nhận đặt bàn.",
        tone: "default",
      };
    case "expiringSoon":
      return {
        title: "Sắp hết thời gian giữ bàn.",
        description: remainingLabel ? `Còn ${remainingLabel}. Mộc Sen sẽ thử giữ thêm thời gian cho bạn.` : "Mộc Sen sẽ thử giữ thêm thời gian cho bạn.",
        tone: "warning",
      };
    case "refreshing":
      return {
        title: "Mình đang giữ bàn thêm thời gian cho bạn...",
        description: "Thông tin liên hệ của bạn vẫn được giữ nguyên.",
        tone: "default",
      };
    case "extended":
      return {
        title: "Đã giữ bàn thêm thời gian cho bạn.",
        description: remainingLabel ? `Còn ${remainingLabel} để hoàn tất đặt bàn.` : "Bạn có thể tiếp tục xác nhận đặt bàn.",
        tone: "default",
      };
    case "recovering":
      return {
        title: "Mình đang thử giữ lại bàn này cho bạn.",
        description: "Mộc Sen đã giữ thông tin của bạn. Bạn không cần nhập lại.",
        tone: "warning",
      };
    case "recovered":
      return {
        title: "Đã giữ lại bàn cho bạn.",
        description: remainingLabel ? `Còn ${remainingLabel} để hoàn tất đặt bàn.` : "Bạn có thể tiếp tục xác nhận đặt bàn.",
        tone: "default",
      };
    case "expired":
      return {
        title: "Bàn vừa hết thời gian giữ, nhưng thông tin của bạn vẫn được giữ nguyên.",
        description: "Bạn có thể chọn lại bàn mà không cần nhập lại thông tin.",
        tone: "warning",
      };
    case "conflicted":
      return {
        title: "Bàn này vừa có khách khác chọn.",
        description: "Mộc Sen đã giữ thông tin của bạn. Bạn chỉ cần chọn lại bàn phù hợp.",
        tone: "warning",
      };
    case "invalidSession":
      return {
        title: "Phiên giữ bàn không còn hợp lệ.",
        description: "Vui lòng chọn lại bàn để tiếp tục. Thông tin liên hệ của bạn vẫn được giữ.",
        tone: "warning",
      };
    case "failed":
      return {
        title: "Mộc Sen chưa thể kiểm tra trạng thái bàn lúc này.",
        description: "Vui lòng thử lại sau ít phút hoặc chọn lại bàn phù hợp.",
        tone: "destructive",
      };
    case "idle":
    default:
      return {
        title: "Chưa có bàn đang giữ.",
        description: "Vui lòng chọn bàn trước khi xác nhận đặt bàn.",
        tone: "warning",
      };
  }
}

function HoldDetailItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border bg-background/80 px-3 py-2">
      <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="mt-1 break-words text-sm font-semibold text-foreground">{value}</dd>
    </div>
  );
}

function HoldStatusPanel({
  state,
  expiresAt,
  remainingLabel,
  tableLabel,
  startLabel,
  guestCount,
  durationMinutes,
  onRetry,
  retryDisabled,
}: {
  state: HoldUxState;
  expiresAt: string | null;
  remainingLabel: string | null;
  tableLabel: string;
  startLabel: string;
  guestCount: number;
  durationMinutes: number;
  onRetry: () => void;
  retryDisabled: boolean;
}) {
  const copy = holdStatusCopy(state, remainingLabel);
  const variant = copy.tone === "destructive" ? "destructive" : "default";
  const showRetry = state === "expired" || state === "conflicted" || state === "invalidSession" || state === "failed";

  return (
    <Alert
      role="region"
      aria-label="Trạng thái giữ bàn"
      variant={variant}
      className={copy.tone === "warning" ? "rounded-lg border-amber-200 bg-amber-50 text-amber-950" : "rounded-lg"}
    >
      {state === "refreshing" || state === "recovering" || state === "creating" ? (
        <RefreshCw className="h-4 w-4 animate-spin" />
      ) : state === "active" || state === "extended" || state === "recovered" ? (
        <ShieldCheck className="h-4 w-4" />
      ) : (
        <Clock3 className="h-4 w-4" />
      )}
      <AlertTitle>{copy.title}</AlertTitle>
      <AlertDescription className="mt-3 space-y-3">
        <p>{copy.description}</p>
        <dl className="grid gap-2 sm:grid-cols-2">
          <HoldDetailItem label="Bàn đã chọn" value={tableLabel} />
          <HoldDetailItem label="Thời gian đến" value={startLabel} />
          <HoldDetailItem label="Số khách" value={`${guestCount} khách`} />
          <HoldDetailItem label="Thời lượng dùng bữa dự kiến" value={`${durationMinutes} phút`} />
        </dl>
        {expiresAt ? (
          <p className="text-sm text-muted-foreground">Hạn giữ bàn theo hệ thống: {formatDateTime(expiresAt)}</p>
        ) : null}
        {showRetry ? (
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" className="rounded-lg bg-background" onClick={onRetry} disabled={retryDisabled}>
              <RefreshCw className="mr-2 h-4 w-4" />
              Thử giữ lại bàn
            </Button>
            <Button asChild type="button" variant="outline" className="rounded-lg bg-background">
              <Link href="/booking">
                <Search className="mr-2 h-4 w-4" />
                Chọn bàn khác
              </Link>
            </Button>
          </div>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}

export function ReservationCreatePage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const { isAuthenticated, profile } = useAuth();
  const branchSelection = useBranchSelection();
  const [customerSessionId] = useState(() => ensureCustomerSessionId());
  const [initialDraft] = useState(() => readCustomerBookingDraft(customerSessionId));
  const initialHoldId = searchParams.get("hold_id") || initialDraft?.hold_id || null;
  const initialHoldExpiresAt = parseHoldExpiresAt(searchParams.get("hold_expires_at")) ?? initialDraft?.hold_expires_at ?? null;
  const initialTableIds = parseTableIds(searchParams.get("tables"));
  const initialDraftTableIds = initialDraft?.selected_table_ids ?? [];
  const initialHoldStartTime = parseHoldStartTime(searchParams.get("start_time")) ?? initialDraft?.start_time ?? null;
  const initialDurationMinutes = parsePositiveInteger(searchParams.get("duration_minutes")) ?? initialDraft?.duration_minutes ?? 90;
  const initialGuestCount = parsePositiveInteger(searchParams.get("guest_count")) ?? initialDraft?.guest_count ?? 2;
  const branchIdFromParams = parsePositiveInteger(searchParams.get("branch_id"));
  const selectedBranchId = branchIdFromParams ?? initialDraft?.branch_id ?? branchSelection.selectedBranch?.branchId ?? null;
  const [currentHoldId, setCurrentHoldId] = useState<string | null>(initialHoldId);
  const [recoveredHold, setRecoveredHold] = useState<TableHold | null>(null);
  const [fallbackTableIds, setFallbackTableIds] = useState<number[]>(
    initialTableIds.length > 0 ? initialTableIds : initialDraftTableIds,
  );
  const [holdNoticeState, setHoldNoticeState] = useState<HoldUxState | null>(null);
  const [holdActionPending, setHoldActionPending] = useState<"refreshing" | "recovering" | null>(null);
  const [holdActionError, setHoldActionError] = useState<unknown>(null);
  const recoveryAttemptedRef = useRef(false);
  const submitRecoveryAttemptedRef = useRef(false);

  const form = useForm<ReservationFormValues>({
    resolver: zodResolver(reservationFormSchemaForCustomer(isAuthenticated)),
    defaultValues: {
      guest_name: initialDraft?.guest_name || (isAuthenticated ? profile?.name ?? "" : ""),
      guest_phone: initialDraft?.guest_phone || (isAuthenticated ? profile?.phone ?? "" : ""),
      guest_email: initialDraft?.guest_email || (isAuthenticated ? profile?.email ?? "" : ""),
      branch_id: selectedBranchId ?? undefined,
      start_time: initialHoldStartTime ?? createRoundedFutureLocalDateTimeInput(),
      duration_minutes: initialDurationMinutes,
      guest_count: initialGuestCount,
      notes: initialDraft?.notes ?? "",
    },
  });
  const watchedName = useWatch({ control: form.control, name: "guest_name" });
  const watchedPhone = useWatch({ control: form.control, name: "guest_phone" });
  const watchedEmail = useWatch({ control: form.control, name: "guest_email" });
  const watchedNotes = useWatch({ control: form.control, name: "notes" });
  const watchedGuestCount = useWatch({ control: form.control, name: "guest_count" });
  const watchedDurationMinutes = useWatch({ control: form.control, name: "duration_minutes" });
  const watchedStartTime = useWatch({ control: form.control, name: "start_time" });
  const [preorderItems, setPreorderItems] = useState<PreorderCartItem[]>(() => initialDraft?.preorder_items ?? []);

  const holdQuery = useQuery({
    queryKey: currentHoldId ? queryKeys.tableBooking.hold(currentHoldId) : ["tables", "hold", "none"],
    queryFn: () => getTableHold(currentHoldId as string),
    enabled: Boolean(currentHoldId),
    retry: false,
  });
  const liveHold = recoveredHold?.hold_id === currentHoldId ? recoveredHold : holdQuery.data ?? null;
  const liveHoldState = liveHold ? parseTableHoldState(liveHold) : null;
  const liveHoldStartTime = liveHold?.start_time ? formatLocalDateTimeInput(new Date(liveHold.start_time)) : initialHoldStartTime;
  const liveHoldDurationMinutes =
    typeof liveHold?.duration_minutes === "number" && liveHold.duration_minutes > 0 ? liveHold.duration_minutes : watchedDurationMinutes;
  const liveHoldTableIds = getTableIdsFromHold(liveHold, fallbackTableIds);
  const liveHoldTableLabel = formatHeldTables(liveHold, liveHoldTableIds);
  const expiresAt = liveHoldState?.expiresAt ?? initialHoldExpiresAt;
  const remainingMs = useRemainingTime(expiresAt);
  const remainingLabel = formatRemaining(remainingMs);
  const holdExpiredByClock = remainingMs !== null && remainingMs <= 0;
  const currentHoldUxState: HoldUxState = useMemo(() => {
    if (holdActionPending === "refreshing") {
      return "refreshing";
    }

    if (holdActionPending === "recovering") {
      return "recovering";
    }

    if (holdNoticeState) {
      return holdNoticeState;
    }

    if (!currentHoldId) {
      return "idle";
    }

    if (holdQuery.isLoading) {
      return "recovering";
    }

    if (holdQuery.error) {
      return holdStateFromError(holdQuery.error);
    }

    if (!liveHoldState?.isActive || holdExpiredByClock) {
      return "expired";
    }

    if (remainingMs !== null && remainingMs <= expiringSoonMs) {
      return "expiringSoon";
    }

    return "active";
  }, [
    currentHoldId,
    holdActionPending,
    holdExpiredByClock,
    holdNoticeState,
    holdQuery.error,
    holdQuery.isLoading,
    liveHoldState?.isActive,
    remainingMs,
  ]);
  const preorderQuantity = preorderItems.reduce((total, item) => total + item.quantity, 0);
  const reservationStartTime = liveHoldStartTime ?? watchedStartTime;
  const visitStartLabel = formatVisitStartForCustomer(reservationStartTime);
  const branchLabel = branchSelection.selectedBranch?.branchName ?? (selectedBranchId ? `#${selectedBranchId}` : "Chưa chọn");
  const bookingSummaryItems = [
    { label: "Chi nhánh", value: branchLabel },
    { label: "Ngày giờ", value: visitStartLabel },
    { label: "Số khách", value: `${watchedGuestCount ?? initialGuestCount} khách` },
    { label: "Thời lượng", value: `${liveHoldDurationMinutes ?? initialDurationMinutes} phút` },
    { label: "Bàn đã chọn", value: liveHoldTableLabel },
    { label: "Món chọn trước", value: preorderQuantity > 0 ? `${preorderQuantity} món sau đặt bàn` : "Tùy chọn sau" },
  ];

  const persistBookingDraft = useCallback((values: ReservationFormValues = form.getValues()) => {
    storeCustomerBookingDraft({
      branch_id: values.branch_id ?? selectedBranchId ?? null,
      start_time: values.start_time,
      duration_minutes: values.duration_minutes,
      guest_count: values.guest_count,
      guest_name: values.guest_name,
      guest_phone: values.guest_phone,
      guest_email: values.guest_email,
      notes: values.notes ?? "",
      preorder_items: preorderItems,
      selected_table_ids: liveHoldTableIds,
      hold_id: currentHoldId,
      hold_expires_at: expiresAt,
      hold_row_version: liveHoldState?.rowVersion ?? null,
    });
  }, [currentHoldId, expiresAt, form, liveHoldState?.rowVersion, liveHoldTableIds, preorderItems, selectedBranchId]);

  const visitValuesForHold = useCallback((values: ReservationFormValues = form.getValues()) => {
    const branchId = values.branch_id ?? selectedBranchId ?? undefined;

    if (!branchId || !values.start_time || !values.duration_minutes || !values.guest_count) {
      return null;
    }

    return {
      branch_id: branchId,
      start_time: values.start_time,
      duration_minutes: values.duration_minutes,
      guest_count: values.guest_count,
    };
  }, [form, selectedBranchId]);

  const replaceUrlWithHold = useCallback((hold: TableHold, values: NonNullable<ReturnType<typeof visitValuesForHold>>, tableIds: number[]) => {
    const nextExpiresAt = hold.expire_at ?? "";
    const nextHref = `/reservations/new?hold_id=${encodeURIComponent(hold.hold_id)}&hold_status=${encodeURIComponent(hold.hold_status ?? "Holding")}&hold_expires_at=${encodeURIComponent(nextExpiresAt)}&tables=${tableIds.join(",")}&start_time=${encodeURIComponent(values.start_time)}&duration_minutes=${values.duration_minutes}&guest_count=${values.guest_count}&branch_id=${values.branch_id ?? ""}`;

    router.replace(nextHref, { scroll: false });
  }, [router]);

  const applyActiveHold = useCallback((
    hold: TableHold,
    values: NonNullable<ReturnType<typeof visitValuesForHold>>,
    tableIds: number[],
    nextState: HoldUxState,
  ) => {
    const effectiveTableIds = getTableIdsFromHold(hold, tableIds);
    const parsed = parseTableHoldState(hold);

    setRecoveredHold(hold);
    setCurrentHoldId(hold.hold_id);
    setFallbackTableIds(effectiveTableIds);
    setHoldNoticeState(nextState === "active" ? null : nextState);
    setHoldActionError(null);
    queryClient.setQueryData(queryKeys.tableBooking.hold(hold.hold_id), hold);
    storeCustomerBookingDraft({
      ...form.getValues(),
      branch_id: values.branch_id ?? null,
      start_time: values.start_time,
      duration_minutes: values.duration_minutes,
      guest_count: values.guest_count,
      preorder_items: preorderItems,
      selected_table_ids: effectiveTableIds,
      hold_id: hold.hold_id,
      hold_expires_at: hold.expire_at ?? null,
      hold_row_version: parsed.rowVersion,
    });

    const snapshot = createActiveTableHoldSnapshot(hold, {
      sessionId: customerSessionId,
      tableIds: effectiveTableIds,
      startTime: values.start_time,
      durationMinutes: values.duration_minutes,
      guestCount: values.guest_count,
      branchId: values.branch_id ?? null,
    });

    if (snapshot && parsed.isActive) {
      storeActiveTableHoldSnapshot(snapshot);
    }

    replaceUrlWithHold(hold, values, effectiveTableIds);
  }, [customerSessionId, form, preorderItems, queryClient, replaceUrlWithHold]);

  const clearHoldForReselect = useCallback((nextState: HoldUxState) => {
    setRecoveredHold(null);
    setCurrentHoldId(null);
    setFallbackTableIds([]);
    setHoldNoticeState(nextState);
    clearCustomerBookingDraftHold();
    clearStoredActiveTableHoldSnapshot(customerSessionId);
  }, [customerSessionId]);

  const recoverHold = useCallback(async (cause?: unknown) => {
    persistBookingDraft();

    if (recoveryAttemptedRef.current) {
      const nextState = cause ? holdStateFromError(cause) : "expired";
      clearHoldForReselect(nextState);
      return null;
    }

    const values = visitValuesForHold();
    const tableIds = liveHoldTableIds;

    if (!values || tableIds.length === 0) {
      clearHoldForReselect("invalidSession");
      return null;
    }

    recoveryAttemptedRef.current = true;
    setHoldActionPending("recovering");
    setHoldNoticeState("recovering");
    setHoldActionError(null);

    try {
      const availability = await searchAvailableTables(values);

      if (!availabilityIncludesTables(availability, tableIds)) {
        clearHoldForReselect("conflicted");
        return null;
      }

      const nextHold = await createTableHold(values, tableIds);

      applyActiveHold(nextHold, values, tableIds, "recovered");
      toast.success("Đã giữ lại bàn cho bạn.");

      return nextHold;
    } catch (error) {
      setHoldActionError(error);
      clearHoldForReselect(holdStateFromError(error));
      return null;
    } finally {
      setHoldActionPending(null);
    }
  }, [applyActiveHold, clearHoldForReselect, liveHoldTableIds, persistBookingDraft, visitValuesForHold]);

  const refreshOrRecoverHold = useCallback(async (override?: {
    holdState: ReturnType<typeof parseTableHoldState>;
    values: NonNullable<ReturnType<typeof visitValuesForHold>>;
    tableIds: number[];
  }) => {
    const targetHoldState = override?.holdState ?? liveHoldState;
    const values = override?.values ?? visitValuesForHold();
    const tableIds = override?.tableIds ?? liveHoldTableIds;

    if (!targetHoldState?.holdId) {
      return recoverHold();
    }

    if (!values || tableIds.length === 0) {
      clearHoldForReselect("invalidSession");
      return null;
    }

    setHoldActionPending("refreshing");
    setHoldNoticeState("refreshing");
    setHoldActionError(null);

    try {
      const refreshed = await refreshTableHold(targetHoldState.holdId, targetHoldState.rowVersion);

      applyActiveHold(refreshed, values, tableIds, "extended");

      return refreshed;
    } catch (error) {
      if (isRecoverableHoldError(error)) {
        return recoverHold(error);
      }

      setHoldActionError(error);
      setHoldNoticeState("failed");
      throw error;
    } finally {
      setHoldActionPending(null);
    }
  }, [applyActiveHold, clearHoldForReselect, liveHoldState, liveHoldTableIds, recoverHold, visitValuesForHold]);

  const revalidateHold = useCallback(async () => {
    if (!currentHoldId || holdActionPending) {
      return null;
    }

    setHoldNoticeState("recovering");

    try {
      const latest = await getTableHold(currentHoldId);
      const latestState = parseTableHoldState(latest);
      const values = visitValuesForHold();
      const tableIds = getTableIdsFromHold(latest, liveHoldTableIds);

      if (!values) {
        clearHoldForReselect("invalidSession");
        return null;
      }

      queryClient.setQueryData(queryKeys.tableBooking.hold(latest.hold_id), latest);
      setRecoveredHold(latest);

      if (!latestState.isActive) {
        return recoverHold();
      }

      const latestExpiresAtMs = latestState.expiresAt ? Date.parse(latestState.expiresAt) : Number.NaN;
      const timeLeftMs = Number.isFinite(latestExpiresAtMs) ? latestExpiresAtMs - Date.now() : Number.POSITIVE_INFINITY;

      if (timeLeftMs <= holdRefreshLeadMs) {
        return refreshOrRecoverHold({ holdState: latestState, values, tableIds });
      }

      applyActiveHold(latest, values, tableIds, "active");

      return latest;
    } catch (error) {
      setHoldActionError(error);

      if (isRecoverableHoldError(error)) {
        return recoverHold(error);
      }

      setHoldNoticeState(holdStateFromError(error));
      return null;
    }
  }, [
    applyActiveHold,
    clearHoldForReselect,
    currentHoldId,
    holdActionPending,
    liveHoldTableIds,
    queryClient,
    recoverHold,
    refreshOrRecoverHold,
    visitValuesForHold,
  ]);

  const ensureHoldReadyForSubmit = useCallback(async () => {
    if (!currentHoldId) {
      clearHoldForReselect("invalidSession");
      return null;
    }

    const latest = await getTableHold(currentHoldId);
    const latestState = parseTableHoldState(latest);
    const values = visitValuesForHold();
    const tableIds = getTableIdsFromHold(latest, liveHoldTableIds);

    if (!values || tableIds.length === 0) {
      clearHoldForReselect("invalidSession");
      return null;
    }

    queryClient.setQueryData(queryKeys.tableBooking.hold(latest.hold_id), latest);
    setRecoveredHold(latest);

    if (!latestState.isActive) {
      return recoverHold();
    }

    const latestExpiresAtMs = latestState.expiresAt ? Date.parse(latestState.expiresAt) : Number.NaN;
    const timeLeftMs = Number.isFinite(latestExpiresAtMs) ? latestExpiresAtMs - Date.now() : Number.POSITIVE_INFINITY;

    if (timeLeftMs <= holdRefreshLeadMs) {
      return refreshOrRecoverHold({ holdState: latestState, values, tableIds });
    }

    applyActiveHold(latest, values, tableIds, "active");

    return latest;
  }, [
    applyActiveHold,
    clearHoldForReselect,
    currentHoldId,
    liveHoldTableIds,
    queryClient,
    recoverHold,
    refreshOrRecoverHold,
    visitValuesForHold,
  ]);

  useEffect(() => {
    if (!selectedBranchId) {
      return;
    }

    if (form.getValues("branch_id") !== selectedBranchId) {
      form.setValue("branch_id", selectedBranchId, {
        shouldDirty: false,
        shouldValidate: true,
      });
    }
  }, [form, selectedBranchId]);

  useEffect(() => {
    if (!isAuthenticated || !profile) {
      return;
    }

    const fillContactField = (
      field: "guest_name" | "guest_phone" | "guest_email",
      value: string | null | undefined,
    ) => {
      const nextValue = value?.trim() ?? "";
      const currentValue = (form.getValues(field) ?? "").trim();
      const fieldState = form.getFieldState(field);

      if (nextValue === "" || (fieldState.isDirty && currentValue !== "")) {
        return;
      }

      form.setValue(field, nextValue, {
        shouldDirty: false,
        shouldValidate: false,
      });
    };

    fillContactField("guest_name", profile.name);
    fillContactField("guest_phone", profile.phone);
    fillContactField("guest_email", profile.email);
  }, [form, isAuthenticated, profile]);

  useEffect(() => {
    if (!selectedBranchId) {
      return;
    }

    const storedCart = readLocalPreorderCart(customerSessionId, selectedBranchId);
    const storedItems = localCartSubmitItems(storedCart);

    if (storedItems.length > 0) {
      setPreorderItems(storedItems);
    }
  }, [customerSessionId, selectedBranchId]);

  useEffect(() => {
    persistBookingDraft();
  }, [
    persistBookingDraft,
    watchedDurationMinutes,
    watchedEmail,
    watchedGuestCount,
    watchedName,
    watchedNotes,
    watchedPhone,
    watchedStartTime,
  ]);

  useEffect(() => {
    if (holdNoticeState !== "extended" && holdNoticeState !== "recovered") {
      return;
    }

    const timer = window.setTimeout(() => setHoldNoticeState(null), 5000);

    return () => window.clearTimeout(timer);
  }, [holdNoticeState]);

  useEffect(() => {
    if (!liveHold || !liveHoldStartTime) {
      return;
    }

    const currentValues = form.getValues();

    form.reset({
      ...currentValues,
      start_time: liveHoldStartTime,
      duration_minutes: liveHoldDurationMinutes ?? currentValues.duration_minutes,
      guest_count: initialGuestCount ?? currentValues.guest_count,
      branch_id: selectedBranchId ?? currentValues.branch_id,
    });
  }, [form, initialGuestCount, liveHold, liveHoldDurationMinutes, liveHoldStartTime, selectedBranchId]);

  useEffect(() => {
    if (!liveHoldState?.isActive || !liveHoldState.expiresAt || holdActionPending) {
      return;
    }

    const expiresAtMs = Date.parse(liveHoldState.expiresAt);
    if (!Number.isFinite(expiresAtMs)) {
      return;
    }

    const delayMs = Math.max(1000, expiresAtMs - Date.now() - holdRefreshLeadMs);
    const timer = window.setTimeout(() => {
      void refreshOrRecoverHold();
    }, delayMs);

    const handleResume = () => {
      void revalidateHold();
    };
    const handleVisibilityChange = () => {
      if (document.visibilityState === "visible") {
        void revalidateHold();
      }
    };

    window.addEventListener("focus", handleResume);
    window.addEventListener("online", handleResume);
    document.addEventListener("visibilitychange", handleVisibilityChange);

    return () => {
      window.clearTimeout(timer);
      window.removeEventListener("focus", handleResume);
      window.removeEventListener("online", handleResume);
      document.removeEventListener("visibilitychange", handleVisibilityChange);
    };
  }, [holdActionPending, liveHoldState?.expiresAt, liveHoldState?.isActive, refreshOrRecoverHold, revalidateHold]);

  useEffect(() => {
    if (holdExpiredByClock && currentHoldId && !holdActionPending && !recoveryAttemptedRef.current) {
      void recoverHold();
    }
  }, [currentHoldId, holdActionPending, holdExpiredByClock, recoverHold]);

  const createMutation = useMutation({
    mutationFn: async (values: ReservationFormValues) => {
      persistBookingDraft(values);
      const readyHold = await ensureHoldReadyForSubmit();

      if (!readyHold) {
        throw new Error("hold_not_ready");
      }

      const submitWithHold = (hold: TableHold) =>
        createReservation({
          ...values,
          branch_id: values.branch_id ?? selectedBranchId ?? undefined,
          hold_id: hold.hold_id,
          table_ids: getTableIdsFromHold(hold, liveHoldTableIds),
        });

      try {
        return await submitWithHold(readyHold);
      } catch (error) {
        if (!submitRecoveryAttemptedRef.current && isRecoverableHoldError(error)) {
          submitRecoveryAttemptedRef.current = true;
          const recovered = await recoverHold(error);

          if (recovered) {
            return submitWithHold(recovered);
          }
        }

        throw error;
      }
    },
    onSuccess(reservation) {
      queryClient.setQueryData(queryKeys.reservations.detail(reservation.reservation_id), reservation);
      void queryClient.invalidateQueries({
        queryKey: queryKeys.reservations.lists,
        refetchType: "inactive",
      });
      clearStoredActiveTableHoldSnapshot(customerSessionId);

      if (preorderItems.length > 0) {
        storePendingReservationPreorderDraft(reservation.reservation_id, preorderItems, "post_reservation");
        if (selectedBranchId) {
          clearLocalPreorderCart(customerSessionId, selectedBranchId);
        }
        clearCustomerBookingDraft();
        toast.success("Đặt bàn thành công. Mộc Sen đã giữ giỏ món để bạn chọn sau.");
        router.push(`/reservations/${reservation.reservation_id}?next=preorder#preorder`);
        return;
      }

      clearCustomerBookingDraft();
      toast.success("Đặt bàn thành công.");
      router.push(`/reservations/${reservation.reservation_id}`);
    },
    onError(error) {
      if (isRecoverableHoldError(error)) {
        setHoldNoticeState(holdStateFromError(error));
      }
    },
  });

  const createError = createMutation.error;
  const createActionDisabled =
    createMutation.isPending ||
    holdQuery.isLoading ||
    Boolean(holdActionPending) ||
    currentHoldUxState === "idle" ||
    currentHoldUxState === "conflicted" ||
    currentHoldUxState === "invalidSession" ||
    currentHoldUxState === "failed" ||
    (currentHoldUxState === "expired" && recoveryAttemptedRef.current);
  const submitReservation = (values: ReservationFormValues) => {
    if (createActionDisabled) {
      return;
    }

    submitRecoveryAttemptedRef.current = false;
    createMutation.mutate(values);
  };

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6 pb-28 lg:pb-8">
      <section className="mb-5 space-y-3">
        <h1 className="text-4xl font-semibold tracking-normal">Xác nhận đặt bàn</h1>
        <p className="mt-2 max-w-2xl text-muted-foreground">
          Hoàn tất thông tin liên hệ để Mộc Sen Bistro xác nhận bàn nhanh. Bạn có thể chọn món trước sau khi đặt bàn thành công.
        </p>
        <BookingProgress currentStep="guest" />
        <div className="max-w-sm">
          <SelectedBranchEntry />
        </div>
      </section>

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <Card className="rounded-lg">
          <CardHeader>
            <CardTitle>{isAuthenticated ? "Thông tin liên hệ" : "Thông tin khách"}</CardTitle>
          </CardHeader>
          <CardContent>
            <form
              className="space-y-4"
              onSubmit={form.handleSubmit(submitReservation)}
            >
              {currentHoldId ? (
                <HoldStatusPanel
                  state={currentHoldUxState}
                  expiresAt={expiresAt}
                  remainingLabel={remainingLabel}
                  tableLabel={liveHoldTableLabel}
                  startLabel={visitStartLabel}
                  guestCount={watchedGuestCount ?? initialGuestCount}
                  durationMinutes={liveHoldDurationMinutes ?? initialDurationMinutes}
                  onRetry={() => void recoverHold(holdActionError)}
                  retryDisabled={Boolean(holdActionPending)}
                />
              ) : (
                <EmptyState
                  title="Chưa có bàn đang giữ"
                  description="Mộc Sen đã giữ thông tin của bạn. Vui lòng chọn lại bàn phù hợp để tiếp tục."
                  action={
                    <Button asChild variant="outline" className="rounded-lg">
                      <Link href="/booking">
                        <Search className="mr-2 h-4 w-4" />
                        Chọn bàn
                      </Link>
                    </Button>
                  }
                />
              )}

              {holdActionError ? (
                <Alert variant="destructive" className="rounded-lg">
                  <AlertDescription>{customerFriendlyHoldMessage(holdActionError, { recoveryFailed: true })}</AlertDescription>
                </Alert>
              ) : null}

              {isAuthenticated ? (
                <Alert className="rounded-lg">
                  <AlertDescription>
                    Đang dùng thông tin từ tài khoản{profile?.name ? ` ${profile.name}` : ""}. Bạn có thể bổ sung ghi chú nếu cần.
                  </AlertDescription>
                </Alert>
              ) : null}

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="guest_name">Tên khách</Label>
                  <Input id="guest_name" className="min-h-11 rounded-lg" {...form.register("guest_name")} />
                  {form.formState.errors.guest_name ? <p className="text-sm text-destructive">{form.formState.errors.guest_name.message}</p> : null}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="guest_phone">Số điện thoại</Label>
                  <Input id="guest_phone" className="min-h-11 rounded-lg" {...form.register("guest_phone")} />
                  {form.formState.errors.guest_phone ? <p className="text-sm text-destructive">{form.formState.errors.guest_phone.message}</p> : null}
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="guest_email">Email</Label>
                <Input id="guest_email" type="email" className="min-h-11 rounded-lg" {...form.register("guest_email")} />
                {form.formState.errors.guest_email ? <p className="text-sm text-destructive">{form.formState.errors.guest_email.message}</p> : null}
              </div>

              <div className="grid gap-4 sm:grid-cols-3">
                <div className="space-y-2 sm:col-span-1">
                  <Label htmlFor="start_time">Giờ đến</Label>
                  <Input
                    id="start_time"
                    type="datetime-local"
                    className="min-h-11 rounded-lg"
                    disabled={Boolean(currentHoldId)}
                    {...form.register("start_time")}
                  />
                  {form.formState.errors.start_time ? <p className="text-sm text-destructive">{form.formState.errors.start_time.message}</p> : null}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="duration_minutes">Thời lượng dùng bữa dự kiến</Label>
                  <Input
                    id="duration_minutes"
                    type="number"
                    min={30}
                    className="min-h-11 rounded-lg"
                    disabled={Boolean(currentHoldId)}
                    {...form.register("duration_minutes", { valueAsNumber: true })}
                  />
                  {form.formState.errors.duration_minutes ? <p className="text-sm text-destructive">{form.formState.errors.duration_minutes.message}</p> : null}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="guest_count">Số khách</Label>
                  <Input
                    id="guest_count"
                    type="number"
                    min={1}
                    className="min-h-11 rounded-lg"
                    disabled={Boolean(currentHoldId)}
                    {...form.register("guest_count", { valueAsNumber: true })}
                  />
                  {form.formState.errors.guest_count ? <p className="text-sm text-destructive">{form.formState.errors.guest_count.message}</p> : null}
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="notes">Ghi chú</Label>
                <Textarea id="notes" className="min-h-24 rounded-lg" {...form.register("notes")} />
              </div>

              <Alert className="rounded-lg">
                <CheckCircle2 className="h-4 w-4" />
                <AlertTitle>Bạn có thể chọn món trước sau khi đặt bàn thành công.</AlertTitle>
                <AlertDescription>
                  {preorderQuantity > 0
                    ? `Mộc Sen đang giữ ${preorderQuantity} món trong giỏ. Sau khi đặt bàn thành công, bạn có thể xem trước và lưu món đặt trước.`
                    : "Bạn có thể bỏ qua bước này và chọn món tại nhà hàng."}
                </AlertDescription>
              </Alert>

              {createError ? (
                <Alert variant="destructive" className="rounded-lg">
                  <AlertDescription>{customerFriendlyHoldMessage(createError, { recoveryFailed: true })}</AlertDescription>
                </Alert>
              ) : null}

              <Button
                type="submit"
                className="min-h-11 w-full rounded-lg"
                disabled={createActionDisabled}
              >
                <CheckCircle2 className="mr-2 h-4 w-4" />
                {createMutation.isPending ? "Đang xác nhận đặt bàn" : "Xác nhận đặt bàn"}
              </Button>
            </form>
          </CardContent>
        </Card>

        <StickyBookingSummary
          title="Tóm tắt đặt bàn"
          items={bookingSummaryItems}
          holdCode={currentHoldId}
          holdExpiresAt={expiresAt}
          holdStatusLabel={holdStatusCopy(currentHoldUxState, remainingLabel).title}
          primaryActionLabel={createMutation.isPending ? "Đang xác nhận đặt bàn" : "Xác nhận đặt bàn"}
          primaryActionDisabled={createActionDisabled}
          onPrimaryAction={() => form.handleSubmit(submitReservation)()}
        />
      </div>
    </main>
  );
}
