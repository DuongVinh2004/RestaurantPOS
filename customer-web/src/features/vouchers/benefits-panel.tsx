"use client";

import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { featureFlags } from "@/lib/config/feature-flags";
import { queryKeys } from "@/lib/api/query-keys";
import { getBenefitsPreview } from "./api";

export function BenefitsPanel({ reservationId }: { reservationId: number }) {
  const benefitsQuery = useQuery({
    queryKey: queryKeys.reservations.benefits(reservationId),
    queryFn: () => getBenefitsPreview(reservationId),
    enabled: featureFlags.vouchers,
  });

  if (!featureFlags.vouchers) {
    return null;
  }

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Benefits</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {benefitsQuery.isLoading ? <LoadingBlock label="Loading benefits" /> : null}
        {benefitsQuery.error ? <ErrorState error={benefitsQuery.error} title="Benefits are unavailable" onRetry={() => benefitsQuery.refetch()} /> : null}
        {benefitsQuery.data ? (
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="rounded-lg bg-secondary p-4">
              <p className="text-sm text-muted-foreground">Available points</p>
              <p className="text-2xl font-semibold">{benefitsQuery.data.data.reservation.loyalty.available_points}</p>
            </div>
            <div className="rounded-lg bg-secondary p-4">
              <p className="text-sm text-muted-foreground">Vouchers</p>
              <p className="text-2xl font-semibold">{benefitsQuery.data.data.available_vouchers.length}</p>
            </div>
            {benefitsQuery.data.data.available_vouchers.map((voucher) => (
              <div key={voucher.user_voucher_id} className="rounded-lg border p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-medium">{voucher.voucher_code}</p>
                    <p className="text-sm text-muted-foreground">{voucher.description}</p>
                  </div>
                  <Badge variant="outline" className="rounded-md">{voucher.current_status}</Badge>
                </div>
              </div>
            ))}
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}
