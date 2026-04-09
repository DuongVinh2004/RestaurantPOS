import { describe, expect, it } from 'vitest';
import { capabilitySet, hasAnyCapability, hasCapability, knownCapabilitySet } from './capabilities';
import { buildStaffSession } from '../test/fixtures';

describe('capabilities', () => {
  it('checks a direct capability in the session envelope', () => {
    const session = buildStaffSession();

    expect(hasCapability(session, 'settlement.manage')).toBe(true);
    expect(hasCapability(session, 'inventory.manage')).toBe(false);
  });

  it('supports wildcard or any-capability checks', () => {
    const session = buildStaffSession({ capabilities: ['*'], known_capabilities: [] });

    expect(hasAnyCapability(session, ['payment.refund', 'conversation.manage'])).toBe(true);
    expect(capabilitySet(session).has('*')).toBe(true);
  });

  it('does not treat known_capabilities as granted access', () => {
    const session = buildStaffSession({
      capabilities: ['table.board.view'],
      known_capabilities: ['table.board.view', 'conversation.manage'],
    });

    expect(hasCapability(session, 'conversation.manage')).toBe(false);
    expect(hasAnyCapability(session, ['conversation.manage'])).toBe(false);
    expect(capabilitySet(session)).toEqual(new Set(['table.board.view']));
    expect(knownCapabilitySet(session)).toEqual(new Set(['table.board.view', 'conversation.manage']));
  });
});
