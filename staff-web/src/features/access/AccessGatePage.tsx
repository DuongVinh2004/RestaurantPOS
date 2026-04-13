import { Alert, Button, Card, Col, Row, Space, Typography } from 'antd';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '../../components/layout/PageHeader';
import { FullPageState } from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { visibleNavigation } from '../../app/router/navigation';
import { recommendedPathForSession, useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import {
  hasStaffStartupAccess,
  hasStaffStartupBranch,
  isStaffCashierShiftActionRequired,
  isStaffSessionOperatorReady,
} from '../../core/auth/startup';
import { buildJourneyResumeTarget } from '../../core/utils/journey';
import { paymentTone } from '../../core/utils/status';

export function AccessGatePage() {
  const navigate = useNavigate();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const selectedTableId = useFlowStore((state) => state.selectedTableId);
  const selectedReservationId = useFlowStore((state) => state.selectedReservationId);
  const selectedReservationRowVersion = useFlowStore((state) => state.selectedReservationRowVersion);
  const selectedOrderId = useFlowStore((state) => state.selectedOrderId);
  const selectedOrderRowVersion = useFlowStore((state) => state.selectedOrderRowVersion);
  const selectedStationId = useFlowStore((state) => state.selectedStationId);
  const source = useFlowStore((state) => state.source);
  const workItems = useFlowStore((state) => state.workItems);

  if (!session) {
    return (
      <FullPageState
        status="403"
        title="Không có phiên nhân viên đang hoạt động"
        description="Đăng nhập lại để khôi phục ngữ cảnh nhân viên."
        extra={<Button onClick={() => navigate('/login', { replace: true })}>Đi tới đăng nhập</Button>}
      />
    );
  }

  const readiness = session.startup.readiness;
  const defaultBranch = session.startup.default_branch;
  const activeShift = session.startup.active_cashier_shift;
  const navigationItems = visibleNavigation(session);
  const cashierShiftAvailable = navigationItems.some((item) => item.path === '/cashier-shift');
  const recommendedPath = recommendedPathForSession(session);
  const recommendedItem = navigationItems.find((item) => item.path === recommendedPath) ?? null;
  const resumeTarget = recommendedPath !== '/access'
    ? buildJourneyResumeTarget({
      source,
      tableId: selectedTableId ?? undefined,
      reservationId: selectedReservationId ?? undefined,
      reservationRowVersion: selectedReservationRowVersion ?? undefined,
      orderId: selectedOrderId ?? undefined,
      orderRowVersion: selectedOrderRowVersion ?? undefined,
      stationId: selectedStationId ?? undefined,
    })
    : null;
  const financeBlocked = isStaffCashierShiftActionRequired(session);
  const blockers = [
    !hasStaffStartupAccess(session)
      ? 'Phiên này chưa có đủ capability để vào luồng vận hành chuẩn.'
      : null,
    !hasStaffStartupBranch(session)
      ? 'Chi nhánh mặc định chưa được phiên nhân viên xác nhận, nên ngữ cảnh bàn, đặt bàn và tài chính chưa đáng tin cậy.'
      : null,
    financeBlocked
      ? 'Thanh toán và đối soát vẫn khóa cho tới khi có ca thu ngân đang mở.'
      : null,
  ].filter((value): value is string => value !== null);

  return (
    <Space orientation="vertical" size={20} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Trung tâm vận hành"
        title="Bắt đầu ca làm việc từ ngữ cảnh đáng tin cậy"
        description="Màn hình này là điểm vào mặc định của staff-web. Nó trả lời ba câu hỏi trước khi nhân viên đi tiếp: chi nhánh nào đang được tin cậy, ca có đủ điều kiện cho nghiệp vụ tài chính hay chưa, và bước nào nên làm ngay bây giờ."
        context={(
          <>
            <StatusChip label={defaultBranch ? `${defaultBranch.branch_code} • ${defaultBranch.branch_name}` : 'Chưa có chi nhánh'} tone={readiness.branch === 'ready' ? 'success' : 'warning'} />
            <StatusChip label={activeShift ? activeShift.shift_code : 'Chưa có ca thu ngân'} tone={paymentTone(readiness.cashier_shift)} />
            <StatusChip label={isStaffSessionOperatorReady(session) ? 'Phiên đã sẵn sàng' : 'Phiên còn chặn'} tone={isStaffSessionOperatorReady(session) ? 'success' : 'warning'} />
          </>
        )}
      />

      <Alert
        type={blockers.length > 0 ? 'warning' : 'success'}
        showIcon
        title={blockers.length > 0 ? 'Có việc cần xử lý trước khi đi sâu vào ca làm việc' : 'Ca làm việc đã đủ điều kiện để tiếp tục'}
        description={blockers.length > 0
          ? blockers.join(' ')
          : 'Staff-web sẽ ưu tiên điều hướng theo bước công việc an toàn thay vì chỉ lấy capability đầu tiên trong danh sách.'}
      />

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card title="Chi nhánh đang neo ngữ cảnh">
            <Space orientation="vertical" size={8}>
              <Typography.Text strong>
                {defaultBranch
                  ? `${defaultBranch.branch_name} (${defaultBranch.branch_code})`
                  : 'Chưa có chi nhánh mặc định'}
              </Typography.Text>
              <Typography.Text type="secondary">
                {branchId
                  ? `Flow store đang giữ chi nhánh #${branchId} làm ngữ cảnh hiện tại.`
                  : 'Flow store chưa có chi nhánh hoạt động, nên không nên tiếp tục các luồng gắn bàn hoặc tài chính.'}
              </Typography.Text>
              <StatusChip label={readiness.branch} tone={readiness.branch === 'ready' ? 'success' : 'warning'} />
            </Space>
          </Card>
        </Col>

        <Col xs={24} md={8}>
          <Card title="Ca thu ngân">
            <Space orientation="vertical" size={8}>
              <Typography.Text strong>
                {activeShift ? `${activeShift.shift_code} (${activeShift.status})` : 'Chưa có ca thu ngân đang mở'}
              </Typography.Text>
              <Typography.Text type="secondary">
                {activeShift
                  ? `Thiết bị ${activeShift.terminal_code ?? 'không rõ'} • Chi nhánh ${activeShift.branch?.branch_code ?? activeShift.branch_id}`
                  : financeBlocked
                    ? 'Mở ca thu ngân trước khi tiếp tục thanh toán hoặc đối soát.'
                    : 'Phiên này không yêu cầu ca thu ngân để mở luồng nghiệp vụ đang được cấp.'}
              </Typography.Text>
              <StatusChip label={readiness.cashier_shift} tone={paymentTone(readiness.cashier_shift)} />
            </Space>
          </Card>
        </Col>

        <Col xs={24} md={8}>
          <Card title="Độ sẵn sàng phiên">
            <Space orientation="vertical" size={8}>
              <Typography.Text strong>
                {isStaffSessionOperatorReady(session) ? 'Phiên đủ điều kiện vận hành' : 'Phiên còn thiếu điều kiện'}
              </Typography.Text>
              <Typography.Text type="secondary">
                Đã cấp {readiness.granted_capability_count} trên {readiness.known_capability_count} capability đã biết cho staff-web.
              </Typography.Text>
              <StatusChip label={isStaffSessionOperatorReady(session) ? 'ready' : 'action_required'} tone={isStaffSessionOperatorReady(session) ? 'success' : 'warning'} />
            </Space>
          </Card>
        </Col>
      </Row>

      <div className="staff-task-grid">
        <Card className="staff-task-card staff-task-card-primary" title="Bước nên làm ngay">
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <Typography.Paragraph style={{ marginBottom: 0 }}>
              {recommendedItem
                ? recommendedItem.description
                : 'Khi startup chưa đủ tin cậy, hãy xử lý các cảnh báo ở trên trước khi mở luồng nghiệp vụ khác.'}
            </Typography.Paragraph>

            <div className="staff-action-row">
              {recommendedItem ? (
                <Button type="primary" onClick={() => navigate(recommendedItem.path)}>
                  Mở {recommendedItem.label}
                </Button>
              ) : null}
              {resumeTarget && resumeTarget.path !== recommendedItem?.path ? (
                <Button onClick={() => navigate(resumeTarget.path)}>
                  {resumeTarget.label}
                </Button>
              ) : null}
              {financeBlocked && cashierShiftAvailable && recommendedItem?.path !== '/cashier-shift' ? (
                <Button onClick={() => navigate('/cashier-shift')}>
                  Mở ca thu ngân
                </Button>
              ) : null}
            </div>

            {resumeTarget ? (
              <Typography.Text type="secondary">
                Staff-web vẫn nhớ ngữ cảnh đang mở gần nhất và chỉ cho phép tiếp tục nó khi startup hiện tại vẫn an toàn.
              </Typography.Text>
            ) : null}
          </Space>
        </Card>

        <Card className="staff-task-card" title="Vùng công việc đang được cấp">
          <div className="staff-task-list">
            {navigationItems.map((item) => {
              const blockedByShift = financeBlocked && (item.path === '/checkout' || item.path === '/finance-review');

              return (
                <div key={item.path} className="staff-task-list-item">
                  <div className="staff-task-list-copy">
                    <Typography.Text strong>{item.label}</Typography.Text>
                    <Typography.Text type="secondary">
                      {blockedByShift ? `${item.description} Luồng này đang chờ ca thu ngân.` : item.description}
                    </Typography.Text>
                  </div>
                  <Button
                    key={item.path}
                    type={item.path === recommendedItem?.path ? 'primary' : 'default'}
                    disabled={blockedByShift}
                    onClick={() => navigate(item.path)}
                  >
                    {blockedByShift ? 'Cần mở ca trước' : 'Mở'}
                  </Button>
                </div>
              );
            })}
          </div>
        </Card>
      </div>

      <Card title="Công việc có thể tiếp tục ngay">
        {workItems.length === 0 ? (
          <Typography.Text type="secondary">
            Chưa có luồng nào được ghim hoặc chạm gần đây trong phiên hiện tại.
          </Typography.Text>
        ) : (
          <div className="staff-task-list">
            {workItems.slice(0, 4).map((item) => (
              <div key={item.key} className="staff-task-list-item">
                <div className="staff-task-list-copy">
                  <Typography.Text strong>{item.label}</Typography.Text>
                  <Typography.Text type="secondary">
                    {item.subtitle ?? 'Luồng đang dở có thể mở lại ngay từ access gate.'}
                  </Typography.Text>
                </div>
                <Button type={item.pinned ? 'primary' : 'default'} onClick={() => navigate(item.path)}>
                  {item.pinned ? 'Mở việc ghim' : 'Tiếp tục'}
                </Button>
              </div>
            ))}
          </div>
        )}
      </Card>
    </Space>
  );
}
