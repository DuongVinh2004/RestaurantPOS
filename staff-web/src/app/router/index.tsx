import { Suspense, lazy, useEffect } from 'react';
import type { ComponentType } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom';
import type { WorkspaceId } from '../../workspaces/workspaces';
import {
  PageLoadingState,
} from '../../shared/ui/states/StateBlocks';
import type { StaffWorkspacePageId, StaffWorkspaceRouteDefinition } from '../../workspaces/routes';
import { adminWorkspaceRoutes } from '../../workspaces/admin/routes';
import { kitchenWorkspaceRoutes } from '../../workspaces/kitchen/routes';
import { opsWorkspaceRoutes } from '../../workspaces/ops/routes';
import { recommendedPathForSession, useAuthStore } from '../store/auth-store';
import { resolveRecommendedStaffPath } from './session-paths';
import { WorkspaceBoundary, WorkspaceIndexRedirect, WorkspaceRoute } from './workspace-route-guards';
import { staffRoutePaths } from './workspace-paths';

function lazyRoute<TModule extends Record<string, unknown>, TKey extends keyof TModule>(
  loader: () => Promise<TModule>,
  key: TKey,
) {
  return lazy(async () => {
    const module = await loader();
    return { default: module[key] as ComponentType };
  });
}

const StaffAppShell = lazyRoute(() => import('../layout/StaffAppShell'), 'StaffAppShell');
const LoginPage = lazyRoute(() => import('../auth/LoginPage'), 'LoginPage');
const AccessGatePage = lazyRoute(() => import('../../workspaces/shared/pages/access/AccessGatePage'), 'AccessGatePage');
const DashboardPage = lazyRoute(() => import('../../workspaces/ops/pages/dashboard/DashboardPage'), 'DashboardPage');
const TableBoardPage = lazyRoute(() => import('../../workspaces/ops/pages/tables/TableBoardPage'), 'TableBoardPage');
const ReservationsPage = lazyRoute(() => import('../../workspaces/ops/pages/reservations/ReservationsPage'), 'ReservationsPage');
const OrderWorkspacePage = lazyRoute(() => import('../../workspaces/ops/pages/orders/OrderWorkspacePage'), 'OrderWorkspacePage');
const KitchenLandingPage = lazyRoute(() => import('../../workspaces/kitchen/pages/landing/KitchenLandingPage'), 'KitchenLandingPage');
const KitchenBoardPage = lazyRoute(() => import('../../workspaces/kitchen/pages/board/KitchenBoardPage'), 'KitchenBoardPage');
const CheckoutPage = lazyRoute(() => import('../../workspaces/ops/pages/checkout/CheckoutPage'), 'CheckoutPage');
const RefundWorkspacePage = lazyRoute(() => import('../../workspaces/ops/pages/refunds/RefundWorkspacePage'), 'RefundWorkspacePage');
const WaitingListPage = lazyRoute(() => import('../../workspaces/ops/pages/waiting/WaitingListPage'), 'WaitingListPage');
const CashierShiftPage = lazyRoute(() => import('../../workspaces/ops/pages/cashier/CashierShiftPage'), 'CashierShiftPage');
const FinanceReviewPage = lazyRoute(() => import('../../workspaces/ops/pages/finance/FinanceReviewPage'), 'FinanceReviewPage');
const ConversationInboxPage = lazyRoute(
  () => import('../../workspaces/ops/pages/conversations/ConversationInboxPage'),
  'ConversationInboxPage',
);
const AdminLandingPage = lazyRoute(() => import('../../workspaces/admin/pages/landing/AdminLandingPage'), 'AdminLandingPage');
const AdminCatalogPage = lazyRoute(() => import('../../workspaces/admin/pages/catalog/AdminCatalogPage'), 'AdminCatalogPage');
const AdminSettingsPage = lazyRoute(() => import('../../workspaces/admin/pages/settings/AdminSettingsPage'), 'AdminSettingsPage');
const AdminInventoryPage = lazyRoute(() => import('../../workspaces/admin/pages/inventory/AdminInventoryPage'), 'AdminInventoryPage');
const AdminBenefitsPage = lazyRoute(() => import('../../workspaces/admin/pages/benefits/AdminBenefitsPage'), 'AdminBenefitsPage');
const AdminPrivacyPage = lazyRoute(() => import('../../workspaces/admin/pages/privacy/AdminPrivacyPage'), 'AdminPrivacyPage');
const AuditTrailPage = lazyRoute(() => import('../../workspaces/admin/pages/audit/AuditTrailPage'), 'AuditTrailPage');
const ReportingHubPage = lazyRoute(() => import('../../workspaces/admin/pages/reporting/ReportingHubPage'), 'ReportingHubPage');

export function AppRouter() {
  return (
    <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <BootstrapGate>
        <Suspense fallback={<RouteLoadingState />}>
          <Routes>
            <Route path={staffRoutePaths.login} element={<LoginPage />} />
            <Route element={<ProtectedRoute />}>
              <Route element={<StaffAppShell />}>
                <Route index element={<IndexRedirect />} />
                <Route path={staffRoutePaths.access} element={<AccessGatePage />} />

                <Route path={staffRoutePaths.ops.root} element={<WorkspaceBoundary workspace="ops" />}>
                  <Route index element={<WorkspaceIndexRedirect workspace="ops" />} />
                  {renderWorkspaceRoutes('ops', opsWorkspaceRoutes)}
                </Route>

                <Route path={staffRoutePaths.kitchen.root} element={<WorkspaceBoundary workspace="kitchen" />}>
                  {renderWorkspaceRoutes('kitchen', kitchenWorkspaceRoutes)}
                </Route>

                <Route path={staffRoutePaths.admin.root} element={<WorkspaceBoundary workspace="admin" />}>
                  {renderWorkspaceRoutes('admin', adminWorkspaceRoutes)}
                </Route>
              </Route>
            </Route>
            <Route path="*" element={<FallbackRedirect />} />
          </Routes>
        </Suspense>
      </BootstrapGate>
    </BrowserRouter>
  );
}

function renderWorkspaceRoutes(
  workspace: WorkspaceId,
  routes: Array<StaffWorkspaceRouteDefinition>,
) {
  return routes.map((route) => (
    <Route
      key={`${workspace}-${route.key}`}
      path={route.path === '' ? undefined : route.path}
      index={route.path === ''}
      element={(
        <WorkspaceRoute capability={route.capability} workspace={workspace}>
          {renderWorkspacePage(route.page)}
        </WorkspaceRoute>
      )}
    />
  ));
}

function renderWorkspacePage(page: StaffWorkspacePageId): JSX.Element {
  switch (page) {
    case 'admin-landing':
      return <AdminLandingPage />;
    case 'admin-catalog':
      return <AdminCatalogPage />;
    case 'admin-settings':
      return <AdminSettingsPage />;
    case 'admin-inventory':
      return <AdminInventoryPage />;
    case 'admin-benefits':
      return <AdminBenefitsPage />;
    case 'admin-privacy':
      return <AdminPrivacyPage />;
    case 'dashboard':
      return <DashboardRoute />;
    case 'tables':
      return <TableBoardPage />;
    case 'reservations':
      return <ReservationsPage />;
    case 'orders':
      return <OrderWorkspacePage />;
    case 'checkout':
      return <CheckoutPage />;
    case 'refunds':
      return <RefundWorkspacePage />;
    case 'waiting-list':
      return <WaitingListPage />;
    case 'cashier-shift':
      return <CashierShiftPage />;
    case 'finance-review':
      return <FinanceReviewPage />;
    case 'conversations':
      return <ConversationInboxPage />;
    case 'kitchen-landing':
      return <KitchenLandingPage />;
    case 'kitchen-board':
      return <KitchenBoardPage />;
    case 'reporting':
      return <ReportingHubPage />;
    case 'audit-trail':
      return <AuditTrailPage />;
    default:
      return <DashboardRoute />;
  }
}

function RouteLoadingState() {
  return <PageLoadingState title="Đang tải màn hình…" description="Staff-web đang dựng route và khôi phục module cần cho bước tiếp theo." />;
}

function BootstrapGate({ children }: { children: JSX.Element }) {
  const status = useAuthStore((state) => state.status);
  const bootstrap = useAuthStore((state) => state.bootstrap);

  useEffect(() => {
    void bootstrap();
  }, [bootstrap]);

  if (status === 'booting') {
    return <PageLoadingState title="Đang khởi tạo phiên nhân viên…" description="Hệ thống đang kiểm tra capability, branch mặc định và điều kiện vận hành của phiên hiện tại." />;
  }

  return children;
}

function ProtectedRoute() {
  const session = useAuthStore((state) => state.session);

  if (!session) {
    return <Navigate to={staffRoutePaths.login} replace />;
  }

  return <Outlet />;
}

function DashboardRoute() {
  const session = useAuthStore((state) => state.session);
  const nextPath = recommendedPathForSession(session);

  if (!session) {
    return <Navigate to={staffRoutePaths.login} replace />;
  }

  if (nextPath !== staffRoutePaths.ops.dashboard) {
    return <Navigate to={nextPath} replace />;
  }

  return <DashboardPage />;
}

function IndexRedirect() {
  const session = useAuthStore((state) => state.session);
  return <Navigate to={resolveRecommendedStaffPath(session)} replace />;
}

function FallbackRedirect() {
  const session = useAuthStore((state) => state.session);
  return <Navigate to={resolveRecommendedStaffPath(session)} replace />;
}
