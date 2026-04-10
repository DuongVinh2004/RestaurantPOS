import { beforeEach, describe, expect, it } from 'vitest';
import { buildStaffSession } from '../../test/fixtures';
import { useFlowStore } from './flow-store';

const initialState = useFlowStore.getState();

describe('flow-store', () => {
  beforeEach(() => {
    sessionStorage.clear();
    useFlowStore.setState(initialState, true);
  });

  it('clears stale route-owned ids when the next journey omits them', () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 7,
      selectedTableId: 11,
      selectedReservationId: 22,
      selectedReservationRowVersion: 3,
      selectedOrderId: 33,
      selectedOrderRowVersion: 4,
      selectedStationId: 44,
      source: 'board',
    });

    useFlowStore.getState().applyJourney({ source: 'order' });

    expect(useFlowStore.getState()).toMatchObject({
      branchId: 7,
      selectedTableId: null,
      selectedReservationId: null,
      selectedReservationRowVersion: null,
      selectedOrderId: null,
      selectedOrderRowVersion: null,
      selectedStationId: null,
      source: 'order',
    });
  });

  it('clears core context when the operator switches branch', () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      branchId: 1,
      selectedTableId: 11,
      selectedReservationId: 22,
      selectedReservationRowVersion: 3,
      selectedOrderId: 33,
      selectedOrderRowVersion: 4,
      selectedStationId: 44,
      source: 'checkout',
    });

    useFlowStore.getState().setBranchId(2);

    expect(useFlowStore.getState()).toMatchObject({
      branchId: 2,
      selectedTableId: null,
      selectedReservationId: null,
      selectedReservationRowVersion: null,
      selectedOrderId: null,
      selectedOrderRowVersion: null,
      selectedStationId: null,
      source: undefined,
    });
  });

  it('preserves branch for the same session owner and resets context for a new owner', () => {
    useFlowStore.setState({
      ...useFlowStore.getState(),
      sessionOwnerKey: 17,
      branchId: 9,
      selectedTableId: 11,
      selectedReservationId: 22,
      selectedReservationRowVersion: 3,
      selectedOrderId: 33,
      selectedOrderRowVersion: 4,
      selectedStationId: 44,
      source: 'board',
    });

    useFlowStore.getState().syncSessionContext(makeSession(17, 1));

    expect(useFlowStore.getState()).toMatchObject({
      sessionOwnerKey: 17,
      branchId: 9,
      selectedTableId: 11,
      selectedReservationId: 22,
      selectedOrderId: 33,
      selectedStationId: 44,
      source: 'board',
    });

    useFlowStore.getState().syncSessionContext(makeSession(18, 2));

    expect(useFlowStore.getState()).toMatchObject({
      sessionOwnerKey: 18,
      branchId: 2,
      selectedTableId: null,
      selectedReservationId: null,
      selectedReservationRowVersion: null,
      selectedOrderId: null,
      selectedOrderRowVersion: null,
      selectedStationId: null,
      source: undefined,
    });
  });
});

function makeSession(staffApiKeyId: number, defaultBranchId: number) {
  const base = buildStaffSession();
  const defaultBranch = base.startup.default_branch
    ? {
      ...base.startup.default_branch,
      branch_id: defaultBranchId,
      branch_code: `BR-${defaultBranchId}`,
      branch_name: `Branch ${defaultBranchId}`,
    }
    : null;

  return buildStaffSession({
    staff_api_key_id: staffApiKeyId,
    startup: {
      ...base.startup,
      default_branch: defaultBranch,
    },
  });
}
