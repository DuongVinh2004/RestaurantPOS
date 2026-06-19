import Link from "next/link";
import { ArrowRight, CalendarDays, ReceiptText, Users, WalletCards, type LucideIcon } from "lucide-react";
import { AppButton, AppCard, StatusPill, type StatusTone } from "@/components/customer/ui";
import { StatusBadge } from "@/components/status/status-badge";
import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import { formatCustomerTableName } from "@/lib/i18n/customer-display";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import { getReservationBillSummaryState, getReservationDepositSummaryState } from "./state";

function tableListLabel(reservation: ReservationSummary): string {
  const tableCodes = Array.isArray(reservation.table_summary?.table_codes)
    ? (reservation.table_summary.table_codes as string[])
    : null;
    
  const zones = Array.isArray(reservation.table_summary?.zones)
    ? (reservation.table_summary.zones as string[])
    : null;
  const primaryZone = zones && zones.length > 0 ? zones[0] : null;

  if (tableCodes && tableCodes.length > 0) {
    return tableCodes.map((code) => formatCustomerTableName(code, primaryZone)).join(", ");
  }

  const tableIds = reservation.table_ids;
  return tableIds?.length
    ? tableIds.map((tableId) => formatCustomerTableName(null, primaryZone, tableId)).join(", ")
    : "Đang chờ";
}

export function ReservationCard({ reservation }: { reservation: ReservationSummary }) {
  const deposit = getReservationDepositSummaryState(reservation);
  const bill = getReservationBillSummaryState(reservation);
  const scheduledAt = formatDateTime(reservation.start_time ?? reservation.booking_time ?? null);
  const billLabel = bill.available ? formatMoney(bill.amount, bill.currency) : bill.label;

  return (
    <AppCard className="group overflow-hidden transition hover:border-primary/40 hover:shadow-sm">
      <div className="h-1 bg-primary" />
      <div className="space-y-4 p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-sm text-muted-foreground">{reservation.reservation_code}</p>
            <h2 className="mt-1 text-lg font-semibold leading-tight">{scheduledAt}</h2>
          </div>
          <StatusBadge status={reservation.status} className="shrink-0" />
        </div>

        <div className="grid gap-3 text-sm sm:grid-cols-2">
          <ReservationSignal
            icon={Users}
            label="Số khách"
            value={reservation.guest_count ? `${reservation.guest_count} khách` : "Chưa có"}
          />
          <ReservationSignal
            icon={CalendarDays}
            label="Bàn"
            value={tableListLabel(reservation)}
          />
        </div>

        <div className="grid gap-2 text-sm">
          <div className="flex items-center justify-between gap-3 rounded-lg border bg-background px-3 py-2.5">
            <span className="inline-flex items-center gap-2 text-muted-foreground">
              <WalletCards className="h-3.5 w-3.5" />
              Đặt cọc
            </span>
            <StatusPill label={deposit.label} tone={depositTone(deposit.state, deposit.requiresAction)} className="max-w-[11rem] justify-center truncate" />
          </div>
          <div className="flex items-center justify-between gap-3 rounded-lg border bg-background px-3 py-2.5">
            <span className="inline-flex items-center gap-2 text-muted-foreground">
              <ReceiptText className="h-3.5 w-3.5" />
              Hóa đơn
            </span>
            <StatusPill label={billLabel} tone={billTone(bill.state)} className="max-w-[11rem] justify-center truncate" />
          </div>
        </div>

        <AppButton asChild className="w-full justify-between">
          <Link href={`/reservations/${reservation.reservation_id}`}>
            Mở chi tiết
            <ArrowRight className="h-4 w-4" />
          </Link>
        </AppButton>
      </div>
    </AppCard>
  );
}

function ReservationSignal({
  icon: Icon,
  label,
  value,
}: {
  icon: LucideIcon;
  label: string;
  value: string | number;
}) {
  return (
    <div className="min-h-20 rounded-lg border bg-secondary/30 p-3">
      <p className="flex items-center gap-2 text-muted-foreground">
        <Icon className="h-3.5 w-3.5" />
        {label}
      </p>
      <p className="mt-2 font-medium leading-snug">{value}</p>
    </div>
  );
}

function depositTone(state: ReturnType<typeof getReservationDepositSummaryState>["state"], requiresAction: boolean): StatusTone {
  if (requiresAction) {
    return "warning";
  }

  if (state === "paid" || state === "not_required") {
    return "success";
  }

  return "neutral";
}

function billTone(state: ReturnType<typeof getReservationBillSummaryState>["state"]): StatusTone {
  if (state === "available") {
    return "info";
  }

  if (state === "settled") {
    return "success";
  }

  return "neutral";
}
