"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { AppButton, AppCard, AppTextarea, EmptyState, ErrorState, SectionHeader, StatusPill } from "@/components/customer/ui";
import { LoadingBlock } from "@/components/states/state-blocks";
import { StatusBadge } from "@/components/status/status-badge";
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
      <AppCard className="p-4">
        <EmptyState title={privacyRollout.disabledTitle} description={privacyRollout.disabledDescription} />
      </AppCard>
    );
  }

  return (
    <AppCard className="p-4">
      <div className="space-y-4">
        <SectionHeader
          eyebrow="Dữ liệu cá nhân"
          title="Trung tâm quyền riêng tư"
          description="Xem yêu cầu dữ liệu cá nhân và gửi ghi chú để nhà hàng hỗ trợ quyền riêng tư của bạn."
          action={<StatusPill label="Theo khách hàng" tone="info" />}
        />

        <div className="rounded-lg border bg-secondary/30 p-4 text-sm">
          <p className="font-medium">Yêu cầu ẩn danh hóa</p>
          <p className="mt-1 text-muted-foreground">
            Yêu cầu này đề nghị nhà hàng xem xét ẩn danh hóa dữ liệu định danh khách hàng. Một số thông tin giao dịch có thể được giữ lại khi nhà hàng cần bảo toàn lịch sử tài chính.
          </p>
        </div>

        {privacyQuery.isLoading ? <LoadingBlock label="Đang tải yêu cầu dữ liệu cá nhân" /> : null}
        {dataExportEnabled && exportQuery.isLoading ? <LoadingBlock label="Đang tải dữ liệu xuất" /> : null}
        {privacyQuery.error ? (
          <ErrorState error={privacyQuery.error} title="Chưa tải được yêu cầu dữ liệu cá nhân" onRetry={() => privacyQuery.refetch()} />
        ) : null}
        {exportQuery.error ? (
          <ErrorState error={exportQuery.error} title="Chưa tải được dữ liệu xuất" onRetry={() => exportQuery.refetch()} />
        ) : null}

        {dataExportEnabled ? (
          <div className="rounded-lg bg-secondary p-4 text-sm">
            <p className="font-medium">Tóm tắt dữ liệu đang lưu</p>
            <p className="mt-1 text-muted-foreground">
              {exportQuery.data
                ? "Bản tóm tắt dữ liệu khách hàng mới nhất đã sẵn sàng."
                : "Bản tóm tắt dữ liệu sẽ hiển thị sau khi nhà hàng xử lý."}
            </p>
            {exportQuery.data ? (
              <p className="mt-2 text-xs text-muted-foreground">
                Bản tóm tắt gồm thông tin tài khoản, lịch đặt, thanh toán, điểm thưởng, chờ bàn, hội thoại và yêu cầu dữ liệu cá nhân khi có sẵn.
              </p>
            ) : null}
          </div>
        ) : (
          <EmptyState title={dataExportRollout.disabledTitle} description={dataExportRollout.disabledDescription} />
        )}

        <div className="space-y-3">
          <AppTextarea
            label="Ghi chú tùy chọn"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            placeholder="Thêm ngữ cảnh cho yêu cầu dữ liệu cá nhân."
          />
          <AppButton type="button" disabled={createMutation.isPending} onClick={() => createMutation.mutate()}>
            {createMutation.isPending ? "Đang gửi yêu cầu" : "Yêu cầu xem xét ẩn danh hóa"}
          </AppButton>
        </div>

        {createMutation.error ? <ErrorState error={createMutation.error} title="Chưa gửi được yêu cầu dữ liệu cá nhân" /> : null}

        {privacyQuery.data?.length === 0 ? (
          <EmptyState title="Chưa có yêu cầu dữ liệu cá nhân" description="Yêu cầu bạn gửi sẽ hiển thị tại đây." />
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
            {request.result_summary ? <p className="mt-2 text-sm text-muted-foreground">{request.result_summary}</p> : null}
          </div>
        ))}
      </div>
    </AppCard>
  );
}
