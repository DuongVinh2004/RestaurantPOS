"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { queryKeys } from "@/lib/api/query-keys";
import { stringValue } from "@/lib/contracts/format";
import { clearReservationPreorder, getReservationPreorder } from "./api";

export function PreorderPanel({ reservationId, rowVersion }: { reservationId: number; rowVersion: number }) {
  const queryClient = useQueryClient();
  const preorderQuery = useQuery({
    queryKey: queryKeys.reservations.preorder(reservationId),
    queryFn: () => getReservationPreorder(reservationId),
  });
  const clearMutation = useMutation({
    mutationFn: () => clearReservationPreorder(reservationId, rowVersion),
    onSuccess() {
      queryClient.invalidateQueries({ queryKey: queryKeys.reservations.preorder(reservationId) });
    },
  });

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Preorder</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {preorderQuery.isLoading ? <LoadingBlock label="Loading preorder" /> : null}
        {preorderQuery.error ? <ErrorState error={preorderQuery.error} title="Preorder is unavailable" onRetry={() => preorderQuery.refetch()} /> : null}
        {preorderQuery.data ? (
          <>
            <div className="rounded-lg bg-secondary p-4 text-sm">
              <p className="font-medium">Current preorder</p>
              <p className="mt-1 text-muted-foreground">
                {stringValue(preorderQuery.data.data as Record<string, unknown>, ["status", "summary", "action"]) ?? "No preorder items returned."}
              </p>
            </div>
            <Button
              type="button"
              variant="outline"
              className="rounded-lg"
              disabled={clearMutation.isPending}
              onClick={() => clearMutation.mutate()}
            >
              {clearMutation.isPending ? "Clearing" : "Clear preorder"}
            </Button>
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}
