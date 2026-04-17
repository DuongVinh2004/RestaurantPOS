import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import type { StaffSession } from '../../shared/auth/storage';
import type { JourneyContext } from '../router/journey';

export type FlowWorkItem = {
  key: string;
  path: string;
  label: string;
  subtitle?: string | null;
  branchId: number | null;
  lastTouchedAt: number;
  pinned: boolean;
};

type FlowState = {
  sessionOwnerKey: number | null;
  branchId: number | null;
  selectedTableId: number | null;
  selectedTableLabel: string | null;
  selectedReservationId: number | null;
  selectedReservationLabel: string | null;
  selectedReservationRowVersion: number | null;
  selectedOrderId: number | null;
  selectedOrderLabel: string | null;
  selectedOrderRowVersion: number | null;
  selectedStationId: number | null;
  selectedStationLabel: string | null;
  source: JourneyContext['source'];
  workItems: Array<FlowWorkItem>;
  syncSessionContext: (session: StaffSession | null) => void;
  hydrateFromSession: (session: StaffSession | null) => void;
  applyJourney: (context: JourneyContext) => void;
  setBranchId: (branchId: number | null) => void;
  setTableContext: (payload: {
    tableId: number | null;
    label?: string | null;
    source?: JourneyContext['source'];
  }) => void;
  setReservationContext: (payload: {
    reservationId: number | null;
    reservationRowVersion?: number | null;
    label?: string | null;
    source?: JourneyContext['source'];
  }) => void;
  setOrderContext: (payload: {
    orderId: number | null;
    orderRowVersion?: number | null;
    label?: string | null;
    source?: JourneyContext['source'];
  }) => void;
  setStationContext: (payload: {
    stationId: number | null;
    label?: string | null;
    source?: JourneyContext['source'];
  }) => void;
  setStationId: (stationId: number | null) => void;
  touchWork: (payload: Omit<FlowWorkItem, 'lastTouchedAt' | 'pinned'> & { pinned?: boolean }) => void;
  pinWork: (key: string) => void;
  unpinWork: (key: string) => void;
  removeWork: (key: string) => void;
  resetCoreContext: () => void;
};

const clearedCoreContext = {
  selectedTableId: null,
  selectedTableLabel: null,
  selectedReservationId: null,
  selectedReservationLabel: null,
  selectedReservationRowVersion: null,
  selectedOrderId: null,
  selectedOrderLabel: null,
  selectedOrderRowVersion: null,
  selectedStationId: null,
  selectedStationLabel: null,
  source: undefined,
} satisfies Pick<
  FlowState,
  | 'selectedTableId'
  | 'selectedTableLabel'
  | 'selectedReservationId'
  | 'selectedReservationLabel'
  | 'selectedReservationRowVersion'
  | 'selectedOrderId'
  | 'selectedOrderLabel'
  | 'selectedOrderRowVersion'
  | 'selectedStationId'
  | 'selectedStationLabel'
  | 'source'
>;

const MAX_RECENT_WORK_ITEMS = 6;

export const useFlowStore = create<FlowState>()(
  persist(
    (set) => ({
      sessionOwnerKey: null,
      branchId: null,
      selectedTableId: null,
      selectedTableLabel: null,
      selectedReservationId: null,
      selectedReservationLabel: null,
      selectedReservationRowVersion: null,
      selectedOrderId: null,
      selectedOrderLabel: null,
      selectedOrderRowVersion: null,
      selectedStationId: null,
      selectedStationLabel: null,
      source: undefined,
      workItems: [],
      syncSessionContext: (session) => {
        const nextSessionOwnerKey = session?.staff_api_key_id ?? null;
        const defaultBranchId = defaultBranchIdForSession(session);
        const defaultStationId = defaultStationIdForSession(session);

        set((state) => {
          if (!session) {
            return {
              sessionOwnerKey: null,
              branchId: null,
              workItems: [],
              ...clearedCoreContext,
            };
          }

          if (state.sessionOwnerKey !== nextSessionOwnerKey) {
            return {
              sessionOwnerKey: nextSessionOwnerKey,
              branchId: defaultBranchId,
              workItems: [],
              ...clearedCoreContext,
              selectedStationId: defaultStationId,
            };
          }

          return {
            sessionOwnerKey: nextSessionOwnerKey,
            branchId: state.branchId ?? defaultBranchId,
            selectedStationId: state.selectedStationId ?? defaultStationId,
          };
        });
      },
      hydrateFromSession: (session) => {
        const defaultBranchId = defaultBranchIdForSession(session);
        const defaultStationId = defaultStationIdForSession(session);
        set((state) => ({
          branchId: state.branchId ?? defaultBranchId,
          selectedStationId: state.selectedStationId ?? defaultStationId,
        }));
      },
      applyJourney: (context) =>
        set((state) => ({
          source: context.source,
          selectedTableId: context.tableId ?? null,
          selectedTableLabel: context.tableId && state.selectedTableId === context.tableId ? state.selectedTableLabel : null,
          selectedReservationId: context.reservationId ?? null,
          selectedReservationLabel: context.reservationId && state.selectedReservationId === context.reservationId
            ? state.selectedReservationLabel
            : null,
          selectedReservationRowVersion: context.reservationRowVersion ?? null,
          selectedOrderId: context.orderId ?? null,
          selectedOrderLabel: context.orderId && state.selectedOrderId === context.orderId ? state.selectedOrderLabel : null,
          selectedOrderRowVersion: context.orderRowVersion ?? null,
          selectedStationId: context.stationId ?? null,
          selectedStationLabel: context.stationId && state.selectedStationId === context.stationId ? state.selectedStationLabel : null,
        })),
      setBranchId: (branchId) =>
        set((state) => (
          state.branchId === branchId
            ? { branchId }
            : {
              branchId,
              ...clearedCoreContext,
            }
        )),
      setTableContext: ({ tableId, label, source }) =>
        set((state) => ({
          selectedTableId: tableId,
          selectedTableLabel: tableId === null ? null : label ?? (state.selectedTableId === tableId ? state.selectedTableLabel : null),
          source,
        })),
      setReservationContext: ({ reservationId, reservationRowVersion, label, source }) =>
        set((state) => ({
          selectedReservationId: reservationId,
          selectedReservationLabel: reservationId === null
            ? null
            : label ?? (state.selectedReservationId === reservationId ? state.selectedReservationLabel : null),
          selectedReservationRowVersion: reservationRowVersion ?? null,
          source,
        })),
      setOrderContext: ({ orderId, orderRowVersion, label, source }) =>
        set((state) => ({
          selectedOrderId: orderId,
          selectedOrderLabel: orderId === null ? null : label ?? (state.selectedOrderId === orderId ? state.selectedOrderLabel : null),
          selectedOrderRowVersion: orderRowVersion ?? null,
          source,
        })),
      setStationContext: ({ stationId, label, source }) =>
        set((state) => ({
          selectedStationId: stationId,
          selectedStationLabel: stationId === null
            ? null
            : label ?? (state.selectedStationId === stationId ? state.selectedStationLabel : null),
          source: source ?? state.source,
        })),
      setStationId: (selectedStationId) => set({ selectedStationId, selectedStationLabel: null }),
      touchWork: (payload) =>
        set((state) => ({
          workItems: upsertWorkItem(state.workItems, payload),
        })),
      pinWork: (key) =>
        set((state) => ({
          workItems: state.workItems
            .map((item) => (item.key === key ? { ...item, pinned: true } : item))
            .sort(sortWorkItems),
        })),
      unpinWork: (key) =>
        set((state) => ({
          workItems: state.workItems
            .map((item) => (item.key === key ? { ...item, pinned: false } : item))
            .sort(sortWorkItems),
        })),
      removeWork: (key) =>
        set((state) => ({
          workItems: state.workItems.filter((item) => item.key !== key),
        })),
      resetCoreContext: () => set(clearedCoreContext),
    }),
    {
      name: 'restaurantpos.staff-web.flow',
      storage: createJSONStorage(() => sessionStorage),
      partialize: (state) => ({
        sessionOwnerKey: state.sessionOwnerKey,
        branchId: state.branchId,
        selectedTableId: state.selectedTableId,
        selectedTableLabel: state.selectedTableLabel,
        selectedReservationId: state.selectedReservationId,
        selectedReservationLabel: state.selectedReservationLabel,
        selectedReservationRowVersion: state.selectedReservationRowVersion,
        selectedOrderId: state.selectedOrderId,
        selectedOrderLabel: state.selectedOrderLabel,
        selectedOrderRowVersion: state.selectedOrderRowVersion,
        selectedStationId: state.selectedStationId,
        selectedStationLabel: state.selectedStationLabel,
        source: state.source,
        workItems: state.workItems,
      }),
    },
  ),
);

function upsertWorkItem(
  currentItems: Array<FlowWorkItem>,
  payload: Omit<FlowWorkItem, 'lastTouchedAt' | 'pinned'> & { pinned?: boolean },
): Array<FlowWorkItem> {
  const existing = currentItems.find((item) => item.key === payload.key);
  const nextItem: FlowWorkItem = {
    ...existing,
    ...payload,
    pinned: payload.pinned ?? existing?.pinned ?? false,
    lastTouchedAt: Date.now(),
  };

  const merged = [nextItem, ...currentItems.filter((item) => item.key !== payload.key)]
    .sort(sortWorkItems);
  const pinnedItems = merged.filter((item) => item.pinned);
  const recentItems = merged.filter((item) => !item.pinned).slice(0, MAX_RECENT_WORK_ITEMS);

  return [...pinnedItems, ...recentItems];
}

function sortWorkItems(left: FlowWorkItem, right: FlowWorkItem): number {
  if (left.pinned !== right.pinned) {
    return left.pinned ? -1 : 1;
  }

  return right.lastTouchedAt - left.lastTouchedAt;
}

function defaultBranchIdForSession(session: StaffSession | null): number | null {
  return session?.startup.default_branch_id ?? session?.startup.default_branch?.branch_id ?? null;
}

function defaultStationIdForSession(session: StaffSession | null): number | null {
  const assignedStationIds = session?.startup.assigned_station_ids ?? [];

  return assignedStationIds.length === 1 ? assignedStationIds[0] : null;
}
