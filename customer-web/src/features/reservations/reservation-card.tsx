import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { StatusBadge } from "@/components/status/status-badge";
import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import type { ReservationSummary } from "@/lib/contracts/generated/restaurantpos-sdk";

export function ReservationCard({ reservation }: { reservation: ReservationSummary }) {
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
            <p className="text-muted-foreground">Guests</p>
            <p className="font-medium">{reservation.guest_count ?? "Not set"}</p>
          </div>
          <div>
            <p className="text-muted-foreground">Deposit</p>
            <p className="font-medium">{reservation.deposit_status ?? "Not required"}</p>
          </div>
          <div>
            <p className="text-muted-foreground">Bill</p>
            <p className="font-medium">{formatMoney(reservation.final_bill_amount, reservation.bill_currency ?? "USD")}</p>
          </div>
          <div>
            <p className="text-muted-foreground">Tables</p>
            <p className="font-medium">{reservation.table_ids?.join(", ") || "Pending"}</p>
          </div>
        </div>
        <Button asChild className="w-full rounded-lg">
          <Link href={`/reservations/${reservation.reservation_id}`}>Open visit</Link>
        </Button>
      </CardContent>
    </Card>
  );
}
