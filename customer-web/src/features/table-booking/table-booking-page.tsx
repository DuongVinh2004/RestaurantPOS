"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, CalendarClock, CheckCircle2, RefreshCw, Search, Users } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { toast } from "sonner";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { BookingProgress } from "@/components/booking/booking-progress";
import { StickyBookingSummary } from "@/components/booking/sticky-booking-summary";
import {
  clearCustomerBookingDraftHold,
  readCustomerBookingDraft,
  storeCustomerBookingDraft,
} from "@/features/booking/booking-draft-storage";
import { TimeSlotGrid, selectedDateFromLocalDateTime } from "@/components/booking/time-slot-grid";
import { SelectedBranchEntry } from "@/features/branch/branch-selector";
import { useBranchSelection } from "@/features/branch/hooks";
import { ErrorState, EmptyState, LoadingBlock } from "@/components/states/state-blocks";
import type { RestaurantTable, TableHold } from "@/lib/contracts/generated/restaurantpos-sdk";
import { createRoundedFutureLocalDateTimeInput, formatLocalDateTimeInput, parseLocalDateTimeInput } from "@/lib/contracts/datetime";
import { formatDateTime } from "@/lib/contracts/format";
import { queryKeys } from "@/lib/api/query-keys";
import { ACTIVE_TABLE_HOLD_SESSION_MESSAGE, customerFriendlyHoldMessage, isActiveTableHoldSessionError } from "@/lib/api/errors";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import { featureFlags } from "@/lib/config/feature-flags";
import {
  formatCustomerTableName,
  formatCustomerZone,
  translateCustomerStatus,
} from "@/lib/i18n/customer-display";
import { cn } from "@/lib/utils";
import { cancelTableHold, createTableHold, refreshTableHold, searchAvailableTables, type AvailableTablesResult } from "./api";
import { availabilitySearchSchema, type AvailabilitySearchValues } from "./schemas";
import {
  clearStoredActiveTableHoldSnapshot,
  createActiveTableHoldSnapshot,
  readStoredActiveTableHoldSnapshot,
  storeActiveTableHoldSnapshot,
  tableHoldFromSnapshot,
  parseAvailabilityMeta,
  parseTableHoldState,
  type ActiveTableHoldSnapshot,
  type AvailabilityMetaState,
} from "./state";

type BookingChoice = {
  key: string;
  tableIds: number[];
  title: string;
  meta: string;
  detail: string;
  statusLabel: string;
  zoneLabel: string;
};

type HoldSelection = {
  values: AvailabilitySearchValues;
  tableIds: number[];
};

type QuickDateKey = "today" | "tomorrow" | "weekend";

const guestQuickOptions = [
  { label: "1 khách", value: 1 },
  { label: "2 khách", value: 2 },
  { label: "3-4 khách", value: 4 },
  { label: "5-6 khách", value: 6 },
  { label: "Nhóm lớn", value: 8 },
];

const durationQuickOptions = [
  { label: "60 phút", value: 60 },
  { label: "90 phút", value: 90 },
  { label: "120 phút", value: 120 },
];

const holdRefreshLeadMs = 120_000;

const quickDateOptions: Array<{
  key: QuickDateKey;
  label: string;
  getValue: (currentValue: string) => string;
}> = [
  { key: "today", label: "Hôm nay", getValue: (currentValue) => localVisitTimeForDayOffset(currentValue, 0) },
  { key: "tomorrow", label: "Ngày mai", getValue: (currentValue) => localVisitTimeForDayOffset(currentValue, 1) },
  { key: "weekend", label: "Cuối tuần", getValue: localVisitTimeForWeekend },
];

// Trích xuất thông báo lỗi Validation (422) từ Backend Laravel (Type-Safe)
function getErrorMessage(error: unknown, defaultTitle: string): string {
  const err = (error ?? {}) as Record<string, unknown>;
  const response = (err.response ?? {}) as Record<string, unknown>;
  const status = err.status ?? response.status;

  if (status === 422) {
    const data = (response.data ?? err.data ?? {}) as Record<string, unknown>;

    if (data.errors && typeof data.errors === "object" && !Array.isArray(data.errors)) {
      const errorsObj = data.errors as Record<string, unknown>;
      const firstKey = Object.keys(errorsObj)[0];
      if (firstKey) {
        const firstError = Array.isArray(errorsObj[firstKey]) ? errorsObj[firstKey][0] : errorsObj[firstKey];
        if (typeof firstError === "string") {
          return `${defaultTitle} (${firstError})`;
        }
      }
    }

    if (typeof data.message === "string") {
      return `${defaultTitle} (${data.message})`;
    }
  }

  return defaultTitle;
}

function tableIdKey(tableIds: number[]): string {
  return [...new Set(tableIds)].sort((left, right) => left - right).join(",");
}

function localVisitTimeForDayOffset(currentValue: string, dayOffset: number): string {
  const current = parseLocalDateTimeInput(currentValue);
  const date = new Date();

  date.setDate(date.getDate() + dayOffset);
  date.setHours(current?.getHours() ?? 19, current?.getMinutes() ?? 0, 0, 0);

  return formatLocalDateTimeInput(date);
}

function localVisitTimeForWeekend(currentValue: string): string {
  const current = parseLocalDateTimeInput(currentValue);
  const date = new Date();
  const currentDay = date.getDay();
  const daysUntilSaturday = currentDay === 6 ? 0 : (6 - currentDay + 7) % 7 || 7;

  date.setDate(date.getDate() + daysUntilSaturday);
  date.setHours(current?.getHours() ?? 19, current?.getMinutes() ?? 0, 0, 0);

  return formatLocalDateTimeInput(date);
}

function localDateStamp(date: Date): string {
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, "0"),
    String(date.getDate()).padStart(2, "0"),
  ].join("-");
}

function selectedQuickDateKey(currentValue: string): QuickDateKey | null {
  const selected = parseLocalDateTimeInput(currentValue);

  if (!selected) {
    return null;
  }

  const selectedStamp = localDateStamp(selected);
  const today = parseLocalDateTimeInput(localVisitTimeForDayOffset(currentValue, 0));
  const tomorrow = parseLocalDateTimeInput(localVisitTimeForDayOffset(currentValue, 1));
  const weekend = parseLocalDateTimeInput(localVisitTimeForWeekend(currentValue));

  if (today && selectedStamp === localDateStamp(today)) {
    return "today";
  }

  if (tomorrow && selectedStamp === localDateStamp(tomorrow)) {
    return "tomorrow";
  }

  if (weekend && selectedStamp === localDateStamp(weekend)) {
    return "weekend";
  }

  return null;
}

function getTableIdsFromHold(hold: TableHold): number[] {
  return Array.isArray(hold.tables)
    ? hold.tables
        .map((table) => table.table_id)
        .filter((tableId): tableId is number => Number.isInteger(tableId) && tableId > 0)
    : [];
}

function visitDetailsFromSnapshot(snapshot: ActiveTableHoldSnapshot): AvailabilitySearchValues | null {
  const startTime = new Date(snapshot.start_time);
  const endTime = new Date(snapshot.end_time);

  if (!Number.isFinite(startTime.getTime()) || !Number.isFinite(endTime.getTime()) || endTime <= startTime) {
    return null;
  }

  return {
    start_time: formatLocalDateTimeInput(startTime),
    duration_minutes: snapshot.duration_minutes ?? Math.max(30, Math.round((endTime.getTime() - startTime.getTime()) / 60_000)),
    guest_count: snapshot.guest_count ?? 2,
    branch_id: snapshot.branch_id ?? undefined,
  };
}

function tableSeatsLabel(seats: number | null | undefined): string {
  return seats ? `${seats} chỗ` : "Chưa rõ số chỗ";
}

function buildSingleTableChoice(table: RestaurantTable): BookingChoice {
  const tableName = formatCustomerTableName(table.table_code, table.zone, table.table_id);
  const zoneLabel = formatCustomerZone(table.zone);

  return {
    key: tableIdKey([table.table_id]),
    tableIds: [table.table_id],
    title: tableName,
    meta: `${zoneLabel} • ${tableSeatsLabel(table.seats)}`,
    detail: table.description?.trim() || "Phù hợp để đặt cho khung giờ đã chọn.",
    statusLabel: translateCustomerStatus(table.status),
    zoneLabel,
  };
}

function buildBookingChoices(tables: RestaurantTable[], meta: AvailabilityMetaState | null): BookingChoice[] {
  const tableById = new Map(tables.map((table) => [table.table_id, table]));

  if (meta?.suggestions.length) {
    return meta.suggestions.map((suggestion) => {
      const enrichedTables = suggestion.tableIds
        .map((tableId) => tableById.get(tableId))
        .filter((table): table is RestaurantTable => Boolean(table));
      const tableNames = suggestion.tableIds.map((tableId) => {
        const table = tableById.get(tableId);

        return formatCustomerTableName(table?.table_code ?? null, table?.zone ?? null, tableId);
      });
      const zoneLabels = [
        ...new Set(enrichedTables.map((table) => formatCustomerZone(table.zone))),
      ];
      const zoneLabel = zoneLabels.join(", ") || "Khu phù hợp";
      const seats = suggestion.totalSeats ?? enrichedTables.reduce((sum, table) => sum + (table.seats ?? 0), 0);
      const statusLabels = [...new Set(enrichedTables.map((table) => translateCustomerStatus(table.status)))];

      return {
        key: tableIdKey(suggestion.tableIds),
        tableIds: suggestion.tableIds,
        title: tableNames.length === 1 ? tableNames[0] : `Ghép ${tableNames.join(" + ")}`,
        meta: `${zoneLabel} • ${tableSeatsLabel(seats || null)}`,
        detail: suggestion.over && suggestion.over > 0
          ? `Dư ${suggestion.over} chỗ so với số khách đã chọn.`
          : "Vừa với số khách đã chọn.",
        statusLabel: statusLabels.length === 1 ? statusLabels[0] : "Có thể đặt",
        zoneLabel,
      };
    });
  }

  return tables.map(buildSingleTableChoice);
}

function groupChoicesByZone(choices: BookingChoice[]): Array<{ zoneLabel: string; choices: BookingChoice[] }> {
  const groups = new Map<string, BookingChoice[]>();

  for (const choice of choices) {
    const zoneLabel = choice.zoneLabel || "Khu phù hợp";
    groups.set(zoneLabel, [...(groups.get(zoneLabel) ?? []), choice]);
  }

  return Array.from(groups.entries()).map(([zoneLabel, groupChoices]) => ({ zoneLabel, choices: groupChoices }));
}

function holdMatchesSelection(
  holdState: ReturnType<typeof parseTableHoldState> | null,
  heldVisitDetails: AvailabilitySearchValues | null,
  heldTableIds: number[],
  values: AvailabilitySearchValues,
  tableIds: number[],
): boolean {
  return Boolean(
    holdState?.isActive
      && heldVisitDetails
      && tableIdKey(heldTableIds) === tableIdKey(tableIds)
      && heldVisitDetails.start_time === values.start_time
      && heldVisitDetails.duration_minutes === values.duration_minutes
      && heldVisitDetails.guest_count === values.guest_count
      && heldVisitDetails.branch_id === values.branch_id,
  );
}

function isHoldResolutionError(error: unknown): boolean {
  const err = (error ?? {}) as Record<string, unknown>;
  const response = (err.response ?? {}) as Record<string, unknown>;
  const status = err.status ?? response.status;

  return status === 422 || status === 404 || status === 409;
}

export function TableBookingPage() {
  const queryClient = useQueryClient();
  const branchSelection = useBranchSelection();
  const [isMounted, setIsMounted] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => setIsMounted(true), 0);
    return () => clearTimeout(timer);
  }, []);

  const [initialSession] = useState(() => {
    if (typeof window === "undefined") {
      return { sessionId: "", snapshot: null, draft: null };
    }
    const sessionId = ensureCustomerSessionId();
    const snapshot = readStoredActiveTableHoldSnapshot(sessionId);
    const draft = readCustomerBookingDraft(sessionId);
    return { sessionId, snapshot, draft };
  });

  const customerSessionId = initialSession.sessionId;
  const restoredVisitDetails = initialSession.snapshot ? visitDetailsFromSnapshot(initialSession.snapshot) : null;
  const holdCreateInFlightKeyRef = useRef<string | null>(null);
  const [availability, setAvailability] = useState<AvailableTablesResult | null>(null);
  const [availabilitySearchValues, setAvailabilitySearchValues] = useState<AvailabilitySearchValues | null>(null);
  const [hold, setHold] = useState<TableHold | null>(() => initialSession.snapshot ? tableHoldFromSnapshot(initialSession.snapshot) : null);
  const [heldVisitDetails, setHeldVisitDetails] = useState<AvailabilitySearchValues | null>(restoredVisitDetails);
  const [heldTableIds, setHeldTableIds] = useState<number[]>(() => initialSession.snapshot?.table_ids ?? []);
  const [selectedTableIds, setSelectedTableIds] = useState<number[]>(() => initialSession.snapshot?.table_ids ?? []);

  const searchParams = useSearchParams();
  const qGuestCount = searchParams.get("guest_count");
  const qDate = searchParams.get("date");
  const qTime = searchParams.get("time");
  const qBranchId = searchParams.get("branch_id");

  const initialStartTime = useMemo(() => {
    if (restoredVisitDetails?.start_time) {
      return restoredVisitDetails.start_time;
    }
    if (qDate && qTime) {
      const date = new Date();
      if (qDate === "tomorrow") {
        date.setDate(date.getDate() + 1);
      } else if (qDate === "weekend") {
        const currentDay = date.getDay();
        const daysUntilSaturday = currentDay === 6 ? 0 : (6 - currentDay + 7) % 7 || 7;
        date.setDate(date.getDate() + daysUntilSaturday);
      }
      
      const [hours, minutes] = qTime.split(":").map(Number);
      if (Number.isInteger(hours) && Number.isInteger(minutes)) {
        date.setHours(hours, minutes, 0, 0);
      } else {
        date.setHours(19, 0, 0, 0);
      }
      return formatLocalDateTimeInput(date);
    }
    return initialSession.draft?.start_time ?? createRoundedFutureLocalDateTimeInput();
  }, [restoredVisitDetails, qDate, qTime, initialSession.draft?.start_time]);

  const initialGuestCount = useMemo(() => {
    if (restoredVisitDetails?.guest_count) {
      return restoredVisitDetails.guest_count;
    }
    if (qGuestCount) {
      const count = parseInt(qGuestCount, 10);
      if (Number.isInteger(count) && count >= 1 && count <= 20) {
        return count;
      }
    }
    return initialSession.draft?.guest_count ?? 2;
  }, [restoredVisitDetails, qGuestCount, initialSession.draft?.guest_count]);

  const initialBranchId = useMemo(() => {
    if (restoredVisitDetails?.branch_id) {
      return restoredVisitDetails.branch_id;
    }
    if (qBranchId) {
      const bId = parseInt(qBranchId, 10);
      if (Number.isInteger(bId)) {
        return bId;
      }
    }
    return initialSession.draft?.branch_id ?? branchSelection.selectedBranch?.branchId ?? undefined;
  }, [restoredVisitDetails, qBranchId, initialSession.draft?.branch_id, branchSelection.selectedBranch]);

  const form = useForm<AvailabilitySearchValues>({
    resolver: zodResolver(availabilitySearchSchema),
    defaultValues: {
      start_time: initialStartTime,
      duration_minutes: restoredVisitDetails?.duration_minutes ?? initialSession.draft?.duration_minutes ?? 90,
      guest_count: initialGuestCount,
      branch_id: initialBranchId,
    },
  });

  useEffect(() => {
    if (qBranchId) {
      const bId = parseInt(qBranchId, 10);
      if (Number.isInteger(bId) && bId !== branchSelection.selectedBranchId) {
        branchSelection.selectBranch(bId);
      }
    }
  }, [qBranchId, branchSelection]);
  const startTimeValue = useWatch({ control: form.control, name: "start_time" });
  const guestCountValue = useWatch({ control: form.control, name: "guest_count" });
  const durationMinutesValue = useWatch({ control: form.control, name: "duration_minutes" });

  const holdState = hold ? parseTableHoldState(hold) : null;

  useEffect(() => {
    const selectedBranchId = branchSelection.selectedBranch?.branchId;

    if (!selectedBranchId || form.getValues("branch_id") === selectedBranchId) {
      return;
    }

    form.setValue("branch_id", selectedBranchId, {
      shouldDirty: false,
      shouldValidate: true,
    });
  }, [branchSelection.selectedBranch?.branchId, form]);

  function applyActiveHold(
    result: TableHold,
    visitDetails: AvailabilitySearchValues | null,
    fallbackTableIds: number[],
  ) {
    const nextTableIds = getTableIdsFromHold(result);
    const effectiveTableIds = nextTableIds.length > 0 ? nextTableIds : [...fallbackTableIds];
    const nextState = parseTableHoldState(result);

    setHold(result);
    setHeldVisitDetails(visitDetails);
    setHeldTableIds(effectiveTableIds);
    setSelectedTableIds(effectiveTableIds);
    storeCustomerBookingDraft({
      branch_id: visitDetails?.branch_id ?? null,
      start_time: visitDetails?.start_time ?? null,
      duration_minutes: visitDetails?.duration_minutes ?? null,
      guest_count: visitDetails?.guest_count ?? null,
      selected_table_ids: effectiveTableIds,
      hold_id: result.hold_id,
      hold_expires_at: result.expire_at ?? null,
      hold_row_version: result.row_version ?? null,
    });
    queryClient.setQueryData(queryKeys.tableBooking.hold(result.hold_id), result);

    const snapshot = createActiveTableHoldSnapshot(result, {
      sessionId: customerSessionId,
      tableIds: effectiveTableIds,
      startTime: visitDetails?.start_time,
      durationMinutes: visitDetails?.duration_minutes ?? null,
      guestCount: visitDetails?.guest_count ?? null,
      branchId: visitDetails?.branch_id ?? null,
    });

    if (snapshot && nextState.isActive) {
      storeActiveTableHoldSnapshot(snapshot);
    } else {
      clearStoredActiveTableHoldSnapshot(customerSessionId);
    }
  }

  const cancelHoldMutation = useMutation({
    mutationFn: ({ holdId, rowVersion }: { holdId: string; rowVersion: number }) => cancelTableHold(holdId, rowVersion),
    onSuccess() {
      setHold(null);
      setHeldVisitDetails(null);
      setHeldTableIds([]);
      setSelectedTableIds([]);
      clearCustomerBookingDraftHold();
      clearStoredActiveTableHoldSnapshot(customerSessionId);
    },
    onError(error: unknown) {
      const err = (error ?? {}) as Record<string, unknown>;
      const response = (err.response ?? {}) as Record<string, unknown>;
      const status = err.status ?? response.status;

      if (status === 422 || status === 404 || status === 409) {
        setHold(null);
        setHeldVisitDetails(null);
        setHeldTableIds([]);
        clearCustomerBookingDraftHold();
        clearStoredActiveTableHoldSnapshot(customerSessionId);
        if (!holdCreateInFlightKeyRef.current) {
          setSelectedTableIds([]);
        }
      }
    }
  });

  const searchMutation = useMutation({
    mutationFn: (values: AvailabilitySearchValues) => searchAvailableTables(values),
    onMutate() {
      setAvailability(null);
      setAvailabilitySearchValues(null);
      const values = form.getValues();
      storeCustomerBookingDraft({
        branch_id: values.branch_id ?? null,
        start_time: values.start_time,
        duration_minutes: values.duration_minutes,
        guest_count: values.guest_count,
      });
      if (!holdState?.isActive) {
        setSelectedTableIds([]);
      }
    },
    onSuccess(result, values) {
      setAvailability(result);
      setAvailabilitySearchValues(values);
      storeCustomerBookingDraft({
        branch_id: values.branch_id ?? null,
        start_time: values.start_time,
        duration_minutes: values.duration_minutes,
        guest_count: values.guest_count,
      });
    },
  });

  const holdSelectionMutation = useMutation({
    mutationFn: ({ values, tableIds }: HoldSelection) => createTableHold(values, tableIds),
    onSuccess(result, selection) {
      applyActiveHold(result, selection.values, selection.tableIds);
    },
    onError(error: unknown) {
      const err = (error ?? {}) as Record<string, unknown>;
      const response = (err.response ?? {}) as Record<string, unknown>;
      const status = err.status ?? response.status;
      const message = typeof err.message === 'string' ? err.message : '';
      const isConflictError = status === 409 || message.includes('conflict');

      if (isConflictError) {
        toast.error(customerFriendlyHoldMessage(error), { duration: 4000 });

        setSelectedTableIds([]);
        searchMutation.mutate(form.getValues());
      } else if (status === 422) {
        toast.error(customerFriendlyHoldMessage(error));
      } else {
        toast.error("Mộc Sen chưa thể giữ bàn lúc này. Vui lòng thử lại sau ít phút.");
      }
    },
    onSettled() {
      holdCreateInFlightKeyRef.current = null;
    },
  });

  const refreshHoldMutation = useMutation({
    mutationFn: ({ holdId, rowVersion }: { holdId: string; rowVersion: number }) => refreshTableHold(holdId, rowVersion),
    onSuccess(result) {
      applyActiveHold(result, heldVisitDetails, heldTableIds);
    },
    onError(error: unknown) {
      const err = (error ?? {}) as Record<string, unknown>;
      const response = (err.response ?? {}) as Record<string, unknown>;
      const status = err.status ?? response.status;

      if (status === 422 || status === 404 || status === 409) {
        setHold(null);
        setHeldVisitDetails(null);
        setHeldTableIds([]);
        setSelectedTableIds([]);
        clearCustomerBookingDraftHold();
        clearStoredActiveTableHoldSnapshot(customerSessionId);
        toast.error("Bàn vừa hết thời gian giữ, nhưng thông tin của bạn vẫn được giữ nguyên.");
      }
    }
  });

  const tables = useMemo(() => availability?.tables ?? [], [availability?.tables]);
  const availabilityMeta = useMemo(
    () => (availability ? parseAvailabilityMeta(availability.meta, tables.length) : null),
    [availability, tables.length],
  );
  const choices = useMemo(() => buildBookingChoices(tables, availabilityMeta), [availabilityMeta, tables]);
  const groupedChoices = useMemo(() => groupChoicesByZone(choices), [choices]);
  const selectedKey = tableIdKey(selectedTableIds);
  const selectedChoice = useMemo(() => choices.find((choice) => choice.key === selectedKey) ?? null, [choices, selectedKey]);

  const pendingSelectionKey = holdSelectionMutation.variables
    ? tableIdKey(holdSelectionMutation.variables.tableIds)
    : (cancelHoldMutation.isPending ? selectedKey : "");

  const selectedDate = selectedDateFromLocalDateTime(startTimeValue);
  const activeQuickDate = selectedQuickDateKey(startTimeValue);
  const currentStep = holdState?.isActive || availability ? "table" : "time";
  const activeVisitDetails = heldVisitDetails ?? availabilitySearchValues ?? form.getValues();
  const bookingSummaryItems = [
    {
      label: "Ngày giờ",
      value: activeVisitDetails.start_time
        ? formatDateTime(new Date(activeVisitDetails.start_time).toISOString())
        : "Chưa chọn",
    },
    { label: "Số khách", value: `${activeVisitDetails.guest_count ?? 0} khách` },
    { label: "Thời lượng", value: `${activeVisitDetails.duration_minutes ?? 0} phút` },
    { label: "Bàn đã chọn", value: selectedChoice?.title ?? (heldTableIds.length ? `${heldTableIds.length} bàn` : "Chưa chọn") },
  ];

  const isoStartTime = heldVisitDetails?.start_time ?? "";
  const isHoldTransitionPending = holdSelectionMutation.isPending || cancelHoldMutation.isPending;
  const searchPending = searchMutation.isPending || isHoldTransitionPending;
  const reservationCreateHref = holdState?.isActive && heldVisitDetails && !isHoldTransitionPending
    ? `/booking/preorder?hold_id=${encodeURIComponent(holdState.holdId)}&hold_status=${encodeURIComponent(holdState.status)}&hold_expires_at=${encodeURIComponent(holdState.expiresAt ?? "")}&tables=${heldTableIds.join(",")}&start_time=${encodeURIComponent(isoStartTime)}&duration_minutes=${heldVisitDetails.duration_minutes}&guest_count=${heldVisitDetails.guest_count}&branch_id=${heldVisitDetails.branch_id ?? branchSelection.selectedBranch?.branchId ?? ""}`
    : null;
  const activeHoldSessionError = Boolean(holdSelectionMutation.error && isActiveTableHoldSessionError(holdSelectionMutation.error));

  useEffect(() => {
    if (!holdState?.isActive) {
      clearStoredActiveTableHoldSnapshot(customerSessionId);
    }
  }, [customerSessionId, holdState?.isActive]);

  useEffect(() => {
    if (!isMounted || initialSession.snapshot || !initialSession.draft?.start_time) {
      return;
    }

    const values = form.getValues();
    searchMutation.mutate(values);
    // Run once after a saved draft returns to table selection.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isMounted]);

  useEffect(() => {
    const syncAndRefreshHold = () => {
      const currentSnapshot = readStoredActiveTableHoldSnapshot(customerSessionId);

      if (!holdState?.isActive || !holdState.expiresAt) return;

      if (currentSnapshot && currentSnapshot.hold_id !== holdState.holdId) {
        return;
      }

      const expiresAtMs = Date.parse(holdState.expiresAt);
      if (!Number.isFinite(expiresAtMs)) return;

      const now = Date.now();
      const timeLeftMs = expiresAtMs - now;

      if (timeLeftMs <= 0) {
        toast.error("Bàn vừa hết thời gian giữ, nhưng thông tin của bạn vẫn được giữ nguyên.");
        cancelHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion });
      } else if (timeLeftMs < holdRefreshLeadMs && !refreshHoldMutation.isPending) {
        refreshHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion });
      }
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === "visible") {
        syncAndRefreshHold();
      }
    };

    document.addEventListener("visibilitychange", handleVisibilityChange);
    window.addEventListener("focus", syncAndRefreshHold);
    window.addEventListener("online", syncAndRefreshHold);

    let timer: number | undefined;
    if (holdState?.isActive && holdState.expiresAt && !refreshHoldMutation.isPending) {
      const expiresAtMs = Date.parse(holdState.expiresAt);
      if (Number.isFinite(expiresAtMs)) {
        const delayMs = Math.max(1000, expiresAtMs - Date.now() - holdRefreshLeadMs);
        timer = window.setTimeout(() => syncAndRefreshHold(), delayMs);
      }
    }

    return () => {
      document.removeEventListener("visibilitychange", handleVisibilityChange);
      window.removeEventListener("focus", syncAndRefreshHold);
      window.removeEventListener("online", syncAndRefreshHold);
      if (timer !== undefined) {
        window.clearTimeout(timer);
      }
    };
  }, [holdState?.expiresAt, holdState?.holdId, holdState?.isActive, holdState?.rowVersion, customerSessionId, refreshHoldMutation, cancelHoldMutation]);

  async function chooseTables(tableIds: number[]) {
    const values = availabilitySearchValues ?? form.getValues();
    const selectionKey = tableIdKey(tableIds);
    const storedActiveHold = readStoredActiveTableHoldSnapshot(customerSessionId);
    const restoredVisitDetailsFromStorage = storedActiveHold ? visitDetailsFromSnapshot(storedActiveHold) : null;
    const restoredHold = storedActiveHold ? tableHoldFromSnapshot(storedActiveHold) : null;
    const activeHold = storedActiveHold && (!holdState?.isActive || holdState.holdId !== storedActiveHold.hold_id)
      ? restoredHold
      : hold;
    const activeHoldState = activeHold ? parseTableHoldState(activeHold) : null;
    const activeHeldVisitDetails = restoredHold ? restoredVisitDetailsFromStorage : heldVisitDetails;
    const activeHeldTableIds = restoredHold ? storedActiveHold?.table_ids ?? [] : heldTableIds;

    if (restoredHold && storedActiveHold && (!holdState?.isActive || holdState.holdId !== storedActiveHold.hold_id)) {
      setHold(restoredHold);
      setHeldVisitDetails(restoredVisitDetailsFromStorage);
      setHeldTableIds(storedActiveHold.table_ids);
      setSelectedTableIds(storedActiveHold.table_ids);
    }

    if (holdMatchesSelection(activeHoldState, activeHeldVisitDetails, activeHeldTableIds, values, tableIds)) {
      setSelectedTableIds(tableIds);
      return;
    }

    if (holdSelectionMutation.isPending || cancelHoldMutation.isPending || holdCreateInFlightKeyRef.current === selectionKey) {
      return;
    }

    if (activeHoldState?.isActive) {
      setSelectedTableIds(tableIds);

      try {
        await cancelHoldMutation.mutateAsync({ holdId: activeHoldState.holdId, rowVersion: activeHoldState.rowVersion });
      } catch (error) {
        if (!isHoldResolutionError(error)) {
          setSelectedTableIds(activeHeldTableIds);
          toast.error("Chưa thể đổi bàn lúc này. Vui lòng thử lại.");
          return;
        }
      }
    }

    holdCreateInFlightKeyRef.current = selectionKey;
    setSelectedTableIds(tableIds);
    storeCustomerBookingDraft({
      branch_id: values.branch_id ?? null,
      start_time: values.start_time,
      duration_minutes: values.duration_minutes,
      guest_count: values.guest_count,
      selected_table_ids: tableIds,
    });
    holdSelectionMutation.mutate({
      values,
      tableIds,
    });
  }

  function submitAvailabilitySearch(values: AvailabilitySearchValues) {
    if (searchPending) {
      return;
    }

    searchMutation.mutate(values);
  }

  if (!isMounted) {
    return (
      <main className="mx-auto w-full max-w-6xl px-4 py-6 pb-28 lg:pb-8">
        <section className="mb-6 space-y-3">
          <Badge variant="outline" className="rounded-md">Đặt bàn</Badge>
          <h1 className="text-4xl font-semibold tracking-normal">Tìm bàn trống cho bữa ăn của bạn</h1>
          <p className="max-w-xl text-muted-foreground">
            Chọn số khách, ngày giờ và thời lượng dùng bữa dự kiến. Sau khi chọn bàn, nhà hàng sẽ giữ tạm trong vài phút để bạn hoàn tất đặt chỗ.
          </p>
          <BookingProgress currentStep="time" />
          <div className="max-w-sm">
            <SelectedBranchEntry />
          </div>
        </section>
        <div className="grid gap-5 xl:grid-cols-[320px_minmax(0,1fr)_320px]">
          <Card className="h-fit rounded-lg">
            <CardHeader>
              <CardTitle>Thông tin bữa ăn</CardTitle>
            </CardHeader>
            <CardContent className="flex items-center justify-center py-10">
              <LoadingBlock label="Đang tải dữ liệu phiên..." />
            </CardContent>
          </Card>
        </div>
      </main>
    );
  }

  return (
    <main className="mx-auto w-full max-w-6xl px-4 py-6 pb-28 lg:pb-8">
      <section className="mb-6 space-y-3">
        <Badge variant="outline" className="rounded-md">Đặt bàn</Badge>
        <h1 className="text-4xl font-semibold tracking-normal">Tìm bàn trống cho bữa ăn của bạn</h1>
        <p className="max-w-xl text-muted-foreground">
          Chọn số khách, ngày giờ và thời lượng dùng bữa dự kiến. Sau khi chọn bàn, nhà hàng sẽ giữ tạm trong vài phút để bạn hoàn tất đặt chỗ.
        </p>
        <BookingProgress currentStep={currentStep} />
        <div className="max-w-sm">
          <SelectedBranchEntry />
        </div>
      </section>

      <div className="grid gap-5 xl:grid-cols-[320px_minmax(0,1fr)_320px]">
        <Card className="h-fit rounded-lg shadow-[var(--restaurant-shadow)]">
          <CardHeader className="border-b bg-secondary/30 pb-4">
            <CardTitle className="text-xl">Thông tin bữa ăn</CardTitle>
          </CardHeader>
          <CardContent className="pt-6">
            <form className="space-y-4" onSubmit={form.handleSubmit(submitAvailabilitySearch)}>
              <section className="rounded-2xl bg-muted/30 p-5 shadow-none transition-shadow hover:bg-muted/40 border border-transparent">
                <div className="mb-4 flex items-center justify-between gap-3">
                  <div>
                    <p className="text-base font-bold text-foreground">1. Chọn số khách</p>
                    <p className="text-xs text-muted-foreground mt-0.5">Dùng chip nhanh hoặc nhập số chính xác.</p>
                  </div>
                  <Badge variant="outline" className="rounded-full bg-background px-3 py-1 font-semibold">{guestCountValue ?? 0} khách</Badge>
                </div>
                <div className="mb-4 flex flex-wrap gap-2">
                  {guestQuickOptions.map((option) => (
                    <Button
                      key={option.label}
                      type="button"
                      variant={guestCountValue === option.value ? "default" : "outline"}
                      className={cn("min-h-10 rounded-full transition-all duration-200 px-5", guestCountValue !== option.value ? "hover:-translate-y-0.5 hover:shadow-sm bg-background border-transparent shadow-sm" : "shadow-md")}
                      aria-pressed={guestCountValue === option.value}
                      onClick={() => form.setValue("guest_count", option.value, { shouldDirty: true, shouldValidate: true })}
                    >
                      {option.label}
                    </Button>
                  ))}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="guest_count" className="text-sm font-medium ml-1">Số khách tùy chỉnh</Label>
                  <Input id="guest_count" type="number" min={1} className="min-h-12 rounded-xl bg-background border-transparent focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all shadow-sm" data-testid="customer-party-size-input" {...form.register("guest_count", { valueAsNumber: true })} />
                  {form.formState.errors.guest_count ? <p className="text-[13px] font-medium text-destructive ml-1">{form.formState.errors.guest_count.message}</p> : null}
                </div>
              </section>



              <section className="rounded-2xl bg-muted/30 p-5 shadow-none transition-shadow hover:bg-muted/40 border border-transparent">
                <div className="mb-4">
                  <p className="text-base font-bold text-foreground">2. Chọn ngày & giờ</p>
                  <p className="text-xs text-muted-foreground mt-0.5">Có thể bấm khung giờ gợi ý hoặc nhập trực tiếp.</p>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="start_time" className="text-sm font-medium ml-1">Ngày và giờ tùy chỉnh</Label>
                  <Input id="start_time" type="datetime-local" className="min-h-12 rounded-xl bg-background border-transparent focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all shadow-sm" data-testid="customer-date-input" {...form.register("start_time")} />
                  {form.formState.errors.start_time ? <p className="text-[13px] font-medium text-destructive ml-1">{form.formState.errors.start_time.message}</p> : null}
                </div>
                <div className="mt-4">
                  <TimeSlotGrid
                    selectedDate={selectedDate}
                    selectedValue={startTimeValue}
                    onSelect={(nextValue) => form.setValue("start_time", nextValue, { shouldDirty: true, shouldValidate: true })}
                  />
                </div>
              </section>

              <section className="rounded-2xl bg-muted/30 p-5 shadow-none transition-shadow hover:bg-muted/40 border border-transparent">
                <div className="mb-4 flex items-center justify-between gap-3">
                  <div>
                    <p className="text-base font-bold text-foreground">4. Thời lượng dùng bữa dự kiến</p>
                    <p className="text-xs text-muted-foreground mt-0.5">Mặc định 90 phút cho một lượt ghé.</p>
                  </div>
                  <Badge variant="outline" className="rounded-full bg-background px-3 py-1 font-semibold">{durationMinutesValue ?? 0} phút</Badge>
                </div>
                <div className="mb-4 flex flex-wrap gap-2">
                  {durationQuickOptions.map((option) => (
                    <Button
                      key={option.value}
                      type="button"
                      variant={durationMinutesValue === option.value ? "default" : "outline"}
                      className={cn("min-h-10 rounded-full transition-all duration-200 px-5", durationMinutesValue !== option.value ? "hover:-translate-y-0.5 hover:shadow-sm bg-background border-transparent shadow-sm" : "shadow-md")}
                      aria-pressed={durationMinutesValue === option.value}
                      onClick={() => form.setValue("duration_minutes", option.value, { shouldDirty: true, shouldValidate: true })}
                    >
                      {option.label}
                    </Button>
                  ))}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="duration_minutes" className="text-sm font-medium ml-1">Thời lượng tùy chỉnh (phút)</Label>
                  <Input id="duration_minutes" type="number" min={30} className="min-h-12 rounded-xl bg-background border-transparent focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all shadow-sm" data-testid="customer-duration-input" {...form.register("duration_minutes", { valueAsNumber: true })} />
                  {form.formState.errors.duration_minutes ? <p className="text-[13px] font-medium text-destructive ml-1">{form.formState.errors.duration_minutes.message}</p> : null}
                </div>
              </section>
              <Button type="submit" className="min-h-12 w-full rounded-full font-semibold shadow-md active:scale-[0.98] transition-all" disabled={searchPending} data-testid="customer-search-tables-btn">
                <Search className="mr-2 h-5 w-5" />
                {searchMutation.isPending ? "Đang tìm bàn" : "Tìm bàn"}
              </Button>
            </form>
          </CardContent>
        </Card>

        <section className="space-y-4">
          {searchMutation.isPending ? <LoadingBlock label="Đang tìm bàn trống" /> : null}
          {searchMutation.error ? (
            <ErrorState
              error={searchMutation.error}
              title={getErrorMessage(searchMutation.error, "Chưa tìm được bàn trống")}
              onRetry={() => searchMutation.mutate(form.getValues())}
            />
          ) : null}

          {!searchMutation.isPending && !searchMutation.error && !availability ? (
            <EmptyState
              title="Bắt đầu tìm bàn"
              description="Chọn số khách, ngày giờ và thời lượng dùng bữa. Kết quả bàn phù hợp sẽ hiện tại đây để bạn giữ chỗ."
              action={
                <Button type="button" className="rounded-lg" onClick={() => submitAvailabilitySearch(form.getValues())}>
                  <Search className="mr-2 h-4 w-4" />
                  Tìm bàn trống
                </Button>
              }
            />
          ) : null}

          {availability && tables.length === 0 ? (
            <EmptyState
              title="Chưa có bàn trống"
              description="Thử đổi ngày, giờ hoặc số khách để tìm lựa chọn khác."
              action={
                featureFlags.waitingList ? (
                  <Button asChild variant="outline" className="rounded-lg">
                    <Link href="/waiting-list">Ghi danh chờ bàn</Link>
                  </Button>
                ) : undefined
              }
            />
          ) : null}
          {availabilityMeta ? (
            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
              <span>Đang hiển thị {availabilityMeta.count} bàn phù hợp</span>
              <span>theo múi giờ {availabilityMeta.branchTimezone ?? availabilityMeta.timezone ?? "của nhà hàng"}.</span>
            </div>
          ) : null}

          {choices.length > 0 ? (
            <div className="space-y-4">
              {groupedChoices.map((group) => (
                <section key={group.zoneLabel} className="space-y-2" aria-label={`Bàn tại ${group.zoneLabel}`}>
                  <div className="flex items-center justify-between gap-3">
                    <h2 className="text-sm font-semibold">{group.zoneLabel}</h2>
                    <span className="text-xs text-muted-foreground">{group.choices.length} lựa chọn</span>
                  </div>
                  <div className="grid gap-3 sm:grid-cols-2">
                    {group.choices.map((choice) => {
                      const selected = selectedKey === choice.key;
                      const pending = (holdSelectionMutation.isPending || cancelHoldMutation.isPending) && pendingSelectionKey === choice.key;

                      return (
                        <button
                          type="button"
                          key={choice.key}
                          aria-label={`Chọn ${choice.title}`}
                          aria-pressed={selected}
                          disabled={searchMutation.isPending || holdSelectionMutation.isPending || cancelHoldMutation.isPending}
                          className={`min-h-36 rounded-lg border bg-card p-4 text-left transition ${
                            selected ? "border-primary ring-2 ring-primary/20" : "hover:border-primary/50"
                          }`}
                          onClick={() => chooseTables(choice.tableIds)}
                          data-testid={`customer-table-choice-${choice.key}`}
                        >
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <h3 className="font-semibold">{choice.title}</h3>
                              <p className="mt-1 text-sm text-muted-foreground">{choice.meta}</p>
                            </div>
                            <Badge variant={selected ? "default" : "outline"} className="rounded-md">
                              {selected ? "Đã chọn" : choice.statusLabel}
                            </Badge>
                          </div>
                          <p className="mt-3 text-sm text-muted-foreground">{choice.detail}</p>
                          <div className="mt-4 flex items-center gap-2 text-sm font-medium">
                            {pending ? (
                              <>
                                <CalendarClock className="h-4 w-4" />
                                Đang chọn bàn
                              </>
                            ) : selected && holdState?.isActive ? (
                              <>
                                <CheckCircle2 className="h-4 w-4 text-primary" />
                                Đã chọn thành công
                              </>
                            ) : (
                              <>
                                <Users className="h-4 w-4" />
                                Chọn bàn này
                              </>
                            )}
                          </div>
                        </button>
                      );
                    })}
                  </div>
                </section>
              ))}
            </div>
          ) : null}

          {activeHoldSessionError && !holdState?.isActive ? (
            <Alert variant="destructive" className="rounded-lg border-destructive/30 bg-destructive/5">
              <AlertTriangle className="h-4 w-4" />
              <AlertTitle>Phiên đặt bàn đang có lượt giữ khác</AlertTitle>
              <AlertDescription className="mt-2 space-y-3">
                <p>{ACTIVE_TABLE_HOLD_SESSION_MESSAGE}</p>
                <Button type="button" variant="outline" size="sm" className="w-fit rounded-lg bg-background" onClick={() => window.location.reload()}>
                  <RefreshCw className="mr-2 h-4 w-4" />
                  Tải lại phiên đặt bàn
                </Button>
              </AlertDescription>
            </Alert>
          ) : null}

          {/* Ẩn các hộp ErrorState của Cancel và Refresh nếu Hold đã bị tự động dọn dẹp do 422 */}
          {refreshHoldMutation.error && holdState?.isActive ? (
            <ErrorState
              error={refreshHoldMutation.error}
              title={getErrorMessage(refreshHoldMutation.error, "Bàn đã chọn cần được kiểm tra lại")}
              onRetry={() => searchMutation.mutate(form.getValues())}
            />
          ) : null}

          {cancelHoldMutation.error && holdState?.isActive ? (
            <ErrorState
              error={cancelHoldMutation.error}
              title={getErrorMessage(cancelHoldMutation.error, "Chưa hủy được giữ bàn")}
              onRetry={() => {
                if (holdState?.isActive) {
                  cancelHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion });
                }
              }}
            />
          ) : null}

          {hold && heldVisitDetails && holdState && !holdState.isActive ? (
             <EmptyState
               title="Bàn đã chọn không còn hiệu lực"
               description="Thời gian giữ bàn đã hết. Tìm lại bàn phù hợp trước khi xác nhận đặt bàn."
               action={
                 <Button type="button" className="rounded-lg" onClick={() => searchMutation.mutate(form.getValues())}>
                   <Search className="mr-2 h-4 w-4" />
                   Tìm bàn mới
                 </Button>
               }
             />
          ) : null}
        </section>

        <StickyBookingSummary
          items={bookingSummaryItems}
          holdCode={holdState?.holdId}
          holdExpiresAt={holdState?.expiresAt}
          holdStatusLabel={holdState?.statusLabel}
          primaryAction={
            reservationCreateHref ? (
              <Button asChild className="min-h-10 w-full rounded-lg" data-testid="customer-confirm-hold-btn">
                <Link href={reservationCreateHref}>Xác nhận thông tin đặt bàn</Link>
              </Button>
            ) : undefined
          }
          primaryActionDisabled={!availability || selectedTableIds.length === 0 || isHoldTransitionPending}
          primaryActionLabel={selectedTableIds.length > 0 ? "Xác nhận thông tin đặt bàn" : "Chọn bàn để tiếp tục đặt bàn"}
          onPrimaryAction={() => submitAvailabilitySearch(form.getValues())}
        />
      </div>
    </main>
  );
}
