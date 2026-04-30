import Link from "next/link";
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
    <Card className="rounded-lg">
      <CardContent className="space-y-4 p-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-sm text-muted-foreground">{reservation.reservation_code}</p>
            <h2 className="text-lg font-semibold">{formatDateTime(reservation.start_time ?? reservation.booking_time ?? null)}</h2>
          </div>
          <StatusBadge status={reservation.status} />
        </div>
        <div className="grid grid-cols-2 gap-3 text-sm">
          <div>
            <p className="text-muted-foreground">Số khách</p>
            <p className="font-medium">{reservation.guest_count ?? "Chưa có"}</p>
          </div>
          <div>
            <p className="text-muted-foreground">Đặt cọc</p>
            <p className="font-medium">{deposit.label}</p>
          </div>
          <div>
            <p className="text-muted-foreground">Hóa đơn</p>
            <p className="font-medium">{bill.available ? formatMoney(bill.amount, bill.currency) : bill.label}</p>
          </div>
          <div>
            <p className="text-muted-foreground">Bàn</p>
            <p className="font-medium">{tableListLabel(reservation.table_ids)}</p>
          </div>
        </div>
        <Button asChild className="w-full rounded-lg">
          <Link href={`/reservations/${reservation.reservation_id}`}>Mở chi tiết</Link>
        </Button>
      </CardContent>
    </Card>
  );
}
