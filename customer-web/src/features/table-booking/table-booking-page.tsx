"use client";

import Link from "next/link";
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
import { TimeSlotGrid, selectedDateFromLocalDateTime } from "@/components/booking/time-slot-grid";
import { ErrorState, EmptyState, LoadingBlock } from "@/components/states/state-blocks";
import type { RestaurantTable, TableHold } from "@/lib/contracts/generated/restaurantpos-sdk";
import { createRoundedFutureLocalDateTimeInput, formatLocalDateTimeInput } from "@/lib/contracts/datetime";
import { formatDateTime } from "@/lib/contracts/format";
import { queryKeys } from "@/lib/api/query-keys";
import { ACTIVE_TABLE_HOLD_SESSION_MESSAGE, isActiveTableHoldSessionError } from "@/lib/api/errors";
import { ensureCustomerSessionId } from "@/lib/auth/storage";
import {
  formatCustomerTableName,
  formatCustomerZone,
  translateCustomerStatus,
} from "@/lib/i18n/customer-display";
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

function tableIdKey(tableIds: number[]): string {
  return [...new Set(tableIds)].sort((left, right) => left - right).join(",");
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

export function TableBookingPage() {
  const queryClient = useQueryClient();
  const [initialSession] = useState(() => {
    const sessionId = ensureCustomerSessionId();
    const snapshot = readStoredActiveTableHoldSnapshot(sessionId);

    return { sessionId, snapshot };
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
  const [activeHoldNotice, setActiveHoldNotice] = useState<string | null>(
    initialSession.snapshot ? ACTIVE_TABLE_HOLD_SESSION_MESSAGE : null,
  );
  const form = useForm<AvailabilitySearchValues>({
    resolver: zodResolver(availabilitySearchSchema),
    defaultValues: {
      start_time: createRoundedFutureLocalDateTimeInput(),
      duration_minutes: 90,
      guest_count: 2,
    },
  });
  const startTimeValue = useWatch({ control: form.control, name: "start_time" });

  const holdState = hold ? parseTableHoldState(hold) : null;

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
    setActiveHoldNotice(null);
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

  const searchMutation = useMutation({
    mutationFn: (values: AvailabilitySearchValues) => searchAvailableTables(values),
    onMutate() {
      setAvailability(null);
      setAvailabilitySearchValues(null);
      if (!holdState?.isActive) {
        setSelectedTableIds([]);
      }
    },
    onSuccess(result, values) {
      setAvailability(result);
      setAvailabilitySearchValues(values);
    },
  });

  const holdSelectionMutation = useMutation({
    mutationFn: ({ values, tableIds }: HoldSelection) => createTableHold(values, tableIds),
    onSuccess(result, selection) {
      applyActiveHold(result, selection.values, selection.tableIds);
      toast.success("Đã chọn bàn cho lượt đặt.");
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
  });

  const cancelHoldMutation = useMutation({
    mutationFn: ({ holdId, rowVersion }: { holdId: string; rowVersion: number }) => cancelTableHold(holdId, rowVersion),
    onSuccess() {
      setHold(null);
      setHeldVisitDetails(null);
      setHeldTableIds([]);
      setSelectedTableIds([]);
      setActiveHoldNotice(null);
      clearStoredActiveTableHoldSnapshot(customerSessionId);
      toast.success("Đã hủy giữ bàn.");
    },
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
  const pendingSelectionKey = holdSelectionMutation.variables ? tableIdKey(holdSelectionMutation.variables.tableIds) : "";
  const selectedDate = selectedDateFromLocalDateTime(startTimeValue);
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
  const reservationCreateHref = holdState?.isActive && heldVisitDetails
    ? `/reservations/new?hold_id=${encodeURIComponent(holdState.holdId)}&hold_status=${encodeURIComponent(holdState.status)}&hold_expires_at=${encodeURIComponent(holdState.expiresAt ?? "")}&tables=${heldTableIds.join(",")}&start_time=${encodeURIComponent(heldVisitDetails.start_time)}&duration_minutes=${heldVisitDetails.duration_minutes}&guest_count=${heldVisitDetails.guest_count}`
    : null;
  const activeHoldSessionError = Boolean(holdSelectionMutation.error && isActiveTableHoldSessionError(holdSelectionMutation.error));

  useEffect(() => {
    if (!holdState?.isActive) {
      clearStoredActiveTableHoldSnapshot(customerSessionId);
    }
  }, [customerSessionId, holdState?.isActive]);

  useEffect(() => {
    if (!holdState?.isActive || !holdState.expiresAt || refreshHoldMutation.isPending) {
      return;
    }

    const expiresAtMs = Date.parse(holdState.expiresAt);
    if (!Number.isFinite(expiresAtMs)) {
      return;
    }

    const delayMs = Math.max(1000, expiresAtMs - Date.now() - 30_000);
    const timer = window.setTimeout(() => {
      refreshHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion });
    }, delayMs);

    return () => window.clearTimeout(timer);
  }, [holdState?.expiresAt, holdState?.holdId, holdState?.isActive, holdState?.rowVersion, refreshHoldMutation]);

  function chooseTables(tableIds: number[]) {
    const values = availabilitySearchValues ?? form.getValues();
    const selectionKey = tableIdKey(tableIds);
    const storedActiveHold = readStoredActiveTableHoldSnapshot(customerSessionId);

    if (storedActiveHold && (!holdState?.isActive || holdState.holdId !== storedActiveHold.hold_id)) {
      const restoredVisitDetailsFromStorage = visitDetailsFromSnapshot(storedActiveHold);

      setHold(tableHoldFromSnapshot(storedActiveHold));
      setHeldVisitDetails(restoredVisitDetailsFromStorage);
      setHeldTableIds(storedActiveHold.table_ids);
      setSelectedTableIds(storedActiveHold.table_ids);
      setActiveHoldNotice(ACTIVE_TABLE_HOLD_SESSION_MESSAGE);
      return;
    }

    if (holdState?.isActive) {
      setSelectedTableIds(heldTableIds);
      setActiveHoldNotice(ACTIVE_TABLE_HOLD_SESSION_MESSAGE);
      return;
    }

    if (holdMatchesSelection(holdState, heldVisitDetails, heldTableIds, values, tableIds)) {
      setSelectedTableIds(tableIds);
      return;
    }

    if (holdSelectionMutation.isPending || holdCreateInFlightKeyRef.current === selectionKey) {
      return;
    }

    holdCreateInFlightKeyRef.current = selectionKey;
    setSelectedTableIds(tableIds);
    holdSelectionMutation.mutate({
      values,
      tableIds,
    });
  }

  return (
    <main className="mx-auto w-full max-w-6xl px-4 py-6 pb-28 lg:pb-8">
      <section className="mb-6 space-y-3">
        <Badge variant="outline" className="rounded-md">Đặt bàn</Badge>
        <h1 className="text-4xl font-semibold tracking-normal">Tìm bàn phù hợp</h1>
        <p className="max-w-xl text-muted-foreground">
          Chọn thời gian và số khách. Bàn phù hợp sẽ được tạm giữ khi bạn chọn để tiếp tục đặt chỗ.
        </p>
        <BookingProgress currentStep={currentStep} />
      </section>

      <div className="grid gap-5 xl:grid-cols-[320px_minmax(0,1fr)_320px]">
        <Card className="h-fit rounded-lg">
          <CardHeader>
            <CardTitle>Thông tin lượt ghé</CardTitle>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={form.handleSubmit((values) => searchMutation.mutate(values))}>
              <div className="space-y-2">
                <Label htmlFor="start_time">Ngày và giờ</Label>
                <Input id="start_time" type="datetime-local" className="min-h-11 rounded-lg" {...form.register("start_time")} />
                {form.formState.errors.start_time ? <p className="text-sm text-destructive">{form.formState.errors.start_time.message}</p> : null}
              </div>
              <TimeSlotGrid
                selectedDate={selectedDate}
                selectedValue={startTimeValue}
                onSelect={(nextValue) => form.setValue("start_time", nextValue, { shouldDirty: true, shouldValidate: true })}
              />
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="guest_count">Số khách</Label>
                  <Input id="guest_count" type="number" min={1} className="min-h-11 rounded-lg" {...form.register("guest_count", { valueAsNumber: true })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="duration_minutes">Thời lượng</Label>
                  <Input id="duration_minutes" type="number" min={30} className="min-h-11 rounded-lg" {...form.register("duration_minutes", { valueAsNumber: true })} />
                </div>
              </div>
              <Button type="submit" className="min-h-11 w-full rounded-lg" disabled={searchMutation.isPending || holdSelectionMutation.isPending}>
                <Search className="mr-2 h-4 w-4" />
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
              title="Chưa tìm được bàn trống"
              onRetry={() => searchMutation.mutate(form.getValues())}
            />
          ) : null}

          {availability && tables.length === 0 ? (
            <EmptyState title="Chưa có bàn trống" description="Thử khung giờ khác hoặc giảm số khách." />
          ) : null}
          {availabilityMeta ? (
            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
              <span>Đang hiển thị {availabilityMeta.count} bàn phù hợp</span>
              <span>theo múi giờ {availabilityMeta.branchTimezone ?? availabilityMeta.timezone ?? "của nhà hàng"}.</span>
            </div>
          ) : null}

          {activeHoldNotice && holdState?.isActive ? (
            <Alert className="rounded-lg border-amber-200 bg-amber-50 text-amber-950">
              <AlertTriangle className="h-4 w-4" />
              <AlertTitle>Đang có lượt giữ bàn</AlertTitle>
              <AlertDescription>{activeHoldNotice}</AlertDescription>
            </Alert>
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
                      const pending = holdSelectionMutation.isPending && pendingSelectionKey === choice.key;

                      return (
                        <button
                          type="button"
                          key={choice.key}
                          aria-label={`Chọn ${choice.title}`}
                          aria-pressed={selected}
                          disabled={searchMutation.isPending || holdSelectionMutation.isPending}
                          className={`min-h-36 rounded-lg border bg-card p-4 text-left transition ${
                            selected ? "border-primary ring-2 ring-primary/20" : "hover:border-primary/50"
                          }`}
                          onClick={() => chooseTables(choice.tableIds)}
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
                                Đang giữ bàn
                              </>
                            ) : selected && holdState?.isActive ? (
                              <>
                                <CheckCircle2 className="h-4 w-4 text-primary" />
                                Sẵn sàng tiếp tục
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
          ) : holdSelectionMutation.error ? (
            <ErrorState
              error={holdSelectionMutation.error}
              title="Chưa chọn được bàn này"
              onRetry={() => {
                if (selectedTableIds.length > 0) {
                  chooseTables(selectedTableIds);
                }
              }}
            />
          ) : null}

          {refreshHoldMutation.error ? (
            <ErrorState
              error={refreshHoldMutation.error}
              title="Bàn đã chọn cần được kiểm tra lại"
              onRetry={() => searchMutation.mutate(form.getValues())}
            />
          ) : null}

          {cancelHoldMutation.error ? (
            <ErrorState
              error={cancelHoldMutation.error}
              title="Chưa hủy được giữ bàn"
              onRetry={() => {
                if (holdState?.isActive) {
                  cancelHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion });
                }
              }}
            />
          ) : null}

          {hold && heldVisitDetails && holdState ? (
            <Card className="rounded-lg border-primary">
              <CardContent className="space-y-4 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold">Bàn đã chọn</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                      {heldVisitDetails.guest_count} khách, {heldVisitDetails.duration_minutes} phút, bắt đầu{" "}
                      {formatDateTime(new Date(heldVisitDetails.start_time).toISOString())}
                    </p>
                    {holdState.expiresAt ? (
                      <p className="mt-1 text-sm text-muted-foreground">Giữ tạm đến {formatDateTime(holdState.expiresAt)}</p>
                    ) : null}
                  </div>
                  <Badge variant="outline" className="rounded-md">{holdState.statusLabel}</Badge>
                </div>

                {holdState.isActive && reservationCreateHref ? (
                  <Button asChild className="min-h-11 w-full rounded-lg">
                    <Link href={reservationCreateHref}>
                      <CheckCircle2 className="mr-2 h-4 w-4" />
                      Tiếp tục đặt chỗ
                    </Link>
                  </Button>
                ) : (
                  <EmptyState
                    title="Bàn đã chọn không còn hiệu lực"
                    description="Tìm lại bàn phù hợp trước khi tạo lịch đặt."
                    action={
                      <Button type="button" className="rounded-lg" onClick={() => searchMutation.mutate(form.getValues())}>
                        <Search className="mr-2 h-4 w-4" />
                        Tìm lại
                      </Button>
                    }
                  />
                )}
              </CardContent>
            </Card>
          ) : null}
        </section>

        <StickyBookingSummary
          items={bookingSummaryItems}
          holdCode={holdState?.holdId}
          holdExpiresAt={holdState?.expiresAt}
          holdStatusLabel={holdState?.statusLabel}
          primaryAction={
            reservationCreateHref ? (
              <Button asChild className="min-h-11 w-full rounded-lg">
                <Link href={reservationCreateHref}>Tiếp tục đặt bàn</Link>
              </Button>
            ) : undefined
          }
          primaryActionDisabled={!availability || selectedTableIds.length === 0 || holdSelectionMutation.isPending}
          primaryActionLabel={selectedTableIds.length > 0 ? "Đang giữ bàn" : "Chọn bàn để tiếp tục"}
          onPrimaryAction={() => searchMutation.mutate(form.getValues())}
          onRefreshHold={
            holdState?.isActive
              ? () => refreshHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion })
              : undefined
          }
          refreshPending={refreshHoldMutation.isPending}
          onCancelHold={
            holdState?.isActive
              ? () => cancelHoldMutation.mutate({ holdId: holdState.holdId, rowVersion: holdState.rowVersion })
              : undefined
          }
          cancelPending={cancelHoldMutation.isPending}
        />
      </div>
    </main>
  );
}
