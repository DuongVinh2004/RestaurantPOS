"use client";

import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, WifiOff } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { getApiErrorDisplay, normalizeApiError } from "@/lib/api/errors";
import { customerWebRollout, featureFlags } from "@/lib/config/feature-flags";
import { getApiBaseUrlRuntimeDiagnostics, publicEnvDiagnostics } from "@/lib/config/env";
import { queryKeys } from "@/lib/api/query-keys";
import { checkBackendHealth, getApiRuntimeDiagnostics } from "@/lib/api/sdk-client";

type BackendStatusBannerState = {
  tone: "warning" | "danger";
  title: string;
  message: string;
  guidance: string[];
  showRetry: boolean;
};

export function BackendStatusBanner() {
  const diagnostics = getApiRuntimeDiagnostics();
  const healthQuery = useQuery({
    queryKey: queryKeys.backend.health,
    queryFn: () => checkBackendHealth(),
    enabled: featureFlags.showDevBackendStatus,
    refetchOnWindowFocus: false,
    retry: false,
  });

  if (!featureFlags.showDevBackendStatus) {
    return null;
  }

  const appHost = typeof window === "undefined" ? null : window.location.hostname;
  const bannerState = resolveBackendStatusBannerState({
    appHost,
    baseUrl: diagnostics.baseUrl,
    usingDevMocks: diagnostics.usingDevMocks,
    healthResult: healthQuery.data,
    healthError: healthQuery.error,
  });

  if (!bannerState && (healthQuery.isLoading || healthQuery.data?.ok)) {
    return null;
  }

  if (!bannerState) {
    return null;
  }

  const normalizedError = healthQuery.error ? normalizeApiError(healthQuery.error) : null;
  const errorDisplay = healthQuery.error ? getApiErrorDisplay(healthQuery.error) : null;
  const healthStatus = healthQuery.data?.status ?? normalizedError?.status ?? null;
  const statusLabel = healthStatus === null ? null : `Trạng thái ${healthStatus}`;
  const requestIdLabel = healthQuery.data?.requestId ? `Mã hỗ trợ: ${healthQuery.data.requestId}` : errorDisplay?.requestIdLabel ?? null;
  const checkedUrl = healthQuery.data?.checkedUrl ?? `${diagnostics.baseUrl.replace(/\/+$/, "")}/api/v1/health`;
  const alertClassName =
    bannerState.tone === "danger"
      ? "rounded-none border-x-0 border-t-0 border-rose-200 bg-rose-50 text-rose-950"
      : "rounded-none border-x-0 border-t-0 bg-amber-50 text-amber-950";
  const Icon = bannerState.tone === "danger" ? WifiOff : AlertTriangle;

  return (
    <Alert className={alertClassName}>
      <Icon className="h-4 w-4" />
      <AlertTitle>{bannerState.title}</AlertTitle>
      <AlertDescription className="flex flex-col gap-3">
        <div className="space-y-2">
          <p>{bannerState.message}</p>
          <div className="space-y-1 text-sm">
            {bannerState.guidance.map((item) => (
              <p key={item}>{item}</p>
            ))}
          </div>
          <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium">
            <span>{diagnostics.usingDevMocks ? "Chế độ: dữ liệu mô phỏng" : "Chế độ: gọi API"}</span>
            <span className="font-mono">{checkedUrl}</span>
            {statusLabel ? <span>{statusLabel}</span> : null}
            {requestIdLabel ? <span>{requestIdLabel}</span> : null}
          </div>
        </div>
        {bannerState.showRetry ? (
          <Button type="button" variant="outline" size="sm" className="w-fit rounded-lg" onClick={() => healthQuery.refetch()}>
            Thử lại
          </Button>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}

export function resolveBackendStatusBannerState({
  appHost,
  baseUrl,
  usingDevMocks,
  healthResult,
  healthError,
}: {
  appHost: string | null;
  baseUrl: string;
  usingDevMocks: boolean;
  healthResult: Awaited<ReturnType<typeof checkBackendHealth>> | undefined;
  healthError: unknown;
}): BackendStatusBannerState | null {
  const baseUrlDiagnostics = getApiBaseUrlRuntimeDiagnostics(baseUrl, appHost);
  const aliasWarnings = publicEnvDiagnostics.rolloutFlagsUsingAliases;
  const normalizedError = healthError ? normalizeApiError(healthError) : null;
  const errorDisplay = healthError ? getApiErrorDisplay(healthError) : null;

  if (usingDevMocks) {
    return {
      tone: "warning",
      title: "Đang dùng dữ liệu mô phỏng",
      message:
        "Trình duyệt đang dùng dữ liệu mô phỏng thay vì API thật. Chỉ dùng chế độ này để kiểm thử giao diện.",
      guidance: [
        "Tắt dữ liệu mô phỏng trước khi kiểm thử với API thật.",
        customerWebRollout.devMockAdapter.liveProofSummary,
      ],
      showRetry: false,
    };
  }

  if (baseUrlDiagnostics.likelyWrongForCurrentHost) {
    return {
      tone: "warning",
      title: "Địa chỉ API có thể chưa đúng",
      message: `Ứng dụng đang chạy trên ${baseUrlDiagnostics.appHost}, nhưng địa chỉ API đang trỏ tới ${baseUrl}. API cục bộ chỉ hoạt động khi trình duyệt và Laravel chạy trên cùng máy.`,
      guidance: [
        "Cập nhật địa chỉ API đúng cho môi trường này rồi tải lại ứng dụng.",
        "Cảnh báo này chỉ kiểm tra kết nối cơ bản.",
      ],
      showRetry: Boolean(healthError || (healthResult && !healthResult.ok)),
    };
  }

  if (healthResult && !healthResult.ok) {
    return {
      tone: "danger",
      title: "Chưa kết nối được API",
      message:
        `${errorDisplay?.message ?? "Kiểm tra hệ thống nhà hàng thất bại."} ${
          errorDisplay?.retryHint ?? "Kiểm tra API đang chạy và có thể truy cập từ môi trường này."
        }`.trim(),
      guidance: [
        "Kiểm tra địa chỉ API trước, sau đó xác nhận hệ thống nhà hàng đang hoạt động.",
        "Trình duyệt chỉ kiểm tra đường dẫn sức khỏe, trạng thái phản hồi và mã hỗ trợ.",
      ],
      showRetry: true,
    };
  }

  if (normalizedError) {
    return {
      tone: "danger",
      title: "Kiểm tra API thất bại",
      message:
        `${errorDisplay?.message ?? normalizedError.message} ${
          errorDisplay?.retryHint ?? "Kiểm tra hệ thống nhà hàng có thể truy cập từ môi trường này."
        }`.trim(),
      guidance: [
        "Kiểm tra địa chỉ API trước, sau đó xác nhận hệ thống nhà hàng đang hoạt động.",
        "Cảnh báo này không thay thế kiểm thử vận hành đầy đủ.",
      ],
      showRetry: true,
    };
  }

  if (aliasWarnings.length > 0) {
    return {
      tone: "warning",
      title: "Đang dùng tên cấu hình cũ",
      message: `Bản dựng vẫn đọc ${aliasWarnings.join(", ")}. Kiểm thử vẫn có thể tiếp tục, nhưng cấu hình mới nên dùng tên NEXT_PUBLIC_FEATURE_* để dễ kiểm soát.`,
      guidance: [
        "Tên cấu hình cũ không mở rộng phạm vi hỗ trợ.",
        "Đây là cảnh báo cấu hình, không phải lỗi thao tác của khách hàng.",
      ],
      showRetry: false,
    };
  }

  return null;
}
