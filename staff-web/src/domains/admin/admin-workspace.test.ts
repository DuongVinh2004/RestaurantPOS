import { describe, expect, it } from 'vitest';
import { buildStaffSession } from '../../test/fixtures';
import {
  groupAdminWorkspaceCards,
  resolveAdminQuickLinks,
  resolveAdminWorkspaceCards,
  summarizeAdminWorkspace,
} from './admin-workspace';

describe('admin workspace definitions', () => {
  it('marks granted admin surfaces as live or contract-ready based on route ownership', () => {
    const session = buildStaffSession({
      capabilities: ['settings.manage', 'inventory.manage', 'reporting.view'],
      known_capabilities: ['settings.manage', 'inventory.manage', 'reporting.view'],
    });

    const cards = resolveAdminWorkspaceCards(session);

    expect(cards.find((card) => card.key === 'branches-settings')?.status).toBe('live');
    expect(cards.find((card) => card.key === 'inventory-purchasing')?.status).toBe('live');
    expect(cards.find((card) => card.key === 'menu-pricing')?.status).toBe('restricted');
  });

  it('dedupes quick links when multiple domains share one route owner', () => {
    const session = buildStaffSession({
      capabilities: ['settings.manage', 'inventory.manage', 'audit.view'],
      known_capabilities: ['settings.manage', 'inventory.manage', 'audit.view'],
    });

    const quickLinks = resolveAdminQuickLinks(resolveAdminWorkspaceCards(session));

    expect(quickLinks.map((card) => card.actionPath)).toEqual([
      '/admin/settings',
      '/admin/inventory',
      '/admin/audit-trail',
    ]);
  });

  it('groups and summarizes admin ownership lanes', () => {
    const session = buildStaffSession({
      capabilities: ['settings.manage', 'inventory.manage', 'audit.view', 'reporting.view'],
      known_capabilities: ['settings.manage', 'inventory.manage', 'audit.view', 'reporting.view'],
    });

    const cards = resolveAdminWorkspaceCards(session);
    const groups = groupAdminWorkspaceCards(cards);
    const summary = summarizeAdminWorkspace(cards);

    expect(groups.map((group) => group.key)).toEqual(['control', 'catalog', 'supply', 'governance']);
    expect(summary.enabledCount).toBe(6);
    expect(summary.liveCount).toBe(6);
    expect(summary.importExportCount).toBe(4);
  });
});
