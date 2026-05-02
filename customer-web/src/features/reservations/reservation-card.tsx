import Link from "next/link";
import { CalendarDays, Users, WalletCards } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { StatusBadge } from "@/components/status/status-badge";
import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import { formatCustomerTableName } from "@/lib/i18n/customer-display";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";
import { getReservationBillSummaryState, getReservationDepositSummaryState } from "./state";

function tableListLabel(tableIds: number[] | null | undefined): string {
  return tableIds?.length
    ? tableIds.map((tableId) => formatCustomerTableName(null, null, tableId)).join(", ")
    : "Đang chờ";
}

export function ReservationCard({ reservation }: { reservation: ReservationSummary }) {
  const deposit = getReservationDepositSummaryState(reservation);
  const bill = getReservationBillSummaryState(reservation);

  return (
    <Card className="rounded-lg transition hover:border-primary/40 hover:shadow-sm">
      <CardContent className="space-y-4 p-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-sm text-muted-foreground">{reservation.reservation_code}</p>
            <h2 className="text-lg font-semibold">{formatDateTime(reservation.start_time ?? reservation.booking_time ?? null)}</h2>
          </div>
          <StatusBadge status={reservation.status} />
        </div>
        <div className="grid grid-cols-2 gap-3 text-sm">
          <div className="rounded-lg border bg-background/70 p-3">
            <p className="flex items-center gap-2 text-muted-foreground"><Users className="h-3.5 w-3.5" /> Số khách</p>
            <p className="mt-1 font-medium">{reservation.guest_count ?? "Chưa có"}</p>
          </div>
          <div className="rounded-lg border bg-background/70 p-3">
            <p className="flex items-center gap-2 text-muted-foreground"><WalletCards className="h-3.5 w-3.5" /> Đặt cọc</p>
            <p className="mt-1 font-medium">{deposit.label}</p>
          </div>
          <div className="rounded-lg border bg-background/70 p-3">
            <p className="flex items-center gap-2 text-muted-foreground"><WalletCards className="h-3.5 w-3.5" /> Hóa đơn</p>
            <p className="mt-1 font-medium">{bill.available ? formatMoney(bill.amount, bill.currency) : bill.label}</p>
          </div>
          <div className="rounded-lg border bg-background/70 p-3">
            <p className="flex items-center gap-2 text-muted-foreground"><CalendarDays className="h-3.5 w-3.5" /> Bàn</p>
            <p className="mt-1 font-medium">{tableListLabel(reservation.table_ids)}</p>
          </div>
        </div>
        <Button asChild className="w-full rounded-lg">
          <Link href={`/reservations/${reservation.reservation_id}`}>Mở chi tiết</Link>
        </Button>
      </CardContent>
    </Card>
  );
}
