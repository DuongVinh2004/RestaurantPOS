import { Button, Card, Typography } from 'antd';
import { ShieldCheck } from 'lucide-react';
import type { DashboardShiftHealthModel } from '../dashboard-view-model';

export function ShiftHealthCard({
  health,
  lastUpdatedLabel,
  onOpen,
}: {
  health: DashboardShiftHealthModel;
  lastUpdatedLabel: string;
  onOpen: (path: string) => void;
}) {
  return (
    <Card className="staff-dashboard-card staff-dashboard-health-card">
      <div className="staff-dashboard-health-shell">
        <div className="staff-dashboard-health-copy">
          <div className="staff-dashboard-health-title-row">
            <span className={`staff-dashboard-health-status staff-dashboard-health-status-${health.statusTone}`}>
              <ShieldCheck size={15} strokeWidth={2.1} />
              {health.statusLabel}
            </span>
            <Typography.Text type="secondary">Cập nhật {lastUpdatedLabel}</Typography.Text>
          </div>

          <Typography.Title level={3}>{health.title}</Typography.Title>
          <Typography.Paragraph type="secondary" className="staff-dashboard-card-description">
            {health.summary}
          </Typography.Paragraph>
        </div>

        <div className="staff-dashboard-health-actions">
          {health.actions.map((action) => (
            <Button
              key={`${health.title}-${action.path}`}
              type={action.tone === 'primary' ? 'primary' : 'default'}
              onClick={() => onOpen(action.path)}
            >
              {action.label}
            </Button>
          ))}
        </div>
      </div>

      <div className="staff-dashboard-health-metrics">
        {health.metrics.map((metric) => (
          <div
            key={`${health.title}-${metric.label}`}
            className={`staff-dashboard-metric-card staff-dashboard-metric-card-${metric.tone ?? 'default'}`}
          >
            <span className="staff-dashboard-metric-label">{metric.label}</span>
            <span className="staff-dashboard-metric-value">{metric.value}</span>
            {metric.hint ? (
              <span className="staff-dashboard-metric-hint">{metric.hint}</span>
            ) : null}
          </div>
        ))}
      </div>
    </Card>
  );
}
