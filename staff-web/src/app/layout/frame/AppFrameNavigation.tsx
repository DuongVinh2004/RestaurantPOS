import {
  Armchair,
  BarChart3,
  Boxes,
  CalendarClock,
  ChefHat,
  ClipboardList,
  Clock3,
  LayoutDashboard,
  MessagesSquare,
  ReceiptText,
  Settings2,
  ShieldCheck,
  WalletCards,
  type LucideIcon,
} from 'lucide-react';
import type { StaffNavGroup, StaffNavIconKey } from '../../../workspaces/navigation/types';
import { AppFramePermissionState } from './AppFrameState';

const navIcons: Record<StaffNavIconKey, LucideIcon> = {
  dashboard: LayoutDashboard,
  settings: Settings2,
  inventory: Boxes,
  menu: ClipboardList,
  tables: Armchair,
  reservations: CalendarClock,
  waiting: Clock3,
  orders: ClipboardList,
  kitchen: ChefHat,
  checkout: ReceiptText,
  cashier: WalletCards,
  finance: ShieldCheck,
  conversations: MessagesSquare,
  audit: ShieldCheck,
  reporting: BarChart3,
};

export function AppFrameNavigation({
  brandEyebrow,
  brandTitle,
  brandCopy,
  navigationGroups,
  selectedMenuKey,
  onOpenPath,
}: {
  brandEyebrow: string;
  brandTitle: string;
  brandCopy: string;
  navigationGroups: Array<StaffNavGroup>;
  selectedMenuKey?: string;
  onOpenPath: (path: string) => void;
}) {
  return (
    <>
      <div className="staff-sider-brand">
        <span className="staff-eyebrow">{brandEyebrow}</span>
        <h2 className="staff-sider-brand-title">{brandTitle}</h2>
        <p className="staff-sider-brand-copy">{brandCopy}</p>
      </div>

      {navigationGroups.length > 0 ? (
        <nav className="staff-shell-menu" aria-label="Staff workspace navigation">
          {navigationGroups.map((group) => (
            <section key={group.key} className="staff-shell-nav-group">
              <span className="staff-nav-group-label">{group.label}</span>

              <div className="staff-shell-nav-list">
                {group.items.map((item) => {
                  const Icon = navIcons[item.iconKey];
                  const isSelected = item.key === selectedMenuKey;
                  const badgeLabel = typeof item.badgeCount === 'number' && item.badgeCount > 99
                    ? '99+'
                    : item.badgeCount;

                  return (
                    <button
                      key={item.key}
                      type="button"
                      className={`staff-shell-nav-item ${isSelected ? 'staff-shell-nav-item-selected' : ''}`}
                      aria-current={isSelected ? 'page' : undefined}
                      onClick={() => onOpenPath(item.path)}
                    >
                      <span className="staff-nav-item-icon" aria-hidden="true">
                        <Icon size={17} strokeWidth={2.1} />
                      </span>

                      <span className="staff-nav-item-row">
                        <span className="staff-nav-item-title">{item.label}</span>
                        {typeof item.badgeCount === 'number' && item.badgeCount > 0 ? (
                          <span className="staff-nav-item-badge">{badgeLabel}</span>
                        ) : null}
                      </span>
                    </button>
                  );
                })}
              </div>
            </section>
          ))}
        </nav>
      ) : (
        <div className="staff-shell-nav-empty">
          <AppFramePermissionState
            title="No routes are available"
            description="Capability guards did not expose any destination in this workspace for the current session."
          />
        </div>
      )}
    </>
  );
}
