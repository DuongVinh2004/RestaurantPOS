import { Suspense, lazy, useEffect } from 'react';
import type { ComponentType } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom';
import { Spin } from 'antd';
import { FullPageState } from '../../components/states/StateBlocks';
import { can } from '../../core/permissions/capabilities';
import { recommendedPathForSession, useAuthStore } from '../store/auth-store';
import { resolveFallbackRedirectPath, resolveIndexRedirectPath } from './redirects';

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
const LoginPage = lazyRoute(() => import('../../features/auth/LoginPage'), 'LoginPage');
const AccessGatePage = lazyRoute(() => import('../../features/access/AccessGatePage'), 'AccessGatePage');
const DashboardPage = lazyRoute(() => import('../../features/dashboard/DashboardPage'), 'DashboardPage');
const TableBoardPage = lazyRoute(() => import('../../features/tables/TableBoardPage'), 'TableBoardPage');
const ReservationsPage = lazyRoute(() => import('../../features/reservations/ReservationsPage'), 'ReservationsPage');
const OrderWorkspacePage = lazyRoute(() => import('../../features/orders/OrderWorkspacePage'), 'OrderWorkspacePage');
const KitchenBoardPage = lazyRoute(() => import('../../features/kitchen/KitchenBoardPage'), 'KitchenBoardPage');
const CheckoutPage = lazyRoute(() => import('../../features/checkout/CheckoutPage'), 'CheckoutPage');
const WaitingListPage = lazyRoute(() => import('../../features/waiting/WaitingListPage'), 'WaitingListPage');
const CashierShiftPage = lazyRoute(() => import('../../features/cashier/CashierShiftPage'), 'CashierShiftPage');
const FinanceReviewPage = lazyRoute(() => import('../../features/finance/FinanceReviewPage'), 'FinanceReviewPage');
const ConversationInboxPage = lazyRoute(
  () => import('../../features/conversations/ConversationInboxPage'),
  'ConversationInboxPage',
);
const AuditTrailPage = lazyRoute(() => import('../../features/audit/AuditTrailPage'), 'AuditTrailPage');
const ReportingHubPage = lazyRoute(() => import('../../features/reporting/ReportingHubPage'), 'ReportingHubPage');

export function AppRouter() {
  return (
    <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <BootstrapGate>
        <Suspense fallback={<RouteLoadingState />}>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route element={<ProtectedRoute />}>
              <Route element={<StaffAppShell />}>
                <Route index element={<IndexRedirect />} />
                <Route path="/access" element={<AccessGatePage />} />
                <Route path="/dashboard" element={<DashboardRoute />} />
                <Route
                  path="/tables"
                  element={(
                    <CapabilityRoute capability="table.board.view">
                      <TableBoardPage />
                    </CapabilityRoute>
                  )}
                />
                <Route
                  path="/reservations"
                  element={(
                    <CapabilityRoute capability="reservation.manage">
                      <ReservationsPage />
                    </CapabilityRoute>
                  )}
                />
                <Route
                  path="/orders"
                  element={(
                    <CapabilityRoute capability="order.manage">
                      <OrderWorkspacePage />
                    </CapabilityRoute>
                  )}
                />
                <Route
                  path="/kitchen"
                  element={(
                    <CapabilityRoute capability="kitchen.manage">
                      <KitchenBoardPage />
                    </CapabilityRoute>
                  )}
                />
                <Route
                  path="/checkout"
                  element={(
                    <CapabilityRoute capability="settlement.manage">
                      <CheckoutPage />
                    </CapabilityRoute>
                  )}
                />
                <Route
                  path="/waiting-list"
                  element={(
                    <CapabilityRoute capability="waiting_list.manage">
                      <WaitingListPage />
                    </CapabilityRoute>
                  )}
                />
                <Route
                  path="/cashier-shift"
                  element={(
                    <CapabilityRoute capability="cashier.shift.manage">
                      <CashierShiftPage />
                    </CapabilityRoute>
                  )}
                />
                <Route
                  path="/finance-review"
                  element={(
                    <CapabilityRoute capability="settlement.manage">
                      <FinanceReviewPage />
                    </CapabilityRoute>
                  )}
                />
                <Route
                  path="/conversations"
                  element={(
                    <CapabilityRoute capability="conversation.manage">
                      <ConversationInboxPage />
                    </CapabilityRoute>
                  )}
                />
                <Route
                  path="/audit-trail"
                  element={(
                    <CapabilityRoute capability="audit.view">
                      <AuditTrailPage />
                    </CapabilityRoute>
                  )}
                />
                <Route
                  path="/reporting"
                  element={(
                    <CapabilityRoute capability="reporting.view">
                      <ReportingHubPage />
                    </CapabilityRoute>
                  )}
                />
              </Route>
            </Route>
            <Route path="*" element={<FallbackRedirect />} />
          </Routes>
        </Suspense>
      </BootstrapGate>
    </BrowserRouter>
  );
}

function RouteLoadingState() {
  return (
    <div className="staff-boot-screen">
      <Spin description="Đang tải màn hình..." />
    </div>
  );
}

function BootstrapGate({ children }: { children: JSX.Element }) {
  const status = useAuthStore((state) => state.status);
  const bootstrap = useAuthStore((state) => state.bootstrap);

  useEffect(() => {
    void bootstrap();
  }, [bootstrap]);

  if (status === 'booting') {
    return (
      <div className="staff-boot-screen">
        <Spin description="Đang khởi tạo phiên nhân viên..." />
      </div>
    );
  }

  return children;
}

function ProtectedRoute() {
  const session = useAuthStore((state) => state.session);

  if (!session) {
    return <Navigate to="/login" replace />;
  }

  return <Outlet />;
}

function DashboardRoute() {
  const session = useAuthStore((state) => state.session);
  const nextPath = recommendedPathForSession(session);

  if (!session) {
    return <Navigate to="/login" replace />;
  }

  if (nextPath !== '/dashboard') {
    return <Navigate to={nextPath} replace />;
  }

  return <DashboardPage />;
}

function CapabilityRoute({
  capability,
  children,
}: {
  capability: string;
  children: JSX.Element;
}) {
  const session = useAuthStore((state) => state.session);

  if (!session) {
    return <Navigate to="/login" replace />;
  }

  if (!can(session, capability)) {
    return (
      <FullPageState
        status="403"
        title="Phiên hiện tại chưa có quyền"
        description={`Màn hình này chỉ hiển thị khi phiên nhân viên có quyền ${capability}.`}
      />
    );
  }

  return children;
}

function IndexRedirect() {
  const session = useAuthStore((state) => state.session);
  return <Navigate to={resolveIndexRedirectPath(session)} replace />;
}

function FallbackRedirect() {
  const session = useAuthStore((state) => state.session);
  return <Navigate to={resolveFallbackRedirectPath(session)} replace />;
}
