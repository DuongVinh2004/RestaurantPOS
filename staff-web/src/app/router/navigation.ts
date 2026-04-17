import type { StaffSession } from '../../shared/auth/storage';
import type {
  StaffNavGroup,
  StaffNavItem,
  StaffWorkspaceNavigationDefinition,
  StaffWorkspaceNavigationOption,
} from '../../workspaces/navigation/types';
import { can } from '../../shared/auth/capabilities';
import { resolveAvailableWorkspaces, type WorkspaceId } from '../../workspaces/workspaces';
import { adminNavigation } from '../../workspaces/navigation/admin-navigation';
import { kitchenNavigation } from '../../workspaces/navigation/kitchen-navigation';
import { opsNavigation } from '../../workspaces/navigation/ops-navigation';

export const workspaceNavigationRegistry: Record<WorkspaceId, StaffWorkspaceNavigationDefinition> = {
  ops: opsNavigation,
  kitchen: kitchenNavigation,
  admin: adminNavigation,
};

function itemIsVisible(session: StaffSession, item: StaffNavItem): boolean {
  return !item.capability || can(session, item.capability);
}

function itemMatchesPath(item: StaffNavItem, pathname: string): boolean {
  if (item.exact) {
    return pathname === item.path;
  }

  if (pathname === item.path || pathname.startsWith(`${item.path}/`)) {
    return true;
  }

  return (item.matchPaths ?? []).some((path) => pathname === path || pathname.startsWith(`${path}/`));
}

export function getWorkspaceNavigationDefinition(workspace: WorkspaceId): StaffWorkspaceNavigationDefinition {
  return workspaceNavigationRegistry[workspace];
}

export function visibleWorkspaceNavigation(
  session: StaffSession | null,
  workspace: WorkspaceId,
): Array<StaffNavItem> {
  if (!session) {
    return [];
  }

  return getWorkspaceNavigationDefinition(workspace).groups.flatMap((group) => (
    group.items.filter((item) => itemIsVisible(session, item))
  ));
}

export function visibleWorkspaceNavigationGroups(
  session: StaffSession | null,
  workspace: WorkspaceId,
  badgeCounts: Partial<Record<string, number>> = {},
): Array<StaffNavGroup> {
  if (!session) {
    return [];
  }

  return getWorkspaceNavigationDefinition(workspace).groups
    .map((group) => ({
      ...group,
      items: group.items
        .filter((item) => itemIsVisible(session, item))
        .map((item) => ({
          ...item,
          badgeCount: badgeCounts[item.key] ?? null,
        })),
    }))
    .filter((group) => group.items.length > 0);
}

export function findWorkspaceNavigationItem(
  session: StaffSession | null,
  workspace: WorkspaceId,
  pathname: string,
): StaffNavItem | null {
  return visibleWorkspaceNavigation(session, workspace).find((item) => itemMatchesPath(item, pathname)) ?? null;
}

export function resolveWorkspaceLandingPath(
  session: StaffSession | null,
  workspace: WorkspaceId,
): string {
  const definition = getWorkspaceNavigationDefinition(workspace);

  if (session) {
    const visibleItems = visibleWorkspaceNavigation(session, workspace);
    const landingItem = visibleItems.find((item) => item.path === definition.landingPath);

    if (landingItem) {
      return landingItem.path;
    }

    const firstVisibleItem = visibleItems[0];
    if (firstVisibleItem) {
      return firstVisibleItem.path;
    }
  }

  return definition.landingPath;
}

export function resolveWorkspaceForPath(
  session: StaffSession | null,
  pathname: string,
): WorkspaceId | null {
  if (!session) {
    return null;
  }

  return resolveAvailableWorkspaces(session).find((workspace) => (
    visibleWorkspaceNavigation(session, workspace).some((item) => itemMatchesPath(item, pathname))
  )) ?? null;
}

export function resolveWorkspaceNavigationOptions(
  session: StaffSession | null,
  availableWorkspaces: Array<WorkspaceId>,
): Array<StaffWorkspaceNavigationOption> {
  if (!session) {
    return [];
  }

  return availableWorkspaces
    .filter((workspace) => visibleWorkspaceNavigation(session, workspace).length > 0)
    .map((workspace) => {
      const definition = getWorkspaceNavigationDefinition(workspace);

      return {
        workspace,
        label: definition.label,
        description: definition.description,
        landingPath: resolveWorkspaceLandingPath(session, workspace),
      };
    });
}

export type StaffWorkspaceNavigationSection = StaffWorkspaceNavigationOption & {
  items: Array<StaffNavItem>;
};

export function resolveWorkspaceNavigationSections(
  session: StaffSession | null,
  availableWorkspaces?: Array<WorkspaceId>,
): Array<StaffWorkspaceNavigationSection> {
  if (!session) {
    return [];
  }

  const resolvedWorkspaces = availableWorkspaces ?? resolveAvailableWorkspaces(session);

  return resolveWorkspaceNavigationOptions(session, resolvedWorkspaces)
    .map((option) => ({
      ...option,
      items: visibleWorkspaceNavigation(session, option.workspace),
    }))
    .filter((section) => section.items.length > 0);
}
