import { beforeEach, describe, expect, it } from 'vitest';
import { staffRoutePaths } from '../router/workspace-paths';
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

  it('hydrates branch and single assigned station from startup contract fields', () => {
    useFlowStore.getState().syncSessionContext(buildStaffSession({
      staff_api_key_id: 21,
      startup: {
        default_branch_id: 7,
        allowed_branch_ids: [7],
        assigned_station_ids: [501],
      },
    }));

    expect(useFlowStore.getState()).toMatchObject({
      sessionOwnerKey: 21,
      branchId: 7,
      selectedStationId: 501,
    });
  });

  it('keeps pinned work and trims recent work for quick resume', () => {
    useFlowStore.getState().touchWork({
      key: `${staffRoutePaths.ops.orders}?order_id=51`,
      path: `${staffRoutePaths.ops.orders}?order_id=51`,
      label: 'Đơn hàng',
      subtitle: 'Đơn #51',
      branchId: 1,
      pinned: true,
    });

    for (let index = 0; index < 8; index += 1) {
      useFlowStore.getState().touchWork({
        key: `${staffRoutePaths.ops.tables}?table_id=${index + 1}`,
        path: `${staffRoutePaths.ops.tables}?table_id=${index + 1}`,
        label: 'Sơ đồ bàn',
        subtitle: `Bàn ${index + 1}`,
        branchId: 1,
      });
    }

    const workItems = useFlowStore.getState().workItems;

    expect(workItems[0]).toMatchObject({
      key: `${staffRoutePaths.ops.orders}?order_id=51`,
      pinned: true,
    });
    expect(workItems).toHaveLength(7);
    expect(workItems.some((item) => item.key === `${staffRoutePaths.ops.tables}?table_id=1`)).toBe(false);
    expect(workItems.some((item) => item.key === `${staffRoutePaths.ops.tables}?table_id=8`)).toBe(true);
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
      default_branch_id: defaultBranchId,
      allowed_branch_ids: [defaultBranchId],
    },
  });
}
