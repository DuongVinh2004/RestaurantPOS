import { Suspense, lazy, useEffect, useMemo, useState, type ChangeEvent, type KeyboardEvent as ReactKeyboardEvent } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { formatStaffFacingApiError } from '../../shared/api/errors';
import { StaffFacingAlert } from '../../shared/ui/feedback/StaffFacingAlert';
import { AppFrameNavigation } from './frame/AppFrameNavigation';
import { staffRoutePaths } from '../router/workspace-paths';
import { useAuthStore, type AuthNotice } from '../store/auth-store';
import { AdminShell } from './AdminShell';
import { KitchenShell } from './KitchenShell';
import { OpsShell } from './OpsShell';
import { useStaffShellContext, type RouteScopedNotice } from './useStaffShellContext';

const StaffShellCommandPalette = lazy(
  () => import('./StaffShellCommandPalette').then((module) => ({ default: module.StaffShellCommandPalette })),
);
const StaffShellNavDrawer = lazy(
  () => import('./StaffShellNavDrawer').then((module) => ({ default: module.StaffShellNavDrawer })),
);

export function StaffAppShell() {
  const navigate = useNavigate();
  const location = useLocation();
  const notice = useAuthStore((state) => state.notice);
  const clearNotice = useAuthStore((state) => state.clearNotice);
  const refresh = useAuthStore((state) => state.refresh);
  const logout = useAuthStore((state) => state.logout);
  const [commandOpen, setCommandOpen] = useState(false);
  const [commandQuery, setCommandQuery] = useState('');
  const [commandActiveIndex, setCommandActiveIndex] = useState(0);
  const [navDrawerOpen, setNavDrawerOpen] = useState(false);
  const [compactNavigation, setCompactNavigation] = useState(false);
  const {
    activeWorkspace,
    branchId,
    branchOptions,
    branchesQuery,
    contextDock,
    freshnessLabel,
    freshnessTone,
    handleBranchChange,
    handleWorkspaceSwitch,
    navigationGroups,
    otherBranchWorkItems,
    quickNavOptions,
    resumeTrayItems,
    routeDescriptor,
    routeScopedNotice,
    selectedMenuKey,
    session,
    workspaceOptions,
  } = useStaffShellContext();

  const workspace = activeWorkspace ?? workspaceOptions[0]?.workspace ?? 'ops';
  const activeWorkspaceOption = workspaceOptions.find((option) => option.workspace === workspace) ?? null;
  const isOpsDashboard = location.pathname === staffRoutePaths.ops.dashboard;

  const commandItems = useMemo(() => {
    const items = [
      ...quickNavOptions.map((item) => ({
        key: `nav-${item.value}`,
        label: item.label,
        subtitle: item.description,
        path: item.value,
        group: 'Di nhanh',
      })),
      ...resumeTrayItems.map((item) => ({
        key: `resume-${item.key}`,
        label: item.label,
        subtitle: item.subtitle ?? 'Tiếp tục luồng đang dở.',
        path: item.path,
        group: item.pinned ? 'Việc đã ghim' : 'Tiếp tục công việc',
      })),
    ];

    const deduped = new Map(items.map((item) => [item.path, item]));
    return Array.from(deduped.values());
  }, [quickNavOptions, resumeTrayItems]);

  const filteredCommandItems = useMemo(() => {
    const normalizedQuery = commandQuery.trim().toLowerCase();
    if (normalizedQuery === '') {
      return commandItems.slice(0, 10);
    }

    return commandItems
      .filter((item) => `${item.label} ${item.subtitle} ${item.group}`.toLowerCase().includes(normalizedQuery))
      .slice(0, 10);
  }, [commandItems, commandQuery]);

  const groupedCommandItems = useMemo(() => {
    const grouped = new Map<string, typeof filteredCommandItems>();

    filteredCommandItems.forEach((item) => {
      const existing = grouped.get(item.group) ?? [];
      existing.push(item);
      grouped.set(item.group, existing);
    });

    return Array.from(grouped.entries());
  }, [filteredCommandItems]);

  const commandActiveItem = filteredCommandItems[commandActiveIndex] ?? null;

  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      if (workspace === 'kitchen') {
        return;
      }

      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setCommandOpen(true);
      }
    }

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [workspace]);

  useEffect(() => {
    function syncCompactNavigation() {
      setCompactNavigation(window.matchMedia('(max-width: 1100px)').matches);
    }

    syncCompactNavigation();
    window.addEventListener('resize', syncCompactNavigation);
    return () => window.removeEventListener('resize', syncCompactNavigation);
  }, []);

  useEffect(() => {
    const workspaceLabel = activeWorkspaceOption?.label ?? 'Workspace';
    document.title = `${routeDescriptor.label} | ${workspaceLabel} | Mộc Sen Staff`;
  }, [activeWorkspaceOption?.label, routeDescriptor.label]);

  useEffect(() => {
    if (!commandOpen || filteredCommandItems.length === 0) {
      setCommandActiveIndex(0);
      return;
    }

    setCommandActiveIndex((currentIndex) => Math.min(currentIndex, filteredCommandItems.length - 1));
  }, [commandOpen, filteredCommandItems.length]);

  function openPath(path: string) {
    setCommandOpen(false);
    setNavDrawerOpen(false);
    setCommandQuery('');
    setCommandActiveIndex(0);
    navigate(path);
  }

  function handleBranchSelectChange(event: ChangeEvent<HTMLSelectElement>) {
    const nextBranchId = event.target.value === '' ? null : Number(event.target.value);
    handleBranchChange(Number.isNaN(nextBranchId) ? null : nextBranchId);
  }

  function handleCommandKeyDown(event: ReactKeyboardEvent<HTMLInputElement>) {
    if (filteredCommandItems.length === 0) {
      if (event.key === 'Escape') {
        setCommandOpen(false);
        setCommandQuery('');
      }
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setCommandActiveIndex((currentIndex) => (currentIndex + 1) % filteredCommandItems.length);
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      setCommandActiveIndex((currentIndex) => (
        currentIndex === 0 ? filteredCommandItems.length - 1 : currentIndex - 1
      ));
    }

    if (event.key === 'Enter' && commandActiveItem) {
      event.preventDefault();
      openPath(commandActiveItem.path);
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      setCommandOpen(false);
      setCommandQuery('');
    }
  }

  if (!session) {
    return null;
  }

  async function logoutAndNavigate() {
    await logout();
    navigate('/login', { replace: true });
  }

  const alerts = renderShellAlerts({
    notice,
    clearNotice,
    branchesError: branchesQuery.error,
    otherBranchWorkItemsCount: otherBranchWorkItems.length,
    routeScopedNotice,
  });

  const sharedShellProps = {
    workspace,
    workspaceOption: activeWorkspaceOption,
    compactNavigation,
    navigationGroups,
    selectedMenuKey,
    contextDock,
    freshnessLabel,
    freshnessTone,
    branchId,
    branchOptions,
    branchesLoading: branchesQuery.isLoading,
    routeDescriptor,
    alerts,
    onOpenPath: openPath,
    onOpenNavigation: () => setNavDrawerOpen(true),
    onOpenCommandPalette: () => setCommandOpen(true),
    onRefresh: () => void refresh(),
    onLogout: () => void logoutAndNavigate(),
    onBranchChange: handleBranchSelectChange,
    onSwitchWorkspace: handleWorkspaceSwitch,
    workspaceOptions,
  } as const;

  const navDrawerContent = (
    <AppFrameNavigation
      brandEyebrow="Mộc Sen Bistro"
      brandTitle={activeWorkspaceOption?.label ?? 'Workspace'}
      brandCopy={activeWorkspaceOption?.description ?? 'Shared staff-web runtime with workspace-owned navigation.'}
      navigationGroups={navigationGroups}
      selectedMenuKey={selectedMenuKey}
      onOpenPath={openPath}
    />
  );

  const shell = workspace === 'kitchen'
    ? (
      <KitchenShell {...sharedShellProps}>
        <Outlet />
      </KitchenShell>
    )
    : workspace === 'admin'
      ? (
        <AdminShell {...sharedShellProps} contentClassName="staff-shell-content-admin-focus">
          <Outlet />
        </AdminShell>
      )
      : (
        <OpsShell
          {...sharedShellProps}
          contentClassName={isOpsDashboard ? 'staff-shell-content-dashboard' : undefined}
        >
          <Outlet />
        </OpsShell>
      );

  return (
    <>
      {shell}

      {commandOpen ? (
        <Suspense fallback={null}>
          <StaffShellCommandPalette
            activeIndex={commandActiveIndex}
            groupedItems={groupedCommandItems}
            items={filteredCommandItems}
            open={commandOpen}
            query={commandQuery}
            onActivate={setCommandActiveIndex}
            onClose={() => {
              setCommandOpen(false);
              setCommandQuery('');
              setCommandActiveIndex(0);
            }}
            onInputKeyDown={handleCommandKeyDown}
            onOpenPath={openPath}
            onQueryChange={setCommandQuery}
          />
        </Suspense>
      ) : null}

      {navDrawerOpen ? (
        <Suspense fallback={null}>
          <StaffShellNavDrawer
            content={navDrawerContent}
            open={navDrawerOpen}
            onClose={() => setNavDrawerOpen(false)}
          />
        </Suspense>
      ) : null}
    </>
  );
}

function renderShellAlerts({
  notice,
  clearNotice,
  branchesError,
  otherBranchWorkItemsCount,
  routeScopedNotice,
}: {
  notice: AuthNotice;
  clearNotice: () => void;
  branchesError: unknown;
  otherBranchWorkItemsCount: number;
  routeScopedNotice: RouteScopedNotice;
}) {
  return (
    <>
      {notice ? (
        <div className="staff-shell-alert-stack">
          <StaffFacingAlert
            tone={notice.tone === 'success' ? 'success' : notice.tone === 'warning' ? 'warning' : 'error'}
            title={notice.message}
            closable
            onClose={clearNotice}
          />
        </div>
      ) : null}

      {branchesError ? (
        <div className="staff-shell-alert-stack">
          <StaffFacingAlert
            tone="warning"
            title="Dữ liệu chi nhánh tạm thời chưa sẵn sàng"
            description={formatStaffFacingApiError(
              branchesError,
              'Hãy làm mới phiên hoặc liên hệ quản trị nếu lỗi tiếp tục lặp lại.',
            )}
          />
        </div>
      ) : null}

      {otherBranchWorkItemsCount > 0 ? (
        <div className="staff-shell-alert-stack">
          <StaffFacingAlert
            tone="info"
            title={`Còn ${otherBranchWorkItemsCount} luồng dở ở chi nhánh khác`}
            description="Khay tiếp tục chỉ mở luồng cùng chi nhánh hiện tại để tránh thao tác nhầm. Đổi chi nhánh nếu cần nối lại công việc cũ."
          />
        </div>
      ) : null}

      {routeScopedNotice ? (
        <div className="staff-shell-alert-stack">
          <StaffFacingAlert
            tone={routeScopedNotice.tone}
            title={routeScopedNotice.title}
            description={routeScopedNotice.description}
          />
        </div>
      ) : null}
    </>
  );
}
