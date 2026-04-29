"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
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
          <CardTitle>Dữ liệu cá nhân</CardTitle>
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
        <CardTitle>Công cụ dữ liệu cá nhân</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="rounded-lg border bg-secondary/30 p-4 text-sm">
          <p className="font-medium">Yêu cầu ẩn danh dữ liệu</p>
          <p className="mt-1 text-muted-foreground">
            Gửi yêu cầu này để nhà hàng xem xét ẩn danh dữ liệu hồ sơ khách hàng. Một số chứng từ tài chính, kiểm toán hoặc pháp lý vẫn có thể được lưu theo quy định.
          </p>
        </div>
        {privacyQuery.isLoading ? <LoadingBlock label="Đang tải yêu cầu dữ liệu" /> : null}
        {dataExportEnabled && exportQuery.isLoading ? <LoadingBlock label="Đang tải dữ liệu xuất" /> : null}
        {privacyQuery.error ? <ErrorState error={privacyQuery.error} title="Chưa tải được yêu cầu dữ liệu" onRetry={() => privacyQuery.refetch()} /> : null}
        {exportQuery.error ? <ErrorState error={exportQuery.error} title="Chưa tải được dữ liệu xuất" onRetry={() => exportQuery.refetch()} /> : null}

        {dataExportEnabled ? (
          <div className="rounded-lg bg-secondary p-4 text-sm">
            <p className="font-medium">Xuất dữ liệu</p>
            <p className="mt-1 text-muted-foreground">
              {exportQuery.data ? "Dữ liệu xuất mới nhất đang hiển thị tại đây." : "Đang tải dữ liệu xuất mới nhất."}
            </p>
          </div>
        ) : (
          <EmptyState
            title={dataExportRollout.disabledTitle}
            description={dataExportRollout.disabledDescription}
          />
        )}

        <div className="space-y-2">
          <Label htmlFor="privacy-request-note">Ghi chú tùy chọn</Label>
          <Textarea
            id="privacy-request-note"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            className="min-h-24 rounded-lg"
            placeholder="Ghi chú tùy chọn"
          />
          <Button type="button" className="rounded-lg" disabled={createMutation.isPending} onClick={() => createMutation.mutate()}>
            {createMutation.isPending ? "Đang gửi yêu cầu" : "Gửi yêu cầu ẩn danh dữ liệu"}
          </Button>
        </div>

        {createMutation.error ? <ErrorState error={createMutation.error} title="Chưa gửi được yêu cầu dữ liệu" /> : null}

        {privacyQuery.data?.length === 0 ? (
          <EmptyState title="Chưa có yêu cầu dữ liệu" description="Các yêu cầu bạn gửi sẽ hiển thị tại đây." />
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
