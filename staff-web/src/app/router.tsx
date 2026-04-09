import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom';
import { LoaderCircle } from 'lucide-react';
import { Login } from '../components/Login';
import { Panel } from '../components/ui';
import { canAccessStaffSection, defaultStaffPath, staffSections, type StaffSection } from './sections';
import { StaffShell } from '../components/shell/StaffShell';
import { StaffSessionProvider } from './session';
import { useStaffSession } from './session-context';
import { BoardPage } from '../features/board/BoardPage';
import { OrdersPage } from '../features/orders/OrdersPage';
import { SettlementPage } from '../features/settlement/SettlementPage';
import { RefundsPage } from '../features/refunds/RefundsPage';
import { CashierPage } from '../features/cashier/CashierPage';
import { ConversationsPage } from '../features/conversations/ConversationsPage';
import { AccessPage } from '../features/access/AccessPage';
import { KitchenPage } from '../features/kitchen/KitchenPage';
import { ReportingPage } from '../features/reporting/ReportingPage';
import { InventoryPage } from '../features/inventory/InventoryPage';
import { SettingsPage } from '../features/settings/SettingsPage';

const boardSection = requireSection('/board');
const ordersSection = requireSection('/orders');
const kitchenSection = requireSection('/kitchen');
const settlementSection = requireSection('/settlement');
const refundsSection = requireSection('/refunds');
const cashierSection = requireSection('/cashier');
const reportingSection = requireSection('/reporting');
const inventorySection = requireSection('/inventory');
const settingsSection = requireSection('/settings');
const conversationsSection = requireSection('/conversations');

export function AppRouter() {
  return (
    <StaffSessionProvider>
      <BrowserRouter>
        <RouterContent />
      </BrowserRouter>
    </StaffSessionProvider>
  );
}

function RouterContent() {
  const { booting, session } = useStaffSession();
  const defaultPath = defaultStaffPath(session);

  if (booting) {
    return (
      <div className="min-h-screen bg-[linear-gradient(135deg,#f7f3eb,#fffdf9)]">
        <div className="mx-auto flex min-h-screen max-w-5xl items-center justify-center px-6">
          <Panel>
            <div className="flex items-center gap-3">
              <LoaderCircle className="h-5 w-5 animate-spin text-amber-600" />
              <p>Dang xac thuc staff session...</p>
            </div>
          </Panel>
        </div>
      </div>
    );
  }

  return (
    <Routes>
      <Route path="/login" element={session ? <Navigate to={defaultPath} replace /> : <LoginRoute />} />
      <Route element={<ProtectedRoute />}>
        <Route element={<StaffShell />}>
          <Route index element={<Navigate to={defaultPath} replace />} />
          <Route
            path="/board"
            element={
              <CapabilityRoute section={boardSection}>
                <BoardPage />
              </CapabilityRoute>
            }
          />
          <Route
            path="/orders"
            element={
              <CapabilityRoute section={ordersSection}>
                <OrdersPage />
              </CapabilityRoute>
            }
          />
          <Route
            path="/kitchen"
            element={
              <CapabilityRoute section={kitchenSection}>
                <KitchenPage />
              </CapabilityRoute>
            }
          />
          <Route
            path="/settlement"
            element={
              <CapabilityRoute section={settlementSection}>
                <SettlementPage />
              </CapabilityRoute>
            }
          />
          <Route
            path="/refunds"
            element={
              <CapabilityRoute section={refundsSection}>
                <RefundsPage />
              </CapabilityRoute>
            }
          />
          <Route
            path="/cashier"
            element={
              <CapabilityRoute section={cashierSection}>
                <CashierPage />
              </CapabilityRoute>
            }
          />
          <Route
            path="/reporting"
            element={
              <CapabilityRoute section={reportingSection}>
                <ReportingPage />
              </CapabilityRoute>
            }
          />
          <Route
            path="/inventory"
            element={
              <CapabilityRoute section={inventorySection}>
                <InventoryPage />
              </CapabilityRoute>
            }
          />
          <Route
            path="/settings"
            element={
              <CapabilityRoute section={settingsSection}>
                <SettingsPage />
              </CapabilityRoute>
            }
          />
          <Route
            path="/conversations"
            element={
              <CapabilityRoute section={conversationsSection}>
                <ConversationsPage />
              </CapabilityRoute>
            }
          />
          <Route path="/access" element={<AccessPage />} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to={session ? defaultPath : '/login'} replace />} />
    </Routes>
  );
}

function LoginRoute() {
  const { notice, noticeTone, setAuthenticatedSession, clearNotice } = useStaffSession();

  return (
    <Login
      notice={notice}
      noticeTone={noticeTone}
      onSuccess={(session) => {
        clearNotice();
        setAuthenticatedSession(session);
      }}
    />
  );
}

function ProtectedRoute() {
  const { session } = useStaffSession();

  if (!session) {
    return <Navigate to="/login" replace />;
  }

  return <Outlet />;
}

function CapabilityRoute({
  section,
  children,
}: {
  section: StaffSection;
  children: JSX.Element;
}) {
  const { session } = useStaffSession();

  if (!session) {
    return <Navigate to="/login" replace />;
  }

  if (!session.startup.readiness.operator_ready) {
    return <Navigate to="/access" replace />;
  }

  if (!canAccessStaffSection(session, section)) {
    return <Navigate to={defaultStaffPath(session)} replace />;
  }

  return children;
}

function requireSection(path: string): StaffSection {
  const section = staffSections.find((candidate) => candidate.path === path);

  if (!section) {
    throw new Error(`Unknown staff section: ${path}`);
  }

  return section;
}
