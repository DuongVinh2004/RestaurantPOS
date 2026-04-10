import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import type { StaffSession } from '../../core/auth/storage';
import type { JourneyContext } from '../../core/utils/journey';

type FlowState = {
  sessionOwnerKey: number | null;
  branchId: number | null;
  selectedTableId: number | null;
  selectedReservationId: number | null;
  selectedReservationRowVersion: number | null;
  selectedOrderId: number | null;
  selectedOrderRowVersion: number | null;
  selectedStationId: number | null;
  source: JourneyContext['source'];
  syncSessionContext: (session: StaffSession | null) => void;
  hydrateFromSession: (session: StaffSession | null) => void;
  applyJourney: (context: JourneyContext) => void;
  setBranchId: (branchId: number | null) => void;
  setTableContext: (payload: { tableId: number | null; source?: JourneyContext['source'] }) => void;
  setReservationContext: (payload: {
    reservationId: number | null;
    reservationRowVersion?: number | null;
    source?: JourneyContext['source'];
  }) => void;
  setOrderContext: (payload: {
    orderId: number | null;
    orderRowVersion?: number | null;
    source?: JourneyContext['source'];
  }) => void;
  setStationId: (stationId: number | null) => void;
  resetCoreContext: () => void;
};

const clearedCoreContext = {
  selectedTableId: null,
  selectedReservationId: null,
  selectedReservationRowVersion: null,
  selectedOrderId: null,
  selectedOrderRowVersion: null,
  selectedStationId: null,
  source: undefined,
} satisfies Pick<
  FlowState,
  | 'selectedTableId'
  | 'selectedReservationId'
  | 'selectedReservationRowVersion'
  | 'selectedOrderId'
  | 'selectedOrderRowVersion'
  | 'selectedStationId'
  | 'source'
>;

export const useFlowStore = create<FlowState>()(
  persist(
    (set) => ({
      sessionOwnerKey: null,
      branchId: null,
      selectedTableId: null,
      selectedReservationId: null,
      selectedReservationRowVersion: null,
      selectedOrderId: null,
      selectedOrderRowVersion: null,
      selectedStationId: null,
      source: undefined,
      syncSessionContext: (session) => {
        const nextSessionOwnerKey = session?.staff_api_key_id ?? null;
        const defaultBranchId = session?.startup.default_branch?.branch_id ?? null;

        set((state) => {
          if (!session) {
            return {
              sessionOwnerKey: null,
              branchId: null,
              ...clearedCoreContext,
            };
          }

          if (state.sessionOwnerKey !== nextSessionOwnerKey) {
            return {
              sessionOwnerKey: nextSessionOwnerKey,
              branchId: defaultBranchId,
              ...clearedCoreContext,
            };
          }

          return {
            sessionOwnerKey: nextSessionOwnerKey,
            branchId: state.branchId ?? defaultBranchId,
          };
        });
      },
      hydrateFromSession: (session) => {
        const defaultBranchId = session?.startup.default_branch?.branch_id ?? null;
        set((state) => ({
          branchId: state.branchId ?? defaultBranchId,
        }));
      },
      applyJourney: (context) =>
        set({
          source: context.source,
          selectedTableId: context.tableId ?? null,
          selectedReservationId: context.reservationId ?? null,
          selectedReservationRowVersion: context.reservationRowVersion ?? null,
          selectedOrderId: context.orderId ?? null,
          selectedOrderRowVersion: context.orderRowVersion ?? null,
          selectedStationId: context.stationId ?? null,
        }),
      setBranchId: (branchId) =>
        set((state) => (
          state.branchId === branchId
            ? { branchId }
            : {
              branchId,
              ...clearedCoreContext,
            }
        )),
      setTableContext: ({ tableId, source }) =>
        set({
          selectedTableId: tableId,
          source,
        }),
      setReservationContext: ({ reservationId, reservationRowVersion, source }) =>
        set({
          selectedReservationId: reservationId,
          selectedReservationRowVersion: reservationRowVersion ?? null,
          source,
        }),
      setOrderContext: ({ orderId, orderRowVersion, source }) =>
        set({
          selectedOrderId: orderId,
          selectedOrderRowVersion: orderRowVersion ?? null,
          source,
        }),
      setStationId: (selectedStationId) => set({ selectedStationId }),
      resetCoreContext: () => set(clearedCoreContext),
    }),
    {
      name: 'restaurantpos.staff-web.flow',
      storage: createJSONStorage(() => sessionStorage),
      partialize: (state) => ({
        sessionOwnerKey: state.sessionOwnerKey,
        branchId: state.branchId,
        selectedTableId: state.selectedTableId,
        selectedReservationId: state.selectedReservationId,
        selectedReservationRowVersion: state.selectedReservationRowVersion,
        selectedOrderId: state.selectedOrderId,
        selectedOrderRowVersion: state.selectedOrderRowVersion,
        selectedStationId: state.selectedStationId,
        source: state.source,
      }),
    },
  ),
);
