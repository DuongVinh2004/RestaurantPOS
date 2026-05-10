"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { getRestaurantProfile } from "@/features/restaurant/api";
import { queryKeys } from "@/lib/api/query-keys";
import {
  branchFromRestaurantProfile,
  getSelectedBranchId,
  persistSelectedBranchId,
  type CustomerBranch,
} from "./state";

export type LocationPermissionState = "idle" | "requesting" | "resolved" | "denied" | "unavailable" | "error";

export type BranchSelectionState = {
  branches: CustomerBranch[];
  selectedBranch: CustomerBranch | null;
  selectedBranchId: number | null;
  isLoading: boolean;
  error: unknown;
  locationPermission: LocationPermissionState;
  locationMessage: string | null;
  selectBranch: (branchId: number) => void;
  findNearMe: () => void;
  refetch: () => void;
};

export function useBranchSelection(): BranchSelectionState {
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(() => getSelectedBranchId());
  const [locationPermission, setLocationPermission] = useState<LocationPermissionState>("idle");
  const [locationMessage, setLocationMessage] = useState<string | null>(null);

  const profileQuery = useQuery({
    queryKey: queryKeys.restaurant.profile,
    queryFn: getRestaurantProfile,
    staleTime: 5 * 60 * 1000,
    retry: 1,
  });

  const branches = useMemo(
    () => (profileQuery.data ? [branchFromRestaurantProfile(profileQuery.data)] : []),
    [profileQuery.data],
  );

  useEffect(() => {
    if (branches.length === 0) {
      return;
    }

    const storedBranch = getSelectedBranchId();
    const nextBranchId = branches.some((branch) => branch.branchId === storedBranch)
      ? storedBranch
      : branches[0]?.branchId ?? null;

    if (nextBranchId) {
      persistSelectedBranchId(nextBranchId);
    }
  }, [branches]);

  const selectBranch = useCallback((branchId: number) => {
    persistSelectedBranchId(branchId);
    setSelectedBranchId(branchId);
  }, []);

  const findNearMe = useCallback(() => {
    if (typeof navigator === "undefined" || !navigator.geolocation) {
      setLocationPermission("unavailable");
      setLocationMessage("Trình duyệt này không hỗ trợ vị trí.");
      return;
    }

    setLocationPermission("requesting");
    setLocationMessage("Đang kiểm tra vị trí của bạn...");

    navigator.geolocation.getCurrentPosition(
      () => {
        const branchId = branches[0]?.branchId ?? null;

        if (branchId) {
          persistSelectedBranchId(branchId);
          setSelectedBranchId(branchId);
        }

        setLocationPermission("resolved");
        setLocationMessage(
          branches.length > 1
            ? "Chọn chi nhánh gần nhất sẽ dùng tọa độ thật khi API cung cấp dữ liệu."
            : "Hiện chỉ có chi nhánh mặc định trực tuyến.",
        );
      },
      (error) => {
        if (error.code === error.PERMISSION_DENIED) {
          setLocationPermission("denied");
          setLocationMessage("Bạn đã từ chối quyền vị trí. Bạn vẫn có thể chọn chi nhánh thủ công.");
          return;
        }

        setLocationPermission("error");
        setLocationMessage("Chưa kiểm tra được vị trí. Bạn vẫn có thể chọn chi nhánh thủ công.");
      },
      {
        enableHighAccuracy: false,
        maximumAge: 10 * 60 * 1000,
        timeout: 8000,
      },
    );
  }, [branches]);

  const selectedBranch = branches.find((branch) => branch.branchId === selectedBranchId) ?? branches[0] ?? null;

  return {
    branches,
    selectedBranch,
    selectedBranchId: selectedBranch?.branchId ?? selectedBranchId,
    isLoading: profileQuery.isLoading,
    error: profileQuery.error,
    locationPermission,
    locationMessage,
    selectBranch,
    findNearMe,
    refetch: () => {
      void profileQuery.refetch();
    },
  };
}
