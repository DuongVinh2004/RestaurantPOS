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
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '16px', width: '100%', background: 'transparent' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: '16px', flexWrap: 'wrap' }}>
        <span className={`staff-dashboard-health-status staff-dashboard-health-status-${health.statusTone}`} style={{ margin: 0, padding: '4px 8px', borderRadius: '4px', fontSize: '12px' }}>
          <ShieldCheck size={14} strokeWidth={2.1} style={{ marginRight: '4px' }} />
          {health.statusLabel}
        </span>
        
        <div style={{ display: 'flex', gap: '12px', fontSize: '12px' }}>
          {health.metrics.map((metric) => (
             <div key={`${health.title}-${metric.label}`}>
               <span style={{ color: '#6b7280', marginRight: '4px', textTransform: 'uppercase', fontSize: '10px', letterSpacing: '0.5px' }}>{metric.label}:</span>
               <span style={{ fontWeight: 600 }}>{metric.value}</span>
             </div>
          ))}
        </div>
      </div>

      <div style={{ display: 'flex', alignItems: 'center', gap: '12px', flexWrap: 'wrap' }}>
        <Typography.Text type="secondary" style={{ fontSize: '11px' }}>
          Cập nhật {lastUpdatedLabel}
        </Typography.Text>
        <div style={{ display: 'flex', gap: '8px' }}>
          {health.actions.map((action) => (
            <Button
              key={`${health.title}-${action.path}`}
              type={action.tone === 'primary' ? 'primary' : 'default'}
              size="small"
              onClick={() => onOpen(action.path)}
            >
              {action.label}
            </Button>
          ))}
        </div>
      </div>
    </div>
  );
}
