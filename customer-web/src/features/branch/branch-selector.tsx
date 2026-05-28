"use client";

import { useState } from "react";
import { ExternalLink, LocateFixed, MapPin, Phone, Store } from "lucide-react";
import {
  AppButton,
  AppCard,
  AppSelect,
  AppSkeleton,
  BottomSheet,
  EmptyState,
  ErrorState,
  SectionHeader,
  StatusPill,
} from "@/components/customer/ui";
import { cn } from "@/lib/utils";
import { useBranchSelection } from "./hooks";
import type { CustomerBranch } from "./state";

export function SelectedBranchEntry({ className }: { className?: string }) {
  const [open, setOpen] = useState(false);
  const { selectedBranch, isLoading } = useBranchSelection();

  return (
    <BottomSheet
      open={open}
      onOpenChange={setOpen}
      title="Chọn chi nhánh"
      description="Lựa chọn này sẽ được dùng lại cho thực đơn, đặt bàn, chờ bàn và món đặt trước."
      trigger={
        <AppButton type="button" variant="outline" className={cn("justify-start gap-2", className)} data-testid="customer-branch-select-trigger">
          <Store className="h-4 w-4" />
          <span className="min-w-0 truncate">
            {isLoading ? "Đang tải chi nhánh" : selectedBranch?.branchName ?? "Chọn chi nhánh"}
          </span>
        </AppButton>
      }
    >
      <BranchSelector />
    </BottomSheet>
  );
}

export function BranchSelector({ className }: { className?: string }) {
  const {
    branches,
    selectedBranch,
    selectedBranchId,
    isLoading,
    error,
    locationPermission,
    locationMessage,
    selectBranch,
    findNearMe,
    refetch,
  } = useBranchSelection();

  if (isLoading) {
    return (
      <div className={cn("space-y-4", className)} aria-busy="true" aria-label="Đang tải chi nhánh">
        <AppSkeleton className="h-11 w-full" />
        <AppSkeleton className="h-40 w-full" />
      </div>
    );
  }

  if (error) {
    return <ErrorState error={error} title="Chưa tải được thông tin chi nhánh" onRetry={refetch} className={className} />;
  }

  if (!selectedBranch || branches.length === 0) {
    return (
      <EmptyState
        className={className}
        title="Chưa có chi nhánh khả dụng"
        description="Nhà hàng chưa mở hồ sơ chi nhánh cho khách hàng."
      />
    );
  }

  return (
    <div className={cn("space-y-5", className)}>
      <SectionHeader
        eyebrow="Địa điểm"
        title="Chi nhánh của bạn"
        description="Chi nhánh này sẽ được dùng cho thực đơn, đặt bàn và thông tin liên hệ trong lượt ghé của bạn."
      />

      <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
        <AppSelect
          label="Chi nhánh"
          value={String(selectedBranchId ?? selectedBranch.branchId)}
          onValueChange={(value) => selectBranch(Number(value))}
          options={branches.map((branch) => ({
            value: String(branch.branchId),
            label: branch.branchName,
          }))}
          helperText={branches.length === 1 ? "Hiện chỉ có một chi nhánh trực tuyến cho khách hàng." : undefined}
        />
        <AppButton
          type="button"
          variant="outline"
          className="self-end"
          disabled={locationPermission === "requesting"}
          onClick={findNearMe}
        >
          <LocateFixed className="h-4 w-4" />
          {locationPermission === "requesting" ? "Đang kiểm tra" : "Tìm gần tôi"}
        </AppButton>
      </div>

      {locationMessage ? (
        <p
          className={cn(
            "rounded-lg border p-3 text-sm",
            locationPermission === "denied" || locationPermission === "error"
              ? "border-amber-200 bg-amber-50 text-amber-900"
              : "bg-secondary/40 text-muted-foreground",
          )}
        >
          {locationMessage}
        </p>
      ) : null}

      <BranchList branches={branches} selectedBranchId={selectedBranch.branchId} onSelect={selectBranch} />
      <BranchDetailCard branch={selectedBranch} />
    </div>
  );
}

export function BranchList({
  branches,
  selectedBranchId,
  onSelect,
}: {
  branches: CustomerBranch[];
  selectedBranchId: number;
  onSelect: (branchId: number) => void;
}) {
  return (
    <div className="grid gap-2" aria-label="Chi nhánh khả dụng">
      {branches.map((branch) => {
        const selected = branch.branchId === selectedBranchId;

        return (
          <button
            key={branch.branchId}
            type="button"
            className={cn(
              "flex min-h-14 items-center justify-between gap-3 rounded-lg border bg-card px-3 text-left transition hover:bg-accent",
              selected && "border-primary bg-primary/5",
            )}
            onClick={() => onSelect(branch.branchId)}
            aria-pressed={selected}
            data-testid={`customer-branch-option-${branch.branchId}`}
          >
            <span className="min-w-0">
              <span className="block truncate text-sm font-medium">{branch.branchName}</span>
              <span className="block text-xs text-muted-foreground">{branch.branchCode}</span>
            </span>
            <StatusPill label={branch.statusLabel} tone={branch.isOpen ? "success" : "warning"} />
          </button>
        );
      })}
    </div>
  );
}

export function BranchDetailCard({ branch, className }: { branch: CustomerBranch; className?: string }) {
  return (
    <AppCard className={cn("p-4", className)}>
      <div className="space-y-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h3 className="text-lg font-semibold">{branch.branchName}</h3>
            <p className="text-sm text-muted-foreground">{branch.timezone}</p>
          </div>
          <StatusPill label={branch.statusLabel} tone={branch.isOpen ? "success" : "warning"} />
        </div>

        <dl className="grid gap-3 text-sm">
          <div>
            <dt className="text-muted-foreground">Hôm nay</dt>
            <dd className="font-medium">{branch.todayHoursLabel}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Địa chỉ</dt>
            <dd className="flex items-start gap-2">
              <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
              <span>{branch.address}</span>
            </dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Điện thoại</dt>
            <dd>
              <a className="inline-flex items-center gap-2 font-medium hover:text-primary" href={`tel:${branch.phone}`}>
                <Phone className="h-4 w-4" />
                {branch.phoneDisplay}
              </a>
            </dd>
          </div>
        </dl>

        <div className="grid gap-2 sm:grid-cols-2">
          <AppButton asChild variant="outline">
            <a href={branch.directionsUrl} target="_blank" rel="noreferrer">
              <ExternalLink className="h-4 w-4" />
              Chỉ đường
            </a>
          </AppButton>
          <AppButton asChild variant="outline">
            <a href={`tel:${branch.phone}`}>
              <Phone className="h-4 w-4" />
              Gọi chi nhánh
            </a>
          </AppButton>
        </div>

        {branch.weeklyHours.length > 0 ? (
          <div className="border-t pt-4">
            <h4 className="text-sm font-medium">Giờ mở cửa</h4>
            <dl className="mt-3 grid gap-2 text-sm">
              {branch.weeklyHours.map((item) => (
                <div key={item.day} className="grid grid-cols-[6rem_1fr] gap-3">
                  <dt className="text-muted-foreground">{item.day}</dt>
                  <dd className="font-medium">{item.hours}</dd>
                </div>
              ))}
            </dl>
          </div>
        ) : null}
      </div>
    </AppCard>
  );
}
