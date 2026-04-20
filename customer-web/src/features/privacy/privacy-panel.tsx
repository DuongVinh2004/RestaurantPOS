"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
import { StatusBadge } from "@/components/status/status-badge";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { queryKeys } from "@/lib/api/query-keys";
import { customerWebRollout } from "@/lib/config/feature-flags";
import { formatDateTime } from "@/lib/contracts/format";
import { createPrivacyRequest, getDataExport, listPrivacyRequests } from "./api";

export function PrivacyPanel() {
  const [reason, setReason] = useState("");
  const queryClient = useQueryClient();
  const privacyRollout = customerWebRollout.privacyRequests;
  const dataExportRollout = customerWebRollout.dataExport;
  const privacyEnabled = privacyRollout.enabled;
  const dataExportEnabled = privacyEnabled && dataExportRollout.enabled;
  const privacyQuery = useQuery({
    queryKey: queryKeys.account.privacyRequests,
    queryFn: listPrivacyRequests,
    enabled: privacyEnabled,
  });
  const exportQuery = useQuery({
    queryKey: queryKeys.account.dataExport,
    queryFn: getDataExport,
    enabled: dataExportEnabled,
  });
  const createMutation = useMutation({
    mutationFn: () => createPrivacyRequest(reason),
    onSuccess() {
      setReason("");
      queryClient.invalidateQueries({ queryKey: queryKeys.account.privacyRequests });
    },
  });

  if (!privacyEnabled) {
    return (
      <Card className="rounded-lg">
        <CardHeader>
          <CardTitle>Privacy and data export</CardTitle>
        </CardHeader>
        <CardContent>
          <EmptyState
            title={privacyRollout.disabledTitle}
            description={privacyRollout.disabledDescription}
          />
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="rounded-lg">
      <CardHeader>
        <CardTitle>Privacy tools</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="rounded-lg border bg-secondary/30 p-4 text-sm">
          <p className="font-medium">Anonymization request lifecycle</p>
          <p className="mt-1 text-muted-foreground">
            Submitting a privacy request asks the restaurant to anonymize customer profile data. After staff processing starts, the request may be irreversible while finance, audit, and legal records can remain retained under policy.
          </p>
        </div>
        {privacyQuery.isLoading ? <LoadingBlock label="Loading privacy requests" /> : null}
        {dataExportEnabled && exportQuery.isLoading ? <LoadingBlock label="Loading data export" /> : null}
        {privacyQuery.error ? <ErrorState error={privacyQuery.error} title="Privacy requests are unavailable" onRetry={() => privacyQuery.refetch()} /> : null}
        {exportQuery.error ? <ErrorState error={exportQuery.error} title="Data export is unavailable" onRetry={() => exportQuery.refetch()} /> : null}

        {dataExportEnabled ? (
          <div className="rounded-lg bg-secondary p-4 text-sm">
            <p className="font-medium">Data export</p>
            <p className="mt-1 text-muted-foreground">
              {exportQuery.data ? "Your latest export data is available for this rollout." : "We are loading the latest export data."}
            </p>
          </div>
        ) : (
          <EmptyState
            title={dataExportRollout.disabledTitle}
            description={dataExportRollout.disabledDescription}
          />
        )}

        <div className="space-y-2">
          <Textarea
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            className="min-h-24 rounded-lg"
            placeholder="Optional note"
          />
          <Button type="button" className="rounded-lg" disabled={createMutation.isPending} onClick={() => createMutation.mutate()}>
            {createMutation.isPending ? "Submitting request" : "Submit anonymization request"}
          </Button>
        </div>

        {createMutation.error ? <ErrorState error={createMutation.error} title="Privacy request failed" /> : null}

        {privacyQuery.data?.length === 0 ? (
          <EmptyState title="No privacy requests yet" description="Requests you submit will appear here." />
        ) : null}
        {privacyQuery.data?.map((request) => (
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
