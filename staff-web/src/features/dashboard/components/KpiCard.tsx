import { Armchair, Banknote, ChefHat, ClipboardList, Clock3, MoveRight, ReceiptText, type LucideIcon } from 'lucide-react';
import type { DashboardKpiIconKey, DashboardKpiModel } from '../dashboard-view-model';

const iconMap: Record<DashboardKpiIconKey, LucideIcon> = {
  service: ClipboardList,
  available: Armchair,
  waiting: Clock3,
  kitchen: ChefHat,
  finance: ReceiptText,
  revenue: Banknote,
};

export function KpiCard({
  kpi,
  onOpen,
}: {
  kpi: DashboardKpiModel;
  onOpen: (path: string) => void;
}) {
  const Icon = iconMap[kpi.iconKey];

  return (
    <button
      type="button"
      className={`staff-dashboard-kpi-card staff-dashboard-kpi-card-${kpi.tone}`}
      onClick={() => onOpen(kpi.path)}
    >
      <div className="staff-dashboard-kpi-head">
        <span className="staff-dashboard-kpi-icon" aria-hidden="true">
          <Icon size={18} strokeWidth={2.1} />
        </span>
        <span className="staff-dashboard-kpi-label">{kpi.label}</span>
      </div>

      <div className="staff-dashboard-kpi-body">
        <span className="staff-dashboard-kpi-value">{kpi.value}</span>
        {kpi.trendLabel ? (
          <span className="staff-dashboard-kpi-trend">{kpi.trendLabel}</span>
        ) : null}
      </div>

      <div className="staff-dashboard-kpi-foot">
        <span className="staff-dashboard-kpi-hint">{kpi.subtext}</span>
        <span className="staff-dashboard-card-link">
          {kpi.actionLabel}
          <MoveRight size={14} strokeWidth={2.2} />
        </span>
      </div>
    </button>
  );
}
