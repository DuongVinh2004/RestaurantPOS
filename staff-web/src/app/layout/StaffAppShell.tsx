import { Suspense, lazy, useEffect, useMemo, useState, type ChangeEvent, type KeyboardEvent as ReactKeyboardEvent } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import {
  Command,
  Armchair,
  BarChart3,
  CalendarClock,
  ChefHat,
  ClipboardList,
  Clock3,
  LayoutDashboard,
  LogOut,
  MessagesSquare,
  PanelLeftOpen,
  ReceiptText,
  RefreshCcw,
  ShieldCheck,
  WalletCards,
  type LucideIcon,
} from 'lucide-react';
import { formatStaffFacingApiError } from '../../core/api/errors';
import type { StaffNavIconKey } from '../../core/types/navigation';
import { StaffFacingAlert } from '../../components/feedback/StaffFacingAlert';
import { useAuthStore } from '../store/auth-store';
import { useStaffShellContext } from './useStaffShellContext';
const StaffShellCommandPalette = lazy(
  () => import('./StaffShellCommandPalette').then((module) => ({ default: module.StaffShellCommandPalette })),
);
const StaffShellNavDrawer = lazy(
  () => import('./StaffShellNavDrawer').then((module) => ({ default: module.StaffShellNavDrawer })),
);

const navIcons: Record<StaffNavIconKey, LucideIcon> = {
  dashboard: LayoutDashboard,
  tables: Armchair,
  reservations: CalendarClock,
  waiting: Clock3,
  orders: ClipboardList,
  kitchen: ChefHat,
  checkout: ReceiptText,
  cashier: WalletCards,
  finance: ShieldCheck,
  conversations: MessagesSquare,
  audit: ShieldCheck,
  reporting: BarChart3,
};

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
    branchId,
    branchOptions,
    branchesQuery,
    contextDock,
    freshnessLabel,
    freshnessTone,
    handleBranchChange,
    navigationGroups,
    otherBranchWorkItems,
    quickNavOptions,
    resumeTrayItems,
    routeDescriptor,
    routeScopedNotice,
    selectedMenuKey,
    session,
  } = useStaffShellContext();

  const isDashboardRoute = location.pathname === '/dashboard';
  const commandItems = useMemo(() => {
    const items = [
      ...quickNavOptions.map((item) => ({
        key: `nav-${item.value}`,
        label: item.label,
        subtitle: item.description,
        path: item.value,
        group: 'Đi nhanh',
      })),
      ...resumeTrayItems.map((item) => ({
        key: `resume-${item.key}`,
        label: item.label,
        subtitle: item.subtitle ?? 'Tiếp tục đúng flow đang dở.',
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
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setCommandOpen(true);
      }
    }

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  useEffect(() => {
    function syncCompactNavigation() {
      setCompactNavigation(window.matchMedia('(max-width: 1100px)').matches);
    }

    syncCompactNavigation();
    window.addEventListener('resize', syncCompactNavigation);
    return () => window.removeEventListener('resize', syncCompactNavigation);
  }, []);

  useEffect(() => {
    if (!commandOpen) {
      setCommandActiveIndex(0);
      return;
    }

    if (filteredCommandItems.length === 0) {
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

  const branchSelectValue = branchId === null ? '' : String(branchId);

  const navContent = (
    <>
      <div className="staff-sider-brand">
        <span className="staff-eyebrow">Operations cockpit</span>
        <h2 className="staff-sider-brand-title">RestaurantPOS</h2>
        <p className="staff-sider-brand-copy">
          Điều phối ca làm, branch context và việc nóng theo cùng một command surface.
        </p>
      </div>

      <nav className="staff-shell-menu" aria-label="Điều hướng staff">
        {navigationGroups.map((group) => (
          <section key={group.key} className="staff-shell-nav-group">
            <span className="staff-nav-group-label">{group.label}</span>

            <div className="staff-shell-nav-list">
              {group.items.map((item) => {
                const Icon = navIcons[item.iconKey];
                const isSelected = item.key === selectedMenuKey;
                const badgeLabel = typeof item.badgeCount === 'number' && item.badgeCount > 99
                  ? '99+'
                  : item.badgeCount;

                return (
                  <button
                    key={item.key}
                    type="button"
                    className={`staff-shell-nav-item ${isSelected ? 'staff-shell-nav-item-selected' : ''}`}
                    aria-current={isSelected ? 'page' : undefined}
                    onClick={() => openPath(item.path)}
                  >
                    <span className="staff-nav-item-icon" aria-hidden="true">
                      <Icon size={17} strokeWidth={2.1} />
                    </span>

                    <span className="staff-nav-item-row">
                      <span className="staff-nav-item-title">{item.label}</span>
                      {typeof item.badgeCount === 'number' && item.badgeCount > 0 ? (
                        <span className="staff-nav-item-badge">{badgeLabel}</span>
                      ) : null}
                    </span>
                  </button>
                );
              })}
            </div>
          </section>
        ))}
      </nav>
    </>
  );

  return (
    <div className="staff-shell-layout">
      <aside className="staff-shell-sider">
        {navContent}
      </aside>

      <div className="staff-shell-main">
        <header className={`staff-shell-header ${isDashboardRoute ? 'staff-shell-header-dashboard' : ''}`}>
          <div className="staff-shell-header-top">
            <div className="staff-shell-header-primary">
              <div className="staff-shell-header-status" aria-label="Ngữ cảnh màn hình hiện tại">
                {compactNavigation ? (
                  <button
                    type="button"
                    className="staff-shell-control-button staff-shell-button-ghost staff-shell-button-icon staff-shell-nav-toggle"
                    onClick={() => setNavDrawerOpen(true)}
                    aria-label="Mở điều hướng"
                  >
                    <PanelLeftOpen size={18} />
                  </button>
                ) : null}

                <div className="staff-shell-header-title-block">
                  <span className="staff-eyebrow">Bảng điều phối hiện tại</span>
                  <div className="staff-shell-header-title-row">
                    <h1 className="staff-shell-header-title">{routeDescriptor.label}</h1>
                    <span className={`staff-shell-freshness-chip staff-shell-freshness-chip-${freshnessTone}`}>
                      {freshnessLabel}
                    </span>
                  </div>
                </div>
              </div>

              <div className="staff-shell-header-context">
                {contextDock.slice(0, 3).map((entry) => (
                  <div
                    key={entry.key}
                    className={`staff-shell-context-card staff-shell-context-card-${entry.tone}`}
                    aria-label={entry.label}
                  >
                    <span className="staff-shell-context-label">{entry.label}</span>
                    <strong className="staff-shell-context-value">{entry.value}</strong>
                    {entry.meta ? <span className="staff-shell-context-meta">{entry.meta}</span> : null}
                  </div>
                ))}
              </div>
            </div>

            <div className="staff-shell-header-controls">
              <div className="staff-shell-header-select">
                <label className="staff-shell-branch-label" htmlFor="staff-shell-branch-select">
                  Chi nhánh thao tác
                </label>
                <div className="staff-shell-select-wrap">
                  <select
                    id="staff-shell-branch-select"
                    aria-label="Chọn chi nhánh hoạt động"
                    className="staff-shell-branch-select"
                    value={branchSelectValue}
                    disabled={branchesQuery.isLoading || branchOptions.length === 0}
                    onChange={handleBranchSelectChange}
                  >
                    {branchSelectValue === '' ? (
                      <option value="">
                        {branchesQuery.isLoading ? 'Đang tải chi nhánh...' : 'Chọn chi nhánh'}
                      </option>
                    ) : null}
                    {branchOptions.map((option) => (
                      <option key={option.value} value={String(option.value)}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="staff-shell-action-row">
                <button
                  type="button"
                  className="staff-shell-control-button staff-shell-button-subtle"
                  onClick={() => void refresh()}
                >
                  <RefreshCcw size={16} />
                  <span>Làm mới</span>
                </button>
                <button
                  type="button"
                  className="staff-shell-control-button staff-shell-button-quiet"
                  onClick={() => setCommandOpen(true)}
                >
                  <Command size={16} />
                  <span>Tìm</span>
                </button>
                <button
                  type="button"
                  className="staff-shell-control-button staff-shell-button-ghost"
                  onClick={async () => {
                    await logout();
                    navigate('/login', { replace: true });
                  }}
                >
                  <LogOut size={16} />
                  <span>Đăng xuất</span>
                </button>
              </div>
            </div>
          </div>
        </header>

        <main className={`staff-shell-content ${isDashboardRoute ? 'staff-shell-content-dashboard' : ''}`}>
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

          {branchesQuery.error ? (
            <div className="staff-shell-alert-stack">
              <StaffFacingAlert
                tone="warning"
                title="Dữ liệu chi nhánh tạm thời chưa sẵn sàng"
                description={formatStaffFacingApiError(
                  branchesQuery.error,
                  'Hãy làm mới phiên hoặc liên hệ quản trị nếu lỗi tiếp tục lặp lại.',
                )}
              />
            </div>
          ) : null}

          {otherBranchWorkItems.length > 0 ? (
            <div className="staff-shell-alert-stack">
              <StaffFacingAlert
                tone="info"
                title={`Còn ${otherBranchWorkItems.length} flow dở ở chi nhánh khác`}
                description="Resume tray chỉ mở ngay các flow cùng chi nhánh hiện tại để tránh thao tác nhầm. Đổi chi nhánh nếu cần nối lại công việc cũ."
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

          <Outlet />
        </main>
      </div>

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
            content={navContent}
            open={navDrawerOpen}
            onClose={() => setNavDrawerOpen(false)}
          />
        </Suspense>
      ) : null}
    </div>
  );
}
