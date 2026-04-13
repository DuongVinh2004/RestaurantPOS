import {
  BarChart3,
  Building2,
  CalendarClock,
  ChefHat,
  MessagesSquare,
  MoveRight,
  ShieldCheck,
  Users,
  WalletCards,
  type LucideIcon,
} from 'lucide-react';
import type { DashboardAlertIconKey, DashboardAlertModel } from '../dashboard-view-model';

const iconMap: Record<DashboardAlertIconKey, LucideIcon> = {
  reservation: CalendarClock,
  waiting: Users,
  kitchen: ChefHat,
  finance: WalletCards,
  cashier: WalletCards,
  conversation: MessagesSquare,
  branch: Building2,
  reporting: BarChart3,
  stable: ShieldCheck,
};

export function UrgentAlertCard({
  alert,
  onOpen,
}: {
  alert: DashboardAlertModel;
  onOpen: (path: string) => void;
}) {
  const Icon = iconMap[alert.iconKey];

  return (
    <button
      type="button"
      className={`staff-dashboard-alert-card staff-dashboard-alert-card-${alert.tone}`}
      onClick={() => onOpen(alert.path)}
    >
      <div className="staff-dashboard-alert-meta-row">
        {alert.groupLabel ? (
          <span className={`staff-dashboard-alert-band staff-dashboard-alert-band-${alert.tone}`}>{alert.groupLabel}</span>
        ) : null}
        {alert.ageLabel ? (
          <span className="staff-dashboard-alert-age">{alert.ageLabel}</span>
        ) : null}
      </div>

      <div className="staff-dashboard-alert-header">
        <span className="staff-dashboard-alert-icon" aria-hidden="true">
          <Icon size={18} strokeWidth={2.1} />
        </span>
        <span className="staff-dashboard-alert-value">{alert.value}</span>
      </div>

      <div className="staff-dashboard-alert-copy">
        <span className="staff-dashboard-alert-title">{alert.title}</span>
        <span className="staff-dashboard-alert-description">{alert.description}</span>
      </div>

      <span className="staff-dashboard-card-link staff-dashboard-card-link-strong">
        {alert.actionLabel}
        <MoveRight size={14} strokeWidth={2.2} />
      </span>
    </button>
  );
}
