"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { StatusBadge } from "@/components/status/status-badge";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { queryKeys } from "@/lib/api/query-keys";
import { featureFlags } from "@/lib/config/feature-flags";
import { formatDateTime } from "@/lib/contracts/format";
import { createPrivacyRequest, getDataExport, listPrivacyRequests } from "./api";

export function PrivacyPanel() {
  const [reason, setReason] = useState("");
  const queryClient = useQueryClient();
  const privacyQuery = useQuery({
    queryKey: queryKeys.account.privacyRequests,
    queryFn: listPrivacyRequests,
    enabled: featureFlags.privacyTools,
  });
  const exportQuery = useQuery({
    queryKey: queryKeys.account.dataExport,
    queryFn: getDataExport,
    enabled: featureFlags.privacyTools && featureFlags.dataExport,
  });
  const createMutation = useMutation({
    mutationFn: () => createPrivacyRequest(reason),
    onSuccess() {
      setReason("");
      queryClient.invalidateQueries({ queryKey: queryKeys.account.privacyRequests });
    },
  });

  if (!featureFlags.privacyTools) {
    return null;
  }

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Privacy tools</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {privacyQuery.isLoading ? <LoadingBlock label="Loading privacy requests" /> : null}
        {privacyQuery.error ? <ErrorState error={privacyQuery.error} title="Privacy requests are unavailable" onRetry={() => privacyQuery.refetch()} /> : null}
        {exportQuery.error ? <ErrorState error={exportQuery.error} title="Data export is unavailable" onRetry={() => exportQuery.refetch()} /> : null}

        {featureFlags.dataExport ? (
          <div className="rounded-lg bg-secondary p-4 text-sm">
            <p className="font-medium">Data export</p>
            <p className="mt-1 text-muted-foreground">
              {exportQuery.data ? "Your export endpoint responded successfully." : "Refresh to request the current export payload."}
            </p>
          </div>
        ) : null}

        <div className="space-y-2">
          <Textarea
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            className="min-h-24 rounded-lg"
            placeholder="Optional reason"
          />
          <Button type="button" className="rounded-lg" disabled={createMutation.isPending} onClick={() => createMutation.mutate()}>
            {createMutation.isPending ? "Creating request" : "Create privacy request"}
          </Button>
        </div>

        {createMutation.error ? <ErrorState error={createMutation.error} title="Privacy request failed" /> : null}

        {privacyQuery.data?.data.length === 0 ? (
          <EmptyState title="No privacy requests" description="Requests you create will appear here." />
        ) : null}
        {privacyQuery.data?.data.map((request) => (
          <div key={request.customer_privacy_request_id} className="rounded-lg border p-4">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="font-medium">{request.request_type}</p>
                <p className="text-sm text-muted-foreground">{formatDateTime(request.requested_at)}</p>
              </div>
              <StatusBadge status={request.status} />
            </div>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
