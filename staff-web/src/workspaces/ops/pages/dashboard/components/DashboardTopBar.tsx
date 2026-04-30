import { Button, Typography } from 'antd';
import { AlertTriangle, Clock3, MoveRight, RefreshCcw, ShieldCheck, WalletCards } from 'lucide-react';
import type { DashboardAlertModel, DashboardFocusModel } from '../dashboard-view-model';
import type { StatusTone } from '../../../../../shared/status/status';

export function DashboardTopBar({
  focus,
  shiftLabel,
  readinessLabel,
  readinessTone,
  updatedLabel,
  freshnessTone,
  primaryAlert,
  activityItems,
  onOpen,
  onRefresh,
  refreshing = false,
}: {
  focus: DashboardFocusModel;
  shiftLabel: string;
  readinessLabel: string;
  readinessTone: StatusTone;
  updatedLabel: string;
  freshnessTone: StatusTone;
  primaryAlert: DashboardAlertModel | null;
  activityItems: Array<string>;
  onOpen: (path: string) => void;
  onRefresh: () => void | Promise<void>;
  refreshing?: boolean;
}) {
  return (
    <section className="staff-dashboard-topbar">
      <div className="staff-dashboard-topbar-copy">
        <Typography.Text className="staff-eyebrow">{focus.roleLabel}</Typography.Text>
        <Typography.Title level={1} className="staff-dashboard-topbar-title">
          {focus.title}
        </Typography.Title>
        <Typography.Paragraph className="staff-dashboard-topbar-description" type="secondary">
          {focus.description}
        </Typography.Paragraph>

        <div className="staff-dashboard-topbar-context">
          <span className={`staff-dashboard-context-chip staff-dashboard-context-chip-${freshnessTone}`}>
            <Clock3 size={13} strokeWidth={2.1} />
            {updatedLabel}
          </span>
          <span className={`staff-dashboard-context-chip staff-dashboard-context-chip-${readinessTone}`}>
            <ShieldCheck size={13} strokeWidth={2.1} />
            {readinessLabel}
          </span>
          <span className="staff-dashboard-context-chip">
            <WalletCards size={13} strokeWidth={2.1} />
            {shiftLabel}
          </span>
        </div>
      </div>

      <div className="staff-dashboard-topbar-meta">
        <TopBarMetric label="Ca hiện tại" value={shiftLabel} tone={shiftLabel === 'Chưa có ca thu ngân' ? 'warning' : 'success'} />
        <TopBarMetric label="Trạng thái" value={readinessLabel} tone={readinessTone} />
        <TopBarMetric label="Dữ liệu" value={updatedLabel} tone={freshnessTone} />
      </div>

      <div className="staff-dashboard-topbar-controls">
        <div className={`staff-dashboard-priority-panel staff-dashboard-priority-panel-${primaryAlert?.tone ?? 'neutral'}`}>
          <div className="staff-dashboard-priority-head">
            <Typography.Text className="staff-eyebrow">
              {primaryAlert ? `Việc số 1 • ${primaryAlert.groupLabel}` : 'Ổn định vận hành'}
            </Typography.Text>
            {primaryAlert?.ageLabel ? (
              <Typography.Text type="secondary">{primaryAlert.ageLabel}</Typography.Text>
            ) : null}
          </div>

          <Typography.Title level={4} className="staff-dashboard-priority-title">
            {primaryAlert?.title ?? 'Ca đang ổn định'}
          </Typography.Title>
          <Typography.Paragraph type="secondary" className="staff-dashboard-priority-description">
            {primaryAlert?.description ?? 'Chưa có hàng đợi nào vượt ngưỡng trong dashboard này.'}
          </Typography.Paragraph>

          <div className="staff-dashboard-topbar-actions">
            {primaryAlert ? (
              <Button type="primary" onClick={() => onOpen(primaryAlert.path)}>
                {primaryAlert.actionLabel}
                <MoveRight size={15} strokeWidth={2.1} />
              </Button>
            ) : null}
            <Button className="staff-dashboard-refresh-button" icon={<RefreshCcw size={16} />} loading={refreshing} onClick={() => void onRefresh()}>
              Làm mới snapshot
            </Button>
          </div>
        </div>

        {activityItems.length > 0 ? (
          <div className="staff-dashboard-activity-strip">
            <Typography.Text className="staff-eyebrow">Dòng nóng đang chạy</Typography.Text>
            <div className="staff-dashboard-activity-list">
              {activityItems.map((item) => (
                <span key={item} className="staff-dashboard-activity-chip">
                  <AlertTriangle size={13} strokeWidth={2.1} />
                  {item}
                </span>
              ))}
            </div>
          </div>
        ) : null}
      </div>
    </section>
  );
}

function TopBarMetric({
  label,
  value,
  tone,
}: {
  label: string;
  value: string;
  tone: StatusTone;
}) {
  return (
    <div className={`staff-dashboard-topbar-metric staff-dashboard-topbar-metric-${tone}`}>
      <div className="staff-dashboard-topbar-metric-copy">
        <span className="staff-dashboard-topbar-metric-label">{label}</span>
        <span className="staff-dashboard-topbar-metric-value">{value}</span>
      </div>
    </div>
  );
}
