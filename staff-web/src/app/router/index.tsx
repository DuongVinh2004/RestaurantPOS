import { useEffect } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom';
import { Spin } from 'antd';
import { can } from '../../core/permissions/capabilities';
import { FullPageState } from '../../components/states/StateBlocks';
import { LoginPage } from '../../features/auth/LoginPage';
import { AccessGatePage } from '../../features/access/AccessGatePage';
import { TableBoardPage } from '../../features/tables/TableBoardPage';
import { ReservationsPage } from '../../features/reservations/ReservationsPage';
import { WaitingListPage } from '../../features/waiting/WaitingListPage';
import { OrderWorkspacePage } from '../../features/orders/OrderWorkspacePage';
import { KitchenBoardPage } from '../../features/kitchen/KitchenBoardPage';
import { CheckoutPage } from '../../features/checkout/CheckoutPage';
import { CashierShiftPage } from '../../features/cashier/CashierShiftPage';
import { ConversationInboxPage } from '../../features/conversations/ConversationInboxPage';
import { AuditTrailPage } from '../../features/audit/AuditTrailPage';
import { ReportingHubPage } from '../../features/reporting/ReportingHubPage';
import { FinanceReviewPage } from '../../features/finance/FinanceReviewPage';
import { StaffAppShell } from '../layout/StaffAppShell';
import { defaultPathForSession, useAuthStore } from '../store/auth-store';

export function AppRouter() {
  return (
    <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <BootstrapGate>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route element={<ProtectedRoute />}>
            <Route element={<StaffAppShell />}>
              <Route index element={<IndexRedirect />} />
              <Route path="/access" element={<AccessGatePage />} />
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
      </BootstrapGate>
    </BrowserRouter>
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
        <Spin description="Äang khá»Ÿi táº¡o phiÃªn nhÃ¢n viÃªn..." />
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
        title="PhiÃªn hiá»‡n táº¡i chÆ°a cÃ³ quyá»n"
        description={`MÃ n hÃ¬nh nÃ y chá»‰ hiá»ƒn thá»‹ khi phiÃªn nhÃ¢n viÃªn cÃ³ quyá»n ${capability}.`}
      />
    );
  }

  return children;
}

function IndexRedirect() {
  const session = useAuthStore((state) => state.session);
  return <Navigate to={defaultPathForSession(session)} replace />;
}

function FallbackRedirect() {
  const session = useAuthStore((state) => state.session);
  return <Navigate to={session ? defaultPathForSession(session) : '/login'} replace />;
}

