import { Button, Card, Typography } from 'antd';
import { EmptyBlock, InlineError, InlineLoading } from '../../../components/states/StateBlocks';
import type { DashboardCashierSnapshotModel } from '../dashboard-view-model';

export function CashierSnapshotCard({
  snapshot,
  onOpen,
  loading = false,
  error = null,
}: {
  snapshot: DashboardCashierSnapshotModel;
  onOpen: (path: string) => void;
  loading?: boolean;
  error?: string | null;
}) {
  return (
    <Card className="staff-dashboard-card staff-dashboard-card-finance">
      <div className="staff-dashboard-card-head">
        <div>
          <Typography.Title level={4}>{snapshot.title}</Typography.Title>
          <Typography.Paragraph type="secondary" className="staff-dashboard-card-description">
            {snapshot.description}
          </Typography.Paragraph>
          {snapshot.reviewLabel ? (
            <Typography.Paragraph className="staff-dashboard-card-priority-hint" type="secondary">
              {snapshot.reviewLabel}
            </Typography.Paragraph>
          ) : null}
        </div>

        <div className="staff-dashboard-card-head-actions">
          {snapshot.urgencyLabel ? (
            <span className={`staff-dashboard-urgency-badge staff-dashboard-urgency-badge-${snapshot.urgencyTone ?? 'default'}`}>
              {snapshot.urgencyLabel}
            </span>
          ) : null}
          <Button type="link" className="staff-link-button" onClick={() => onOpen(snapshot.path)}>
            {snapshot.actionLabel}
          </Button>
        </div>
      </div>

      <div className="staff-dashboard-metric-grid">
        {snapshot.metrics.map((metric) => (
          <div
            key={`${snapshot.title}-${metric.label}`}
            className={`staff-dashboard-metric-card staff-dashboard-metric-card-${metric.tone ?? 'default'}`}
          >
            <span className="staff-dashboard-metric-label">{metric.label}</span>
            <span className="staff-dashboard-metric-value">{metric.value}</span>
            {metric.hint ? <span className="staff-dashboard-metric-hint">{metric.hint}</span> : null}
          </div>
        ))}
      </div>

      {loading ? <InlineLoading tip="Đang tải ca thu ngân..." /> : null}
      {error ? <InlineError message={error} /> : null}
      {!loading && !error && snapshot.notes.length === 0 ? (
        <EmptyBlock
          title="Chưa có ghi chú ca"
          description="Khi ca thu ngân có giao dịch, phần này sẽ hiển thị các phương thức thanh toán nổi bật."
        />
      ) : null}

      {!loading && !error && snapshot.notes.length > 0 ? (
        <div className="staff-dashboard-note-list">
          {snapshot.notes.map((note) => (
            <div key={note} className="staff-dashboard-note-item">
              <Typography.Text>{note}</Typography.Text>
            </div>
          ))}
        </div>
      ) : null}
    </Card>
  );
}
