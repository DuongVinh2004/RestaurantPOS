import { cloneElement, type ReactElement, type ReactNode } from 'react';
import { Tooltip } from 'antd';
import { can, hasAny } from '../../core/permissions/capabilities';
import { useAuthStore } from '../../app/store/auth-store';

export function CapabilityGuard({
  capability,
  anyOf,
  children,
  fallback = null,
}: {
  capability?: string;
  anyOf?: Array<string>;
  children: ReactNode;
  fallback?: ReactNode;
}) {
  const session = useAuthStore((state) => state.session);
  const allowed = capability ? can(session, capability) : anyOf ? hasAny(session, anyOf) : true;

  return allowed ? <>{children}</> : <>{fallback}</>;
}

export function PermissionAction({
  capability,
  tooltip,
  children,
}: {
  capability: string;
  tooltip?: string;
  children: ReactElement<{ disabled?: boolean }>;
}) {
  const session = useAuthStore((state) => state.session);
  const allowed = can(session, capability);

  return (
    <Tooltip title={!allowed ? tooltip ?? `Missing capability: ${capability}` : undefined}>
      <span>
        {cloneElement(children, {
          disabled: children.props.disabled || !allowed,
        })}
      </span>
    </Tooltip>
  );
}
