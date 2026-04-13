import { useEffect, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useLocation, useNavigate } from 'react-router-dom';
import { listBranches } from '../../core/api/staff-branch-api';
import {
  hasStaffStartupBranch,
  isStaffSessionOperatorReady,
  requiresStaffCashierShift,
} from '../../core/auth/startup';
import { formatRelativeAge } from '../../core/utils/format';
import { stripJourneySearch } from '../../core/utils/journey';
import { resolveRouteDataScope } from './route-scope';
import { visibleNavigation, visibleNavigationGroups } from '../router/navigation';
import { useAuthStore } from '../store/auth-store';
import { useFlowStore } from '../store/flow-store';

type ContextDockEntry = {
  key: string;
  label: string;
  value: string;
  tone: 'default' | 'warning' | 'success' | 'processing';
  meta?: string;
};

type RouteScopedNotice = {
  tone: 'warning' | 'info' | 'error' | 'success';
  title: string;
  description: string;
} | null;

export function useStaffShellContext() {
  const navigate = useNavigate();
  const location = useLocation();
  const session = useAuthStore((state) => state.session);
  const lastSessionSyncAt = useAuthStore((state) => state.lastSessionSyncAt);
  const setNotice = useAuthStore((state) => state.setNotice);
  const branchId = useFlowStore((state) => state.branchId);
  const hydrateFromSession = useFlowStore((state) => state.hydrateFromSession);
  const setBranchId = useFlowStore((state) => state.setBranchId);
  const selectedTableId = useFlowStore((state) => state.selectedTableId);
  const selectedTableLabel = useFlowStore((state) => state.selectedTableLabel);
  const selectedReservationId = useFlowStore((state) => state.selectedReservationId);
  const selectedReservationLabel = useFlowStore((state) => state.selectedReservationLabel);
  const selectedOrderId = useFlowStore((state) => state.selectedOrderId);
  const selectedOrderLabel = useFlowStore((state) => state.selectedOrderLabel);
  const selectedStationId = useFlowStore((state) => state.selectedStationId);
  const selectedStationLabel = useFlowStore((state) => state.selectedStationLabel);
  const source = useFlowStore((state) => state.source);
  const workItems = useFlowStore((state) => state.workItems);
  const touchWork = useFlowStore((state) => state.touchWork);

  useEffect(() => {
    hydrateFromSession(session);
  }, [hydrateFromSession, session]);

  const navigationItems = visibleNavigation(session);
  const navigationGroups = visibleNavigationGroups(session);
  const activeNavigationItem = navigationItems.find((item) => (
    location.pathname === item.path || location.pathname.startsWith(`${item.path}/`)
  )) ?? null;
  const selectedMenuKey = activeNavigationItem?.key;

  const branchesQuery = useQuery({
    queryKey: ['staff-branches'],
    queryFn: listBranches,
    enabled: !!session,
    staleTime: 5 * 60_000,
  });

  const branchOptions = useMemo(
    () => (branchesQuery.data?.data ?? []).map((branch) => ({
      value: branch.branch_id,
      label: `${branch.branch_code} • ${branch.branch_name}`,
    })),
    [branchesQuery.data?.data],
  );

  const activeBranch = useMemo(
    () => (branchesQuery.data?.data ?? []).find((branch) => branch.branch_id === branchId) ?? null,
    [branchId, branchesQuery.data?.data],
  );

  useEffect(() => {
    const branches = branchesQuery.data?.data ?? [];
    if (branches.length === 0) {
      return;
    }

    const defaultBranchId = session?.startup.default_branch?.branch_id ?? null;
    const hasActiveBranch = branchId !== null && branches.some((branch) => branch.branch_id === branchId);
    const fallbackBranchId = defaultBranchId !== null && branches.some((branch) => branch.branch_id === defaultBranchId)
      ? defaultBranchId
      : branches[0]?.branch_id ?? null;

    if (hasActiveBranch || branchId === fallbackBranchId) {
      return;
    }

    setBranchId(fallbackBranchId);
  }, [branchId, branchesQuery.data?.data, session?.startup.default_branch?.branch_id, setBranchId]);

  const routeDescriptor = useMemo(() => {
    if (location.pathname === '/access') {
      return {
        label: 'Trung tâm mở ca',
        description: 'Xác nhận chi nhánh, readiness và hướng xử lý an toàn trước khi quay lại ca làm.',
      };
    }

    return activeNavigationItem ?? {
      label: 'Điều hành staff',
      description: 'Mở đúng workspace theo chi nhánh, flow đang dở và readiness hiện tại.',
    };
  }, [activeNavigationItem, location.pathname]);
  const routeDataScope = useMemo(
    () => resolveRouteDataScope(location.pathname),
    [location.pathname],
  );

  const contextDock = useMemo<Array<ContextDockEntry>>(() => {
    const readiness = session?.startup.readiness;
    const activeShift = session?.startup.active_cashier_shift;
    const branchSummary = activeBranch
      ? `${activeBranch.branch_code} • ${activeBranch.branch_name}`
      : session?.startup.default_branch
        ? `${session.startup.default_branch.branch_code} • ${session.startup.default_branch.branch_name}`
        : 'Chưa neo chi nhánh';

    const selectedContextValue = [
      selectedTableId ? selectedTableLabel ?? `Bàn #${selectedTableId}` : null,
      selectedReservationId ? selectedReservationLabel ?? `Đặt bàn #${selectedReservationId}` : null,
      selectedOrderId ? selectedOrderLabel ?? `Đơn #${selectedOrderId}` : null,
      selectedStationId ? selectedStationLabel ?? `Trạm #${selectedStationId}` : null,
    ].filter((value): value is string => value !== null);

    return [
      {
        key: 'branch',
        label: 'Chi nhánh',
        value: branchSummary,
        tone: readiness?.branch === 'ready' ? 'success' : 'warning',
        meta: branchId ? `Đang thao tác theo chi nhánh ${branchId}` : 'Chưa khóa chi nhánh thao tác',
      },
      {
        key: 'shift',
        label: 'Ca thu ngân',
        value: activeShift?.shift_code ?? 'Chưa có ca hoạt động',
        tone: activeShift ? 'processing' : session && requiresStaffCashierShift(session) ? 'warning' : 'default',
        meta: activeShift?.terminal_code ? `Thiết bị ${activeShift.terminal_code}` : 'Luồng tiền sẽ chờ readiness hiện tại',
      },
      {
        key: 'readiness',
        label: 'Readiness',
        value: session && isStaffSessionOperatorReady(session) ? 'Sẵn sàng vận hành' : 'Cần kiểm tra trước ca',
        tone: session && isStaffSessionOperatorReady(session) ? 'success' : 'warning',
        meta: readiness ? `${readiness.granted_capability_count}/${readiness.known_capability_count} quyền thao tác đã được cấp` : undefined,
      },
      {
        key: 'context',
        label: 'Ngữ cảnh đang giữ',
        value: selectedContextValue.length > 0 ? selectedContextValue.join(' • ') : 'Chưa khóa đối tượng thao tác',
        tone: selectedContextValue.length > 0 ? 'processing' : 'default',
        meta: source ? `Tiếp tục từ ${source}` : 'Chưa có flow đang dở cần nối tiếp',
      },
    ];
  }, [
    activeBranch,
    branchId,
    selectedOrderId,
    selectedOrderLabel,
    selectedReservationId,
    selectedReservationLabel,
    selectedStationId,
    selectedStationLabel,
    selectedTableId,
    selectedTableLabel,
    session,
    source,
  ]);

  const contextTags = useMemo(
    () => contextDock
      .find((entry) => entry.key === 'context')
      ?.value.split(' • ')
      .filter((value) => value !== 'Chưa khóa đối tượng thao tác') ?? [],
    [contextDock],
  );

  const currentPath = `${location.pathname}${location.search}`;

  const sameBranchWorkItems = useMemo(
    () => workItems.filter((item) => item.branchId === null || branchId === null || item.branchId === branchId),
    [branchId, workItems],
  );
  const otherBranchWorkItems = useMemo(
    () => workItems.filter((item) => item.branchId !== null && branchId !== null && item.branchId !== branchId),
    [branchId, workItems],
  );
  const resumeTrayItems = useMemo(
    () => sameBranchWorkItems.filter((item) => item.path !== currentPath).slice(0, 5),
    [currentPath, sameBranchWorkItems],
  );

  useEffect(() => {
    if (!session || location.pathname === '/dashboard' || location.pathname === '/access' || location.pathname === '/login') {
      return;
    }

    const subtitle = [
      contextTags.length > 0 ? contextTags.join(' • ') : null,
      activeBranch ? `${activeBranch.branch_code} • ${activeBranch.branch_name}` : null,
    ].filter((value): value is string => value !== null).join(' • ');

    touchWork({
      key: currentPath,
      path: currentPath,
      label: routeDescriptor.label,
      subtitle: subtitle === '' ? routeDescriptor.description : subtitle,
      branchId,
    });
  }, [
    activeBranch,
    branchId,
    contextTags,
    currentPath,
    location.pathname,
    routeDescriptor.description,
    routeDescriptor.label,
    session,
    touchWork,
  ]);

  const freshnessUpdatedAt = Math.max(lastSessionSyncAt ?? 0, branchesQuery.dataUpdatedAt ?? 0);
  const freshnessAgeMinutes = freshnessUpdatedAt > 0 ? Math.floor((Date.now() - freshnessUpdatedAt) / 60_000) : null;
  const freshnessLabel = freshnessAgeMinutes === null
    ? 'Chưa đồng bộ'
    : freshnessAgeMinutes < 1
      ? 'Đã cập nhật trong <1 phút'
      : `Đã cập nhật trong ${freshnessAgeMinutes} phút`;
  const freshnessTone = freshnessAgeMinutes === null
    ? 'warning'
    : freshnessAgeMinutes >= 10
      ? 'warning'
      : 'success';
  const freshnessMeta = freshnessUpdatedAt > 0
    ? `Bản shell này đã cập nhật ${formatRelativeAge(freshnessUpdatedAt, { short: true })}`
    : 'Đăng nhập lại hoặc làm mới phiên nếu dữ liệu shell không còn tin cậy';

  const routeScopedNotice = useMemo<RouteScopedNotice>(() => {
    if (session && !hasStaffStartupBranch(session)) {
      return {
        tone: 'warning',
        title: 'Chi nhánh thao tác chưa sẵn sàng',
        description: 'Quay lại trung tâm mở ca để xác nhận branch context trước khi chạm vào bàn, đơn hàng hoặc tiền.',
      };
    }

    if (!routeDataScope) {
      return null;
    }

    return {
      tone: routeDataScope.tone,
      title: routeDataScope.title,
      description: routeDataScope.description,
    };
  }, [routeDataScope, session]);

  const shellStatusCopy = routeDataScope
    ? routeDataScope.kind === 'global'
      ? `Shell đang giữ branch context cho điều hướng, nhưng dữ liệu của màn này vẫn ở phạm vi toàn staff actor. ${freshnessMeta}.`
      : `Shell đang giữ branch context cho luồng FOH, nhưng màn này đang trộn snapshot theo branch và theo staff actor. ${freshnessMeta}.`
    : session && isStaffSessionOperatorReady(session)
      ? `Shell đang bám đúng chi nhánh và readiness. ${freshnessMeta}.`
      : `Shell đang giữ ngữ cảnh an toàn nhưng còn bước chuẩn bị chưa hoàn tất. ${freshnessMeta}.`;

  void shellStatusCopy;

  const quickNavOptions = useMemo(
    () => navigationItems.map((item) => ({
      value: item.path,
      label: item.label,
      description: item.description,
    })),
    [navigationItems],
  );

  function handleBranchChange(nextBranchId: number | null) {
    if (nextBranchId === branchId) {
      return;
    }

    const nextBranch = (branchesQuery.data?.data ?? []).find((branch) => branch.branch_id === nextBranchId) ?? null;
    const displacedContext = contextTags.length > 0 ? contextTags.join(' • ') : 'flow đang dở';

    setBranchId(nextBranchId);

    const nextSearch = stripJourneySearch(location.search);
    if (nextSearch !== location.search.replace(/^\?/, '')) {
      navigate(
        {
          pathname: location.pathname,
          search: nextSearch ? `?${nextSearch}` : '',
        },
        { replace: true },
      );
    }

    setNotice({
      tone: 'warning',
      message: nextBranch
        ? `Đã chuyển sang ${nextBranch.branch_name}. Shell đã xóa ${displacedContext} của chi nhánh trước để tránh thao tác nhầm.`
        : 'Đã đổi chi nhánh thao tác. Shell đã xóa ngữ cảnh cũ để tránh thao tác nhầm.',
    });
  }

  return {
    branchId,
    branchOptions,
    branchesQuery,
    contextDock,
    freshnessLabel,
    freshnessTone,
    handleBranchChange,
    navigationGroups,
    quickNavOptions,
    resumeTrayItems,
    routeDescriptor,
    routeScopedNotice,
    selectedMenuKey,
    session,
    otherBranchWorkItems,
  };
}
