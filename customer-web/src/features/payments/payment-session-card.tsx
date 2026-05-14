"use client";

import Link from "next/link";
import { AlertTriangle, CheckCircle2, Clock3, ExternalLink, RefreshCw } from "lucide-react";
import { AppButton, AppCard, StatusPill } from "@/components/customer/ui";
import { StatusBadge } from "@/components/status/status-badge";
import { asRecord, stringValue } from "@/lib/contracts/loose";
import { formatDateTime, formatMoney } from "@/lib/contracts/format";
import type { CustomerBillPaymentSession, CustomerDepositPaymentSession } from "@/lib/contracts/generated/restaurantpos-sdk";
import type { PaymentSessionPolicy } from "@/features/reservations/state";

type PaymentSessionCardProps = {
  surfaceLabel: string;
  session: CustomerDepositPaymentSession | CustomerBillPaymentSession;
  policy: PaymentSessionPolicy;
  refreshPending: boolean;
  confirmPending: boolean;
  onRefresh: () => void;
  onConfirm: () => void;
};

function SessionTile({
  eyebrow,
  title,
  description,
  status,
}: {
  eyebrow: string;
  title: string;
  description: string;
  status?: string | null;
}) {
  return (
    <div className="rounded-lg bg-secondary/50 p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm text-muted-foreground">{eyebrow}</p>
          <p className="mt-1 font-semibold">{title}</p>
          <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
        {status ? <StatusBadge status={status} /> : null}
      </div>
    </div>
  );
}

function SessionMeta({
  label,
  value,
}: {
  label: string;
  value: string | null;
}) {
  if (!value) {
    return null;
  }

  return (
    <div className="rounded-lg border border-dashed p-3">
      <p className="text-xs font-medium uppercase text-muted-foreground">{label}</p>
      <p className="mt-1 break-words text-sm">{value}</p>
    </div>
  );
}

export function PaymentSessionCard({
  surfaceLabel,
  session,
  policy,
  refreshPending,
  confirmPending,
  onRefresh,
  onConfirm,
}: PaymentSessionCardProps) {
  const amountLabel = session.amount ? formatMoney(session.amount, session.currency ?? "USD") : null;
  const expiryLabel = session.provider_expires_at ? formatDateTime(session.provider_expires_at) : null;
  const lastCheckedLabel = session.last_reconciled_at ? formatDateTime(session.last_reconciled_at) : null;
  const actionPending = refreshPending || confirmPending;
  const paymentLinks = getPaymentProviderLinks(session.provider_payload);
  const lifecycleTone = getLifecycleTone(policy.lifecycle);

  return (
    <AppCard className="p-4">
      <div className="space-y-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className="space-y-2">
            <StatusPill label={`Thanh toán ${surfaceLabel.toLowerCase()}`} tone={lifecycleTone} />
            <div>
              <p className="text-lg font-semibold">{getNextActionTitle(policy)}</p>
              <p className="mt-1 text-sm text-muted-foreground">{getNextActionDescription(policy)}</p>
            </div>
          </div>
          <StatusBadge status={session.session_status} />
        </div>

        <div className="rounded-lg border bg-background p-4">
          <div className="flex gap-3">
            {renderLifecycleIcon(policy.lifecycle)}
            <div>
              <p className="font-medium">{getNextActionTitle(policy)}</p>
              <p className="mt-1 text-sm text-muted-foreground">{getNextActionDescription(policy)}</p>
            </div>
          </div>
        </div>

        {policy.statusMessage ? (
          <div className="rounded-lg bg-secondary/40 p-3 text-sm text-muted-foreground">{policy.statusMessage}</div>
        ) : null}

        <div className="grid gap-3 sm:grid-cols-3">
          <SessionTile
            eyebrow="Ghi nhận"
            title={getSettlementTitle(policy)}
            description={getSettlementDescription(policy)}
            status={session.settlement_status}
          />
          <SessionTile eyebrow="Kiểm tra trạng thái" title={getRefreshTitle(policy)} description={getRefreshDescription(policy)} />
          <SessionTile eyebrow="Nhà cung cấp" title={getProviderTitle(policy)} description={getProviderDescription(policy, surfaceLabel)} />
        </div>

        <div className="grid gap-3 sm:grid-cols-4">
          <SessionMeta label="Mã tham chiếu" value={session.provider_session_code} />
          <SessionMeta label="Số tiền" value={amountLabel} />
          <SessionMeta label="Hết hạn" value={expiryLabel} />
          <SessionMeta label="Kiểm tra lần cuối" value={lastCheckedLabel} />
        </div>

        {paymentLinks.length > 0 ? (
          <div className="flex flex-wrap gap-2">
            {paymentLinks.map((link) => (
              <AppButton key={link.href} asChild variant="outline">
                <Link href={link.href} target="_blank" rel="noreferrer">
                  <ExternalLink className="h-4 w-4" />
                  {link.label}
                </Link>
              </AppButton>
            ))}
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">
            Liên kết thanh toán, hóa đơn hoặc biên nhận chỉ hiển thị khi nhà cung cấp trả về URL an toàn cho khách hàng.
          </p>
        )}

        {policy.canRefresh || policy.canConfirm ? (
          <div className="flex flex-wrap gap-2">
            {policy.canRefresh ? (
              <AppButton type="button" variant="outline" disabled={actionPending} onClick={onRefresh}>
                <RefreshCw className="h-4 w-4" />
                {refreshPending ? "Đang cập nhật" : policy.lifecycle === "failed" ? "Thử kiểm tra lại" : "Cập nhật trạng thái"}
              </AppButton>
            ) : null}
            {policy.canConfirm ? (
              <AppButton type="button" disabled={actionPending} onClick={onConfirm}>
                {confirmPending ? "Đang xác nhận" : "Xác nhận thanh toán"}
              </AppButton>
            ) : null}
          </div>
        ) : null}
      </div>
    </AppCard>
  );
}

function getPaymentProviderLinks(payload: Record<string, unknown> | null | undefined): Array<{ label: string; href: string }> {
  const record = asRecord(payload);

  if (!record) {
    return [];
  }

  const candidates = [
    { label: "Mở thanh toán", href: stringValue(record, ["checkout_url", "payment_url", "redirect_url"]) },
    { label: "Xem biên nhận", href: stringValue(record, ["receipt_url", "receipt_link"]) },
    { label: "Xem hóa đơn", href: stringValue(record, ["invoice_url", "invoice_link"]) },
  ];

  return candidates.filter((candidate): candidate is { label: string; href: string } => isSafeHttpUrl(candidate.href));
}

function isSafeHttpUrl(value: string | null): value is string {
  if (!value) {
    return false;
  }

  try {
    const url = new URL(value);

    return url.protocol === "https:" || url.protocol === "http:";
  } catch {
    return false;
  }
}

function getLifecycleTone(lifecycle: PaymentSessionPolicy["lifecycle"]) {
  switch (lifecycle) {
    case "succeeded":
      return "success" as const;
    case "failed":
    case "expired":
    case "cancelled":
      return "danger" as const;
    case "confirmed":
      return "info" as const;
    case "pending":
    default:
      return "warning" as const;
  }
}

function renderLifecycleIcon(lifecycle: PaymentSessionPolicy["lifecycle"]) {
  switch (lifecycle) {
    case "succeeded":
      return <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-primary" />;
    case "failed":
    case "expired":
    case "cancelled":
      return <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-primary" />;
    case "confirmed":
    case "pending":
    default:
      return <Clock3 className="mt-0.5 h-5 w-5 shrink-0 text-primary" />;
  }
}

function getNextActionTitle(policy: PaymentSessionPolicy): string {
  if (policy.lifecycle === "succeeded") return "Đã ghi nhận thanh toán";
  if (policy.lifecycle === "failed") return "Thanh toán thất bại";
  if (policy.lifecycle === "expired") return "Phiên thanh toán đã hết hạn";
  if (policy.lifecycle === "cancelled") return "Thanh toán đã hủy";
  if (policy.lifecycle === "confirmed") return "Đang chờ ghi nhận";

  return "Hoàn tất thanh toán rồi cập nhật";
}

function getNextActionDescription(policy: PaymentSessionPolicy): string {
  if (policy.lifecycle === "succeeded") {
    return "Lịch đặt vẫn được giữ và khoản thanh toán này đã được ghi nhận.";
  }

  if (policy.lifecycle === "failed") {
    return policy.failureMessage ?? "Thanh toán chưa được ghi nhận. Bạn có thể cập nhật trạng thái hoặc mở thanh toán mới khi lịch đặt cho phép.";
  }

  if (policy.lifecycle === "expired") {
    return "Cửa sổ thanh toán đã hết hạn trước khi ghi nhận. Mở phiên mới nếu vẫn cần thanh toán.";
  }

  if (policy.lifecycle === "cancelled") {
    return "Phiên này đã hủy trước khi thanh toán được ghi nhận.";
  }

  if (policy.lifecycle === "confirmed") {
    return "Nhà cung cấp đã xác nhận thanh toán. Hệ thống nhà hàng đang ghi nhận vào lịch đặt.";
  }

  return "Dùng trang thanh toán của nhà cung cấp nếu có. Ứng dụng này không yêu cầu chi tiết thẻ hoặc lưu dữ liệu thanh toán nhạy cảm.";
}

function getSettlementTitle(policy: PaymentSessionPolicy): string {
  switch (policy.settlement) {
    case "applied":
      return "Đã ghi nhận";
    case "failed":
      return "Chưa ghi nhận";
    case "expired":
      return "Hết hạn";
    case "cancelled":
      return "Đã hủy";
    case "unapplied":
    default:
      return "Đang chờ";
  }
}

function getSettlementDescription(policy: PaymentSessionPolicy): string {
  switch (policy.settlement) {
    case "applied":
      return "Khoản thanh toán đã được áp dụng cho lịch đặt này.";
    case "failed":
      return "Khoản thanh toán chưa được ghi nhận thành công.";
    case "expired":
      return "Phiên nhà cung cấp đã hết hạn trước khi ghi nhận cuối cùng.";
    case "cancelled":
      return "Phiên nhà cung cấp đã hủy trước khi ghi nhận cuối cùng.";
    case "unapplied":
    default:
      return policy.lifecycle === "confirmed"
      ? "Nhà cung cấp đã xác nhận thanh toán và ứng dụng đang cập nhật."
        : "Chưa ghi nhận kết quả cuối từ nhà cung cấp.";
  }
}

function getRefreshTitle(policy: PaymentSessionPolicy): string {
  switch (policy.refreshMode) {
    case "auto":
      return "Tự cập nhật";
    case "manual":
      return "Cập nhật thủ công";
    case "stopped":
    default:
      return "Đã dừng cập nhật";
  }
}

function getRefreshDescription(policy: PaymentSessionPolicy): string {
  switch (policy.refreshMode) {
    case "auto":
      return "Trạng thái sẽ tự cập nhật trong thời gian ngắn khi thanh toán đang diễn ra.";
    case "manual":
      return "Bấm cập nhật khi bạn muốn kiểm tra trạng thái mới nhất từ nhà cung cấp.";
    case "stopped":
    default:
    return "Phiên này đã kết thúc, nên ứng dụng đã dừng kiểm tra trạng thái.";
  }
}

function getProviderTitle(policy: PaymentSessionPolicy): string {
  switch (policy.providerSupport.state) {
    case "simulated":
      return "Nhà cung cấp mô phỏng";
    case "provider_backed":
      return "Đã kết nối nhà cung cấp";
    case "seeded_uat_required":
      return "Cần thiết lập UAT";
    case "not_enabled":
      return "Chưa bật nhà cung cấp";
    case "conditional":
    default:
      return "Phụ thuộc nhà cung cấp";
  }
}

function getProviderDescription(policy: PaymentSessionPolicy, surfaceLabel: string): string {
  if (policy.providerSupport.state === "simulated") {
    return "Đây là phiên kiểm thử, không phải thanh toán thật của khách hàng.";
  }

  if (policy.providerSupport.state === "provider_backed") {
    return `Nhà hàng đã mở phiên thanh toán ${surfaceLabel.toLowerCase()} qua nhà cung cấp.`;
  }

  return "Khả dụng của nhà cung cấp phụ thuộc vào thiết lập thanh toán của nhà hàng.";
}
