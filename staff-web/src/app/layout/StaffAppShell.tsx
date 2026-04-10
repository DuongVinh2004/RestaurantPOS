import { useEffect, useMemo } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Alert, Button, Layout, Menu, Select, Space, Tag, Typography } from 'antd';
import { listBranches } from '../../core/api/staff-api';
import { formatApiError } from '../../core/api/errors';
import { buildJourneyResumeTarget, stripJourneySearch } from '../../core/utils/journey';
import { visibleNavigation } from '../router/navigation';
import { recommendedPathForSession, useAuthStore } from '../store/auth-store';
import { useFlowStore } from '../store/flow-store';

const { Header, Sider, Content } = Layout;

export function StaffAppShell() {
  const navigate = useNavigate();
  const location = useLocation();
  const session = useAuthStore((state) => state.session);
  const notice = useAuthStore((state) => state.notice);
  const clearNotice = useAuthStore((state) => state.clearNotice);
  const refresh = useAuthStore((state) => state.refresh);
  const logout = useAuthStore((state) => state.logout);
  const setNotice = useAuthStore((state) => state.setNotice);
  const branchId = useFlowStore((state) => state.branchId);
  const hydrateFromSession = useFlowStore((state) => state.hydrateFromSession);
  const setBranchId = useFlowStore((state) => state.setBranchId);
  const selectedTableId = useFlowStore((state) => state.selectedTableId);
  const selectedReservationId = useFlowStore((state) => state.selectedReservationId);
  const selectedReservationRowVersion = useFlowStore((state) => state.selectedReservationRowVersion);
  const selectedOrderId = useFlowStore((state) => state.selectedOrderId);
  const selectedOrderRowVersion = useFlowStore((state) => state.selectedOrderRowVersion);
  const selectedStationId = useFlowStore((state) => state.selectedStationId);
  const source = useFlowStore((state) => state.source);

  useEffect(() => {
    hydrateFromSession(session);
  }, [hydrateFromSession, session]);

  const navigationItems = visibleNavigation(session);
  const activeNavigationItem = navigationItems.find((item) => location.pathname === item.path || location.pathname.startsWith(`${item.path}/`)) ?? null;
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

  const resumeTarget = useMemo(
    () => buildJourneyResumeTarget({
      source,
      tableId: selectedTableId ?? undefined,
      reservationId: selectedReservationId ?? undefined,
      reservationRowVersion: selectedReservationRowVersion ?? undefined,
      orderId: selectedOrderId ?? undefined,
      orderRowVersion: selectedOrderRowVersion ?? undefined,
      stationId: selectedStationId ?? undefined,
    }),
    [
      selectedOrderId,
      selectedOrderRowVersion,
      selectedReservationId,
      selectedReservationRowVersion,
      selectedStationId,
      selectedTableId,
      source,
    ],
  );

  const recommendedPath = recommendedPathForSession(session);
  const recommendedRoute = recommendedPath === '/access'
    ? {
      path: '/access',
      label: 'Trung tâm vận hành',
      description: 'Kiểm tra độ sẵn sàng ca làm việc, chi nhánh và bước tiếp theo an toàn trước khi mở luồng chính.',
    }
    : navigationItems.find((item) => item.path === recommendedPath) ?? null;

  const routeDescriptor = useMemo(() => {
    if (location.pathname === '/access') {
      return {
        label: 'Trung tâm vận hành',
        description: 'Bàn điều phối đầu ca để kiểm tra chi nhánh, độ sẵn sàng và lối vào công việc phù hợp.',
      };
    }

    return activeNavigationItem ?? {
      label: 'Staff Web',
      description: 'Không gian điều phối tác vụ cho ca làm việc hiện tại.',
    };
  }, [activeNavigationItem, location.pathname]);

  const routeScopedNotice = useMemo(() => {
    if (location.pathname === '/finance-review') {
      return {
        tone: 'warning' as const,
        title: 'Đối soát tài chính chưa khóa hoàn toàn theo chi nhánh đã chọn',
        description: 'Bộ chọn chi nhánh hiện vẫn giúp giữ ngữ cảnh điều hướng trong staff-web, nhưng dữ liệu đối soát còn phụ thuộc vào giới hạn backend hiện tại. Chỉ dùng màn hình này như bước triage và xác nhận lại chi tiết trước khi xử lý tài chính.',
      };
    }

    if (session?.startup.readiness.branch !== 'ready') {
      return {
        tone: 'warning' as const,
        title: 'Ngữ cảnh chi nhánh chưa đủ tin cậy',
        description: 'Hãy quay lại trung tâm vận hành để xác nhận chi nhánh mặc định trước khi tiếp tục các luồng gắn bàn, đơn hàng hoặc tài chính.',
      };
    }

    return null;
  }, [location.pathname, session?.startup.readiness.branch]);

  if (!session) {
    return null;
  }

  const contextTags = [
    selectedTableId ? `Bàn #${selectedTableId}` : null,
    selectedReservationId ? `Đặt bàn #${selectedReservationId}` : null,
    selectedOrderId ? `Đơn #${selectedOrderId}` : null,
    selectedStationId ? `Trạm #${selectedStationId}` : null,
  ].filter((value): value is string => value !== null);

  const resumePathname = resumeTarget?.path.split('?')[0] ?? null;
  const showResumeAction = !!resumeTarget && resumePathname !== location.pathname;
  const showRecommendedAction = !!recommendedRoute && recommendedRoute.path !== location.pathname && recommendedRoute.path !== resumePathname;
  const branchSummary = activeBranch
    ? `${activeBranch.branch_code} • ${activeBranch.branch_name}`
    : session.startup.default_branch
      ? `${session.startup.default_branch.branch_code} • ${session.startup.default_branch.branch_name}`
      : 'Chưa có chi nhánh đáng tin cậy';

  function handleBranchChange(nextBranchId: number | null) {
    if (nextBranchId === branchId) {
      return;
    }

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
      message: 'Đã xóa ngữ cảnh bàn, đặt bàn, đơn hàng hoặc trạm của chi nhánh cũ. Hãy chọn lại dữ liệu trong chi nhánh mới.',
    });
  }

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider width={270}>
        <div className="staff-sider-brand">
          <Typography.Text className="staff-eyebrow">Màn hình nhân viên</Typography.Text>
          <Typography.Title level={4} style={{ color: '#fff', margin: '8px 0 4px' }}>
            RestaurantPOS
          </Typography.Title>
          <Typography.Paragraph style={{ color: 'rgba(255,255,255,0.68)', marginBottom: 0 }}>
            Staff-web chuẩn vận hành: rõ ngữ cảnh, rõ bước tiếp theo, không để ca làm việc dựa vào trạng thái mơ hồ.
          </Typography.Paragraph>
        </div>

        <Menu
          theme="dark"
          mode="inline"
          selectedKeys={selectedMenuKey ? [selectedMenuKey] : []}
          items={navigationItems.map((item) => ({
            key: item.key,
            label: item.label,
            onClick: () => navigate(item.path),
          }))}
        />
      </Sider>

      <Layout>
        <Header className="staff-shell-header">
          <div className="staff-shell-header-main">
            <div className="staff-shell-headline">
              <Typography.Text className="staff-eyebrow">Ngữ cảnh ca làm việc</Typography.Text>
              <Typography.Title level={2} style={{ margin: '8px 0 6px' }}>
                {routeDescriptor.label}
              </Typography.Title>
              <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
                {routeDescriptor.description}
              </Typography.Paragraph>
            </div>

            <div className="staff-shell-context-strip">
              <div className="staff-shell-context-summary">
                <Typography.Text strong>{branchSummary}</Typography.Text>
                <Typography.Text type="secondary">
                  {session.startup.readiness.branch === 'ready'
                    ? 'Chi nhánh đang được dùng làm điểm neo cho điều hướng và bộ lọc staff-web.'
                    : 'Cần xác nhận lại chi nhánh mặc định trước khi tiếp tục các luồng có ràng buộc bàn hoặc tài chính.'}
                </Typography.Text>
              </div>

              <Space wrap size={8}>
                {session.startup.default_branch ? (
                  <Tag color="blue">
                    Mặc định {session.startup.default_branch.branch_code}
                  </Tag>
                ) : null}
                <Tag color={session.startup.readiness.branch === 'ready' ? 'green' : 'gold'}>
                  Chi nhánh {session.startup.readiness.branch}
                </Tag>
                <Tag color={session.startup.readiness.cashier_shift === 'ready' ? 'green' : 'gold'}>
                  Ca {session.startup.active_cashier_shift?.shift_code ?? session.startup.readiness.cashier_shift}
                </Tag>
                {contextTags.map((tag) => (
                  <Tag key={tag}>{tag}</Tag>
                ))}
              </Space>
            </div>
          </div>

          <div className="staff-shell-control-stack">
            <Select
              aria-label="Chọn chi nhánh hoạt động"
              style={{ width: 300 }}
              value={branchId ?? undefined}
              placeholder="Chọn chi nhánh hoạt động"
              options={branchOptions}
              onChange={(value) => handleBranchChange(value)}
              loading={branchesQuery.isLoading}
            />

            <Space wrap size={8} className="staff-shell-action-row">
              {showResumeAction ? (
                <Button type="primary" onClick={() => navigate(resumeTarget.path)}>
                  {resumeTarget.label}
                </Button>
              ) : null}
              {showRecommendedAction ? (
                <Button onClick={() => navigate(recommendedRoute.path)}>
                  {recommendedRoute.label}
                </Button>
              ) : null}
              <Button onClick={() => void refresh()}>
                Làm mới phiên
              </Button>
              <Button
                onClick={async () => {
                  await logout();
                  navigate('/login', { replace: true });
                }}
              >
                Đăng xuất
              </Button>
            </Space>
          </div>
        </Header>

        <Content className="staff-shell-content">
          {notice ? (
            <Alert
              style={{ marginBottom: 16 }}
              type={notice.tone === 'success' ? 'success' : notice.tone === 'warning' ? 'warning' : 'error'}
              showIcon
              title={notice.message}
              closable
              onClose={clearNotice}
            />
          ) : null}
          {branchesQuery.error ? (
            <Alert
              style={{ marginBottom: 16 }}
              type="warning"
              showIcon
              title="Không thể tải danh sách chi nhánh nhân viên"
              description={formatApiError(branchesQuery.error, 'Kiểm tra lại backend hoặc làm mới phiên để lấy lại danh sách chi nhánh.')}
            />
          ) : null}
          {routeScopedNotice ? (
            <Alert
              style={{ marginBottom: 16 }}
              type={routeScopedNotice.tone}
              showIcon
              title={routeScopedNotice.title}
              description={routeScopedNotice.description}
            />
          ) : null}
          <Outlet />
        </Content>
      </Layout>
    </Layout>
  );
}
