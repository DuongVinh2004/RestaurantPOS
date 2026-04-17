import type { ReactNode } from 'react';
import type { StatusChipAppearance, StatusChipVariant, StatusTone } from '../../status/status';
import { translateUiLabel } from '../../utils/translation';

export function StatusChip({
  label,
  tone = 'default',
  variant = 'entity',
  appearance,
  icon,
  className,
}: {
  label: string;
  tone?: StatusTone;
  variant?: StatusChipVariant;
  appearance?: StatusChipAppearance;
  icon?: ReactNode;
  className?: string;
}) {
  const resolvedAppearance = appearance ?? resolveAppearance(variant);

  return (
    <span
      className={[
        'staff-status-chip',
        `staff-status-chip-${variant}`,
        `staff-status-chip-${tone}`,
        `staff-status-chip-${resolvedAppearance}`,
        className,
      ].filter(Boolean).join(' ')}
    >
      {icon ? <span className="staff-status-chip-icon" aria-hidden="true">{icon}</span> : null}
      <span className="staff-status-chip-label">{translateUiLabel(label)}</span>
    </span>
  );
}

function resolveAppearance(variant: StatusChipVariant): StatusChipAppearance {
  switch (variant) {
    case 'severity':
      return 'filled';
    case 'freshness':
      return 'outline';
    case 'count':
      return 'soft';
    default:
      return 'soft';
  }
}
