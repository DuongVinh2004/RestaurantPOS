import { Suspense, lazy, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Button, Card, Space, Typography } from 'antd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { StaffTableBoardRow, StaffTableBoardUnassignedReservation } from '../../core/api/sdk';
import {
  assignBestFitTable,
  assignSuggestedTable,
  buildBoardWindow,
  checkInReservation,
  createReservation,
  createWalkInSession,
  getTableBoard,
  getTableBoardChanges,
  moveReservationTable,
  releaseStaffTable,
} from '../../core/api/staff-api';
import { formatStaffFacingApiError } from '../../core/api/errors';
import { formatDateTime } from '../../core/utils/format';
import { buildJourneySearch, mergeJourneySearch } from '../../core/utils/journey';
import {
  buildOrderContextLabel,
  buildReservationContextLabel,
  buildTableContextLabel,
} from '../../core/utils/journey-labels';
import {
  getReservationGuestLabel,
  isReservationSnapshotOnlyGuest,
  RESERVATION_SNAPSHOT_GUEST_LABEL,
} from '../../core/utils/reservation-guest';
import { reservationTone, tableTone } from '../../core/utils/status';
import { translateUiCode } from '../../core/utils/translation';
import { PageHeader } from '../../components/layout/PageHeader';
import { SplitWorkspace } from '../../components/layout/SplitWorkspace';
import { MutationStatusNotice } from '../../components/feedback/MutationStatusNotice';
import {
  ApiStateBlock,
  EmptyBlock,
  InlineLoading,
  TransientFailureState,
} from '../../components/states/StateBlocks';
import { StatusChip } from '../../components/status/StatusChip';
import { useAuthStore } from '../../app/store/auth-store';
import { useFlowStore } from '../../app/store/flow-store';
import { useConfirmAction } from '../../hooks/useConfirmAction';
import { useJourneyContext } from '../../hooks/useJourneyContext';
import { useStaffMutationFeedback } from '../../hooks/useStaffMutationFeedback';
import { can } from '../../core/permissions/capabilities';
import {
  buildReservationCreatePayload,
  type ReservationCreateFormValues,
} from '../reservations/reservation-create';
import { buildTableBoardSearch, readTableBoardUrlState } from './table-board-url';
import {
  DEFAULT_WALK_IN_FORM_VALUES,
  type MoveTableFormValues,
  type WalkInFormValues,
} from './table-board-forms';

const TableBoardDialogs = lazy(
  () => import('./TableBoardDialogs').then((module) => ({ default: module.TableBoardDialogs })),
);
const TableBoardUnassignedReservationsTable = lazy(
  () => import('./TableBoardAssignmentTables').then((module) => ({ default: module.TableBoardUnassignedReservationsTable })),
);
const TableBoardCandidateReservationsTable = lazy(
  () => import('./TableBoardAssignmentTables').then((module) => ({ default: module.TableBoardCandidateReservationsTable })),
);

function tableCardActionCopy(row: StaffTableBoardRow): string {
  const boardState = (row.board_state ?? '').toLowerCase();

  if (row.active_order) {
    return 'Mở đơn đang phục vụ';
  }

  if (row.actions?.check_in?.available) {
    return 'Nhận khách vào bàn';
  }

  if (row.reservation) {
    return 'Xem chi tiết đặt bàn';
  }

  if (boardState === 'available') {
    return 'Xếp khách vào bàn';
  }

  if (row.availability?.has_hold_in_range || row.hold || (row.holds?.length ?? 0) > 0) {
    return 'Kiểm tra giữ bàn';
  }

  if (row.availability?.is_realtime_occupied) {
    return 'Kiểm tra bàn đang dùng';
  }

  return 'Mở chi tiết bàn';
}

function buildTableCardContext(row: StaffTableBoardRow): {
  label: string;
  value: string;
  meta: string;
} {
  const boardStateLabel = translateUiCode(row.board_state, 'Không rõ');
  const realtimeStatusLabel = translateUiCode(row.realtime_status, boardStateLabel);

  if (row.active_order) {
    return {
      label: 'Đang phục vụ',
      value: `Đơn #${row.active_order.order_id}`,
      meta: row.reservation
        ? `${row.reservation.reservation_code} • ${row.reservation.guest_count} khách`
        : `${translateUiCode(row.active_order.status)} • ${row.capacity.seats ?? 'Chưa rõ'} chỗ`,
    };
  }

  if (row.reservation) {
    return {
      label: 'Đặt bàn hiện tại',
      value: row.reservation.reservation_code,
      meta: `${row.reservation.guest_count} khách • ${getReservationGuestLabel(row.reservation)}`,
    };
  }

  if (realtimeStatusLabel !== boardStateLabel) {
    return {
      label: 'Tại bàn',
      value: realtimeStatusLabel,
      meta: `Bảng điều phối: ${boardStateLabel}`,
    };
  }

  if ((row.board_state ?? '').toLowerCase() === 'available') {
    return {
      label: 'Trạng thái',
      value: 'Trống & sẵn nhận khách',
      meta: 'Không có đặt bàn hoặc đơn đang giữ bàn',
    };
  }

  return {
    label: 'Trạng thái',
    value: boardStateLabel,
    meta: 'Mở chi tiết để xem thao tác phù hợp cho bàn này',
  };
}

function formatWalkInCreationError(error: unknown): string {
  const message = formatStaffFacingApiError(
    error,
    'Không thể xếp khách vào bàn này. Hãy kiểm tra lại số điện thoại, số khách và trạng thái bàn.',
  );

  if (message.toLowerCase().includes('non-customer')) {
    return 'Số điện thoại này đang thuộc tài khoản nội bộ. Hãy dùng số khác hoặc chọn khách đã có.';
  }

  return message;
}

function hasMeaningfulWalkInDraft(values: Partial<WalkInFormValues>): boolean {
  return Boolean(
    values.guest_name?.trim()
    || values.phone?.trim()
    || values.notes?.trim()
    || (values.guest_count !== undefined && values.guest_count !== DEFAULT_WALK_IN_FORM_VALUES.guest_count)
    || (
      values.service_minutes !== undefined
      && values.service_minutes !== DEFAULT_WALK_IN_FORM_VALUES.service_minutes
    ),
  );
}

export function TableBoardPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const confirmAction = useConfirmAction();
  const mutationFeedback = useStaffMutationFeedback('table-board');
  const journey = useJourneyContext();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const setTableContext = useFlowStore((state) => state.setTableContext);
  const setReservationContext = useFlowStore((state) => state.setReservationContext);
  const setOrderContext = useFlowStore((state) => state.setOrderContext);
  const windowRange = useMemo(() => buildBoardWindow(), []);
  const [walkInOpen, setWalkInOpen] = useState(false);
  const [phoneReservationOpen, setPhoneReservationOpen] = useState(false);
  const [moveTableOpen, setMoveTableOpen] = useState(false);
  const [walkInDrafts, setWalkInDrafts] = useState<Record<number, Partial<WalkInFormValues>>>({});
  const boardUrlState = useMemo(() => readTableBoardUrlState(searchParams), [searchParams]);
  const zone = boardUrlState.zone !== '' ? boardUrlState.zone : undefined;
  const selectedTableId = journey.tableId ?? null;
  const boardDataCacheKey = `${branchId ?? 'default'}|${zone ?? 'all'}|${windowRange.from}|${windowRange.to}`;

  const updateBoardSearch = useCallback((
    patch: Partial<typeof boardUrlState>,
    context: Parameters<typeof mergeJourneySearch>[1],
    options?: { replace?: boolean },
  ) => {
    const nextLocalSearch = buildTableBoardSearch(searchParams, patch);
    const nextSearch = mergeJourneySearch(nextLocalSearch, context);
    setSearchParams(new URLSearchParams(nextSearch), { replace: options?.replace });
  }, [searchParams, setSearchParams]);

  const clearBoardSelection = useCallback((options?: { replace?: boolean }) => {
    updateBoardSearch({ zone: boardUrlState.zone }, { source: 'board' }, options);
  }, [boardUrlState.zone, updateBoardSearch]);

  const openWalkInFormForRow = useCallback((row: StaffTableBoardRow) => {
    void row;
    setWalkInOpen(true);
  }, []);

  const selectBoardRow = useCallback((row: StaffTableBoardRow) => {
    updateBoardSearch(
      { zone: boardUrlState.zone },
      {
        source: 'board',
        tableId: row.table_id,
        reservationId: row.reservation?.reservation_id,
        reservationRowVersion: row.reservation?.row_version,
        orderId: row.active_order?.order_id,
        orderRowVersion: row.active_order?.row_version,
      },
    );

    if (row.table_id === selectedTableId && walkInDrafts[row.table_id] && !row.active_order) {
      openWalkInFormForRow(row);
    }
  }, [boardUrlState.zone, openWalkInFormForRow, selectedTableId, updateBoardSearch, walkInDrafts]);

  const boardQuery = useQuery({
    queryKey: ['table-board', branchId, zone, windowRange.from, windowRange.to],
    queryFn: () =>
      getTableBoard({
        ...windowRange,
        branch_id: branchId ?? undefined,
        zone,
        include_holds: true,
        group_by: 'zone',
      }),
    refetchInterval: 30_000,
  });

  const lastGoodBoardDataRef = useRef<{ key: string; data: typeof boardQuery.data } | null>(null);
  const lastAppliedBoardChangeVersionRef = useRef<number | null>(null);

  useEffect(() => {
    if (boardQuery.data) {
      lastGoodBoardDataRef.current = {
        key: boardDataCacheKey,
        data: boardQuery.data,
      };
    }
  }, [boardDataCacheKey, boardQuery.data]);

  const boardData = boardQuery.data
    ?? (lastGoodBoardDataRef.current?.key === boardDataCacheKey ? lastGoodBoardDataRef.current.data : undefined);
  const isBoardColdLoading = !boardData && boardQuery.isLoading;
  const isBoardRefreshing = Boolean(boardData && boardQuery.isFetching);
  const boardRealtimeVersion = boardData?.meta.realtime.current_version;

  const selectedTable = useMemo(
    () => boardData?.data.find((row) => row.table_id === selectedTableId) ?? null,
    [boardData?.data, selectedTableId],
  );
  const selectedTableCardContext = useMemo(
    () => (selectedTable ? buildTableCardContext(selectedTable) : null),
    [selectedTable],
  );

  useEffect(() => {
    if (!selectedTable) {
      return;
    }

    setTableContext({
      tableId: selectedTable.table_id,
      label: buildTableContextLabel(selectedTable.table_code, selectedTable.table_id),
      source: 'board',
    });
    setReservationContext({
      reservationId: selectedTable.reservation?.reservation_id ?? null,
      reservationRowVersion: selectedTable.reservation?.row_version ?? null,
      label: buildReservationContextLabel(
        selectedTable.reservation?.reservation_code ?? null,
        selectedTable.reservation?.reservation_id ?? null,
      ),
      source: 'board',
    });
    setOrderContext({
      orderId: selectedTable.active_order?.order_id ?? null,
      orderRowVersion: selectedTable.active_order?.row_version ?? null,
      label: buildOrderContextLabel(selectedTable.active_order?.order_id ?? null),
      source: 'board',
    });
  }, [selectedTable, setOrderContext, setReservationContext, setTableContext]);

  const selectedTableHasWalkInDraft = selectedTable ? Boolean(walkInDrafts[selectedTable.table_id]) : false;
  const moveTableTargetOptions = useMemo(
    () =>
      (boardData?.data ?? [])
        .filter((row) => row.table_id !== selectedTable?.table_id && row.availability.accepts_new_assignment)
        .map((row) => ({
          value: row.table_id,
          label: `${row.table_code} • ${row.zone ?? 'Chưa có khu'} • ${row.capacity.seats ?? 'Không rõ'} chỗ`,
        })),
    [boardData?.data, selectedTable?.table_id],
  );
  const openWalkInFormForSelectedTable = useCallback(() => {
    if (!selectedTable) {
      return;
    }

    openWalkInFormForRow(selectedTable);
  }, [openWalkInFormForRow, selectedTable]);
  const openPhoneReservationFormForSelectedTable = useCallback(() => {
    if (!selectedTable) {
      return;
    }

    setPhoneReservationOpen(true);
  }, [selectedTable]);
  const saveWalkInDraftForSelectedTable = useCallback((values: Partial<WalkInFormValues>) => {
    if (!selectedTable) {
      return;
    }

    setWalkInDrafts((currentDrafts) => {
      const nextDraft = {
        ...DEFAULT_WALK_IN_FORM_VALUES,
        ...currentDrafts[selectedTable.table_id],
        ...values,
      };
      const nextDrafts = { ...currentDrafts };

      if (!hasMeaningfulWalkInDraft(nextDraft)) {
        delete nextDrafts[selectedTable.table_id];
        return nextDrafts;
      }

      nextDrafts[selectedTable.table_id] = nextDraft;
      return nextDrafts;
    });
  }, [selectedTable]);
  const closeWalkInForm = useCallback(() => {
    setWalkInOpen(false);
  }, []);
  const closePhoneReservationForm = useCallback(() => {
    setPhoneReservationOpen(false);
  }, []);
  const openMoveTableFormForSelectedTable = useCallback(() => {
    if (!selectedTable?.actions.move_table?.available) {
      return;
    }

    setMoveTableOpen(true);
  }, [selectedTable?.actions.move_table?.available]);
  const closeMoveTableForm = useCallback(() => {
    setMoveTableOpen(false);
  }, []);

  useEffect(() => {
    if (!boardData) {
      return;
    }

    const rows = boardData.data;
    if (rows.length === 0) {
      if (selectedTableId) {
        clearBoardSelection({ replace: true });
      }
      return;
    }

    if (!selectedTableId || selectedTable) {
      return;
    }

    clearBoardSelection({ replace: true });
  }, [boardData, clearBoardSelection, selectedTable, selectedTableId]);

  const boardChangesQuery = useQuery({
    queryKey: ['table-board-changes', boardRealtimeVersion],
    queryFn: () => getTableBoardChanges(boardRealtimeVersion),
    enabled: !!boardRealtimeVersion,
    refetchInterval: 20_000,
  });

  const refreshTableBoardInBackground = useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: ['table-board'], refetchType: 'active' });
  }, [queryClient]);

  const refreshBoardWorkspace = useCallback(async (options?: {
    reservationId?: number | null;
    tableIds?: Array<number | null | undefined>;
  }) => {
    const invalidations = [
      queryClient.invalidateQueries({ queryKey: ['table-board'], refetchType: 'active' }),
      queryClient.invalidateQueries({ queryKey: ['reservations'] }),
    ];

    const reservationId = options?.reservationId ?? null;
    if (reservationId) {
      invalidations.push(
        queryClient.invalidateQueries({ queryKey: ['reservation-detail', reservationId] }),
        queryClient.invalidateQueries({ queryKey: ['active-order-by-reservation', reservationId] }),
      );
    }

    const uniqueTableIds = Array.from(
      new Set((options?.tableIds ?? []).filter((tableId): tableId is number => typeof tableId === 'number')),
    );

    for (const tableId of uniqueTableIds) {
      invalidations.push(queryClient.invalidateQueries({ queryKey: ['active-order-by-table', tableId] }));
    }

    await Promise.all(invalidations);
  }, [queryClient]);

  useEffect(() => {
    const currentVersion = boardRealtimeVersion ?? null;
    const latestVersion = boardChangesQuery.data?.data.current_version ?? null;
    const eventCount = boardChangesQuery.data?.data.events.length ?? 0;

    if (currentVersion === null || latestVersion === null) {
      return;
    }

    if (latestVersion === currentVersion) {
      lastAppliedBoardChangeVersionRef.current = latestVersion;
      return;
    }

    if (
      (latestVersion > currentVersion || eventCount > 0)
      && lastAppliedBoardChangeVersionRef.current !== latestVersion
    ) {
      lastAppliedBoardChangeVersionRef.current = latestVersion;
      refreshTableBoardInBackground();
    }
  }, [
    boardChangesQuery.data?.data.current_version,
    boardChangesQuery.data?.data.events.length,
    boardRealtimeVersion,
    refreshTableBoardInBackground,
  ]);

  const walkInMutation = useMutation({
    onMutate: () => {
      mutationFeedback.setSubmitting(
        'Xếp khách vào bàn',
        `Đang tạo phiên khách mới cho ${selectedTable?.table_code ?? 'bàn đang chọn'} và khóa thao tác gửi lặp.`,
      );
    },
    mutationFn: async (values: WalkInFormValues) => {
      if (!selectedTable) {
        throw new Error('Chọn bàn trước khi tạo phiên khách vãng lai.');
      }

      return createWalkInSession({
        branch_id: branchId,
        guest_name: values.guest_name,
        phone: values.phone,
        table_ids: [selectedTable.table_id],
        guest_count: values.guest_count,
        service_minutes: values.service_minutes,
        notes: values.notes,
      });
    },
    onSuccess: async (reservationEnvelope) => {
      const reservation = reservationEnvelope.data;
      setWalkInOpen(false);
      if (selectedTable) {
        setWalkInDrafts((currentDrafts) => {
          const nextDrafts = { ...currentDrafts };
          delete nextDrafts[selectedTable.table_id];

          return nextDrafts;
        });
      }
      await refreshBoardWorkspace({
        reservationId: reservation.reservation_id,
        tableIds: [selectedTable?.table_id ?? null],
      });
      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        label: buildReservationContextLabel(reservation.reservation_code, reservation.reservation_id),
        source: 'board',
      });
      setTableContext({
        tableId: selectedTable?.table_id ?? null,
        label: buildTableContextLabel(selectedTable?.table_code ?? null, selectedTable?.table_id ?? null),
        source: 'board',
      });
      mutationFeedback.setSuccess(
        'Xếp khách vào bàn',
        `Đã tạo ${reservation.reservation_code} và mở tiếp luồng order cho bàn ${selectedTable?.table_code ?? 'đang chọn'}.`,
      );
      navigate(`/orders?${buildJourneySearch({
        source: 'board',
        tableId: selectedTable?.table_id ?? undefined,
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
      })}`);
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Xếp khách vào bàn',
        fallbackMessage: formatWalkInCreationError(error),
      });
    },
  });

  const createPhoneReservationMutation = useMutation({
    onMutate: () => {
      mutationFeedback.setSubmitting(
        'Tạo đặt bàn hộ',
        `Đang lưu guest snapshot và tạo đặt bàn mới cho ${selectedTable?.table_code ?? 'bàn đang chọn'}.`,
      );
    },
    mutationFn: async (values: ReservationCreateFormValues) => {
      if (!selectedTable) {
        throw new Error('Chọn bàn trước khi tạo đặt bàn hộ.');
      }

      return createReservation(buildReservationCreatePayload(values, {
        branchId,
        tableIds: [selectedTable.table_id],
      }));
    },
    onSuccess: async (reservationEnvelope) => {
      const reservation = reservationEnvelope.data;
      setPhoneReservationOpen(false);
      await refreshBoardWorkspace({
        reservationId: reservation.reservation_id,
        tableIds: reservation.table_ids ?? (selectedTable ? [selectedTable.table_id] : []),
      });
      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        label: buildReservationContextLabel(reservation.reservation_code, reservation.reservation_id),
        source: 'board',
      });
      setTableContext({
        tableId: selectedTable?.table_id ?? null,
        label: buildTableContextLabel(selectedTable?.table_code ?? null, selectedTable?.table_id ?? null),
        source: 'board',
      });
      mutationFeedback.setSuccess(
        'Tạo đặt bàn hộ',
        `Đã tạo ${reservation.reservation_code} và neo lại ngữ cảnh reservation mới.`,
      );
      navigate(`/reservations?${buildJourneySearch({
        source: 'board',
        tableId: selectedTable?.table_id ?? undefined,
        tableIds: reservation.table_ids ?? (selectedTable ? [selectedTable.table_id] : undefined),
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
      })}`);
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Tạo đặt bàn hộ',
        fallbackMessage: formatStaffFacingApiError(
          error,
          'Không thể tạo đặt bàn hộ. Hãy kiểm tra tên khách, số điện thoại và khung giờ.',
        ),
      });
    },
  });

  const checkInMutation = useMutation({
    onMutate: (table) => {
      mutationFeedback.setSubmitting(
        'Nhận bàn',
        `Đang chuyển ${table.reservation?.reservation_code ?? 'reservation đã chọn'} sang trạng thái đang phục vụ.`,
      );
    },
    mutationFn: async (table: StaffTableBoardRow) => {
      if (!table.reservation) {
        throw new Error('Bàn này không có đặt bàn để nhận bàn.');
      }

      return checkInReservation(table.reservation.reservation_id, {
        row_version: table.actions.check_in?.preferred_payload.row_version ?? table.reservation.row_version,
        table_ids: table.actions.check_in?.preferred_payload.table_ids ?? table.reservation.table_ids,
      });
    },
    onSuccess: async (reservationEnvelope, table) => {
      const reservation = reservationEnvelope.data;
      await refreshBoardWorkspace({
        reservationId: reservation.reservation_id,
        tableIds: [table.table_id],
      });
      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        label: buildReservationContextLabel(reservation.reservation_code, reservation.reservation_id),
        source: 'board',
      });
      setTableContext({
        tableId: table.table_id,
        label: buildTableContextLabel(table.table_code, table.table_id),
        source: 'board',
      });
      mutationFeedback.setSuccess(
        'Nhận bàn',
        `Đã nhận bàn cho ${table.reservation?.reservation_code ?? reservation.reservation_code} và mở luồng order hiện tại.`,
      );
      navigate(`/orders?${buildJourneySearch({
        source: 'board',
        tableId: table.table_id,
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
      })}`);
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Nhận bàn',
        fallbackMessage: 'Không thể nhận bàn cho đặt bàn này.',
      });
    },
  });

  const assignCurrentTableMutation = useMutation({
    onMutate: () => {
      mutationFeedback.setSubmitting(
        'Gán vào bàn đang chọn',
        `Đang gán reservation vào ${selectedTable?.table_code ?? 'bàn hiện tại'} theo ngữ cảnh board.`,
      );
    },
    mutationFn: async (reservation: { reservationId: number; rowVersion: number }) => {
      if (!selectedTable) {
        throw new Error('Chọn bàn đích trước khi gán.');
      }

      return assignSuggestedTable(reservation.reservationId, {
        table_id: selectedTable.table_id,
        row_version: reservation.rowVersion,
        board_from: windowRange.from,
        board_to: windowRange.to,
        zone: selectedTable.zone,
      });
    },
    onSuccess: async (reservationEnvelope) => {
      const reservation = reservationEnvelope.data;
      await refreshBoardWorkspace({
        reservationId: reservation.reservation_id,
        tableIds: reservation.table_ids ?? [selectedTable?.table_id ?? null],
      });
      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        label: buildReservationContextLabel(reservation.reservation_code, reservation.reservation_id),
        source: 'board',
      });
      mutationFeedback.setSuccess(
        'Gán vào bàn đang chọn',
        `Đã gán ${reservation.reservation_code} vào ${selectedTable?.table_code ?? 'bàn hiện tại'}.`,
      );
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Gán vào bàn đang chọn',
        fallbackMessage: 'Không thể gán đặt bàn vào bàn hiện tại.',
      });
    },
  });

  const assignBestFitMutation = useMutation({
    onMutate: (reservation) => {
      mutationFeedback.setSubmitting(
        'Gán bàn tốt nhất',
        `Đang xin backend chọn bàn phù hợp nhất cho ${reservation.reservation_code}.`,
      );
    },
    mutationFn: async (reservation: StaffTableBoardUnassignedReservation) =>
      assignBestFitTable(reservation.reservation_id, {
        row_version: reservation.row_version,
        board_from: windowRange.from,
        board_to: windowRange.to,
        zone,
      }),
    onSuccess: async (reservationEnvelope) => {
      const reservation = reservationEnvelope.data;
      await refreshBoardWorkspace({
        reservationId: reservation.reservation_id,
        tableIds: reservation.table_ids ?? [],
      });
      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        label: buildReservationContextLabel(reservation.reservation_code, reservation.reservation_id),
        source: 'board',
      });
      mutationFeedback.setSuccess(
        'Gán bàn tốt nhất',
        `Đã cập nhật ${reservation.reservation_code} với phương án bàn tốt nhất hiện tại.`,
      );
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Gán bàn tốt nhất',
        fallbackMessage: 'Không thể gán bàn phù hợp nhất.',
      });
    },
  });

  const moveTableMutation = useMutation({
    onMutate: () => {
      mutationFeedback.setSubmitting(
        'Chuyển bàn',
        `Đang khóa thao tác chuyển ${selectedTable?.reservation?.reservation_code ?? 'reservation đang chọn'} sang bàn mới.`,
      );
    },
    mutationFn: async (values: MoveTableFormValues) => {
      if (!selectedTable?.reservation || !selectedTable.actions.move_table?.preferred_payload.from_table_id) {
        throw new Error('Bàn này chưa sẵn sàng cho thao tác chuyển bàn.');
      }

      return moveReservationTable(selectedTable.reservation.reservation_id, {
        from_table_id: selectedTable.actions.move_table.preferred_payload.from_table_id,
        to_table_id: values.to_table_id,
        row_version: selectedTable.actions.move_table.preferred_payload.row_version ?? selectedTable.reservation.row_version,
      });
    },
    onSuccess: async (reservationEnvelope, values) => {
      if (!selectedTable?.reservation) {
        return;
      }

      const reservation = reservationEnvelope.data;
      const nextOrderId = selectedTable.active_order?.order_id;
      const nextOrderRowVersion = selectedTable.active_order?.row_version ?? null;
      const nextTable = boardData?.data.find((row) => row.table_id === values.to_table_id) ?? null;

      setMoveTableOpen(false);

      await refreshBoardWorkspace({
        reservationId: reservation.reservation_id,
        tableIds: [selectedTable.table_id, values.to_table_id],
      });

      setReservationContext({
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        label: buildReservationContextLabel(reservation.reservation_code, reservation.reservation_id),
        source: 'board',
      });
      setTableContext({
        tableId: values.to_table_id,
        label: buildTableContextLabel(nextTable?.table_code ?? null, values.to_table_id),
        source: 'board',
      });
      if (nextOrderId) {
        setOrderContext({
          orderId: nextOrderId,
          orderRowVersion: nextOrderRowVersion,
          label: buildOrderContextLabel(nextOrderId),
          source: 'board',
        });
      }

      mutationFeedback.setSuccess(
        'Chuyển bàn',
        `Đã chuyển ${selectedTable.reservation.reservation_code} sang ${nextTable?.table_code ?? `bàn #${values.to_table_id}`}.`,
      );
      navigate(`/orders?${buildJourneySearch({
        source: 'board',
        tableId: values.to_table_id,
        tableIds: reservation.table_ids,
        reservationId: reservation.reservation_id,
        reservationRowVersion: reservation.row_version,
        orderId: nextOrderId,
        orderRowVersion: nextOrderRowVersion ?? undefined,
      })}`);
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Chuyển bàn',
        fallbackMessage: 'Không thể chuyển bàn cho lượt phục vụ này.',
      });
    },
  });

  const releaseTableMutation = useMutation({
    onMutate: (table) => {
      mutationFeedback.setSubmitting(
        'Trả bàn',
        `Đang trả ${table.table_code} về trạng thái sẵn sàng nhận khách.`,
      );
    },
    mutationFn: async (table: StaffTableBoardRow) => releaseStaffTable(table.table_id),
    onSuccess: async (response, table) => {
      await refreshBoardWorkspace({
        tableIds: [table.table_id],
      });

      setReservationContext({
        reservationId: null,
        reservationRowVersion: null,
        source: 'board',
      });
      setOrderContext({
        orderId: null,
        orderRowVersion: null,
        source: 'board',
      });
      setTableContext({
        tableId: table.table_id,
        label: buildTableContextLabel(table.table_code, table.table_id),
        source: 'board',
      });
      updateBoardSearch(
        { zone: boardUrlState.zone },
        {
          source: 'board',
          tableId: table.table_id,
        },
        { replace: true },
      );

      mutationFeedback.setSuccess(
        'Trả bàn',
        `Đã trả ${String(response.data?.table_code ?? table.table_code)} về trạng thái sẵn sàng nhận khách.`,
      );
    },
    onError: (error) => {
      mutationFeedback.setFailure(error, {
        actionLabel: 'Trả bàn',
        fallbackMessage: 'Không thể trả bàn về trạng thái sẵn sàng nhận khách.',
      });
    },
  });

  async function handleCheckIn(table: StaffTableBoardRow) {
    const confirmed = await confirmAction({
      title: `Nhận bàn ${table.reservation?.reservation_code ?? 'đặt bàn'}`,
      content: (
        <Space direction="vertical" size={10}>
          <Typography.Text>
            Thao tác này đưa reservation sang trạng thái <strong>đang phục vụ</strong> và giữ nguyên gán bàn hiện tại.
          </Typography.Text>
          <div className="staff-mini-list">
            <div className="staff-mini-list-item">
              <Typography.Text strong>Bàn hiện tại</Typography.Text>
              <Typography.Text type="secondary">{table.table_code}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Số khách</Typography.Text>
              <Typography.Text type="secondary">{table.reservation?.guest_count ?? 'Không rõ'} khách</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Phiên bản gửi đi</Typography.Text>
              <Typography.Text type="secondary">
                RV {table.actions.check_in?.preferred_payload.row_version ?? table.reservation?.row_version ?? 'n/a'}
              </Typography.Text>
            </div>
          </div>
        </Space>
      ),
      okText: 'Nhận bàn',
      width: 560,
    });

    if (confirmed) {
      await checkInMutation.mutateAsync(table);
    }
  }

  async function handleReleaseTable(table: StaffTableBoardRow) {
    const confirmed = await confirmAction({
      title: `Trả bàn ${table.table_code}`,
      danger: true,
      content: (
        <Space direction="vertical" size={10}>
          <Typography.Text>
            Chỉ tiếp tục khi bàn đang bị kẹt ở trạng thái <strong>Occupied</strong> nhưng không còn reservation hay order đang chạy.
          </Typography.Text>
          <div className="staff-mini-list">
            <div className="staff-mini-list-item">
              <Typography.Text strong>Trạng thái board</Typography.Text>
              <Typography.Text type="secondary">{translateUiCode(table.board_state)}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Reservation gắn kèm</Typography.Text>
              <Typography.Text type="secondary">{table.reservation?.reservation_code ?? 'Không có'}</Typography.Text>
            </div>
            <div className="staff-mini-list-item">
              <Typography.Text strong>Đơn đang chạy</Typography.Text>
              <Typography.Text type="secondary">{table.active_order?.order_id ? `#${table.active_order.order_id}` : 'Không có'}</Typography.Text>
            </div>
          </div>
        </Space>
      ),
      okText: 'Trả bàn',
      width: 560,
    });

    if (confirmed) {
      await releaseTableMutation.mutateAsync(table);
    }
  }

  const main = (
    <Space orientation="vertical" size={14} style={{ width: '100%' }} className="staff-table-board-main">
      <div className="staff-table-board-ops-panel">
        <PageHeader
          className="staff-table-board-page-header"
          eyebrow="Vận hành sàn phục vụ"
          title="Sơ đồ bàn"
          description="Chọn bàn để xếp khách, nhận đặt bàn hoặc mở đơn đang phục vụ."
          context={(
            <>
              <StatusChip label={branchId ? 'Đúng chi nhánh đang chọn' : 'Theo branch mặc định'} tone="default" variant="freshness" />
              <StatusChip label={selectedTable ? `${selectedTable.table_code} • ${translateUiCode(selectedTable.board_state)}` : 'Chưa chọn bàn'} tone={selectedTable ? tableTone(selectedTable.board_state) : 'warning'} variant="entity" />
              <StatusChip label={`Realtime v${boardRealtimeVersion ?? 0}`} tone="processing" variant="freshness" />
            </>
          )}
          extra={
            <>
              <div className="staff-table-board-toolbar-select-wrap">
                <select
                  aria-label="Lọc theo khu"
                  className="staff-table-board-zone-select"
                  value={zone ?? ''}
                  onChange={(event) => updateBoardSearch(
                    { zone: event.target.value },
                    { source: 'board' },
                    { replace: true },
                  )}
                >
                  <option value="">Lọc theo khu</option>
                  {(boardData?.zones ?? []).map((zoneSummary) => (
                    <option key={zoneSummary.zone} value={zoneSummary.zone}>
                      {`${zoneSummary.zone} (${zoneSummary.summary.table_count})`}
                    </option>
                  ))}
                </select>
              </div>
              <Button className="staff-table-board-toolbar-button" onClick={() => boardQuery.refetch()} loading={isBoardRefreshing}>
                Làm mới
              </Button>
            </>
          }
        />

        <div className="staff-table-board-summary-strip">
          <div className="staff-table-board-summary-item staff-table-board-summary-item-active">
            <span>Đơn phục vụ</span>
            <strong>{boardData?.summary.active_order_count ?? 0}</strong>
          </div>
          <div className="staff-table-board-summary-item">
            <span>Chưa gán bàn</span>
            <strong>{boardData?.summary.unassigned_reservation_count ?? 0}</strong>
          </div>
          <div className="staff-table-board-summary-item">
            <span>Khu</span>
            <strong>{zone ?? 'Tất cả'}</strong>
          </div>
          <div className="staff-table-board-summary-item">
            <span>Realtime</span>
            <strong>v{boardRealtimeVersion ?? 0}</strong>
          </div>
        </div>
      </div>

      {isBoardColdLoading ? <InlineLoading tip="Đang tải sơ đồ bàn..." /> : null}
      {boardQuery.error && !boardData ? (
        <ApiStateBlock
          error={boardQuery.error}
          fallback="Không thể tải sơ đồ bàn."
          onRetry={() => {
            void boardQuery.refetch();
          }}
        />
      ) : null}
      {boardQuery.error && boardData ? (
        <TransientFailureState
          title="Không đồng bộ được lượt mới nhất của sơ đồ bàn"
          description="Sơ đồ hiện tại vẫn được giữ để staff tiếp tục thao tác. Hãy làm mới lại để lấy biến động mới nhất."
          primaryAction={<Button onClick={() => void boardQuery.refetch()}>Làm mới sơ đồ</Button>}
        />
      ) : null}
      {isBoardRefreshing ? (
        <div className="staff-table-board-refresh-strip" aria-live="polite">
          Đang đồng bộ trạng thái bàn trong nền, sơ đồ không bị làm trống.
        </div>
      ) : null}

      <div className="staff-table-board-grid">
        {(boardData?.data ?? []).map((row) => {
          const isSelected = row.table_id === selectedTableId;
          const cardContext = buildTableCardContext(row);

          return (
            <Card
              key={row.table_id}
              role="button"
              tabIndex={0}
              aria-pressed={isSelected}
              aria-label={`${row.table_code} - ${translateUiCode(row.board_state)}. ${cardContext.value}. ${tableCardActionCopy(row)}.`}
              className={`staff-table-board-card staff-table-board-card-${tableTone(row.board_state)} ${isSelected ? 'staff-card-selected' : ''}`}
              onClick={() => selectBoardRow(row)}
              onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                  event.preventDefault();
                  selectBoardRow(row);
                }
              }}
            >
              <div className="staff-table-board-card-shell">
                <div className="staff-table-board-card-top">
                  <span className="staff-table-board-zone">{row.zone ?? 'Chưa có khu'}</span>
                  <StatusChip label={row.board_state} tone={tableTone(row.board_state)} />
                </div>

                <div className="staff-table-board-card-identity">
                  <Typography.Title level={4}>{row.table_code}</Typography.Title>
                  <span>{row.capacity.seats ?? 'Không có'} chỗ</span>
                </div>

                <div className="staff-table-board-card-context">
                  <span className="staff-table-board-context-label">{cardContext.label}</span>
                  <strong className="staff-table-board-context-value">{cardContext.value}</strong>
                  <span className="staff-table-board-context-meta">{cardContext.meta}</span>
                </div>

                <div className="staff-table-board-card-footer">
                  <div className="staff-table-board-next-action">
                    <span>Tiếp theo</span>
                    <strong>{tableCardActionCopy(row)}</strong>
                  </div>
                </div>
              </div>
            </Card>
          );
        })}
      </div>

      <Card
        className="staff-table-board-unassigned-card"
        title="Danh sách đặt bàn chưa gán"
        extra={<StatusChip label={`${boardData?.unassigned_reservations.length ?? 0} đặt bàn`} />}
      >
        {(boardData?.unassigned_reservations.length ?? 0) === 0 ? (
          <EmptyBlock
            title="Không có đặt bàn chưa gán"
            description="Hiện tại không có đặt bàn nào đang chờ được gán bàn."
          />
        ) : (
          <Suspense fallback={<InlineLoading tip="Đang tải danh sách đặt bàn chưa gán..." />}>
            <TableBoardUnassignedReservationsTable
              reservations={boardData?.unassigned_reservations ?? []}
              assignBestFitPending={assignBestFitMutation.isPending}
              onAssignBestFit={(reservation) => assignBestFitMutation.mutate(reservation)}
              onOpenDetail={(reservation) =>
                navigate(`/reservations?${buildJourneySearch({
                  source: 'board',
                  reservationId: reservation.reservation_id,
                  reservationRowVersion: reservation.row_version,
                  tableId: selectedTable?.table_id ?? undefined,
                })}`)
              }
            />
          </Suspense>
        )}
      </Card>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={14} style={{ width: '100%' }} className="staff-table-board-side">
      <MutationStatusNotice
        feedback={mutationFeedback.feedback}
        onDismiss={mutationFeedback.resetFeedback}
        onRetry={() => {
          void refreshBoardWorkspace({
            reservationId: selectedTable?.reservation?.reservation_id ?? null,
            tableIds: selectedTable ? [selectedTable.table_id] : [],
          });
        }}
      />
      <Card className="staff-table-board-inspector" title="Ngữ cảnh bàn đang chọn">
        {!selectedTable ? (
          <div className="staff-table-board-inspector-empty">
            <EmptyBlock
              title="Chưa chọn bàn"
              description="Chọn trực tiếp một thẻ bàn để mở ngữ cảnh phục vụ, đặt bàn phù hợp và hành động tiếp theo."
            />
          </div>
        ) : (
          <Space orientation="vertical" size={12} style={{ width: '100%' }}>
            <div className="staff-table-board-inspector-hero">
              <div className="staff-table-board-inspector-hero-head">
                <div>
                  <span className="staff-table-board-inspector-label">Bàn đang xử lý</span>
                  <Typography.Title level={3}>{selectedTable.table_code}</Typography.Title>
                </div>
                <StatusChip label={selectedTable.realtime_status} tone={tableTone(selectedTable.realtime_status)} />
              </div>
              <div className="staff-table-board-inspector-meta">
                <span>{selectedTable.zone ?? 'Chưa có khu'}</span>
                <span>{selectedTable.capacity.seats ?? 'Không có'} chỗ</span>
                <span>{selectedTableCardContext?.value ?? translateUiCode(selectedTable.board_state)}</span>
              </div>
              <Typography.Text type="secondary">
                Khung bảng {formatDateTime(windowRange.from)} - {formatDateTime(windowRange.to)}
              </Typography.Text>
            </div>

            {selectedTable.reservation ? (
              <Card size="small" type="inner" className="staff-table-board-inspector-section" title="Đặt bàn">
                <Space orientation="vertical" size={8}>
                  <Typography.Text strong>{selectedTable.reservation.reservation_code}</Typography.Text>
                  <Space wrap size={8}>
                    <Typography.Text>{getReservationGuestLabel(selectedTable.reservation)}</Typography.Text>
                    {isReservationSnapshotOnlyGuest(selectedTable.reservation) ? (
                      <StatusChip label={RESERVATION_SNAPSHOT_GUEST_LABEL} tone="processing" variant="freshness" />
                    ) : null}
                  </Space>
                  <Space wrap>
                    <StatusChip label={selectedTable.reservation.status} tone={reservationTone(selectedTable.reservation.status)} />
                    <Typography.Text type="secondary">
                      {selectedTable.reservation.guest_count} khách
                    </Typography.Text>
                  </Space>
                  <div className="staff-action-row">
                    <Button
                      onClick={() =>
                        navigate(`/reservations?${buildJourneySearch({
                          source: 'board',
                          tableId: selectedTable.table_id,
                          tableIds: selectedTable.reservation?.table_ids,
                          reservationId: selectedTable.reservation?.reservation_id,
                          reservationRowVersion: selectedTable.reservation?.row_version,
                        })}`)
                      }
                    >
                      Mở đặt bàn
                    </Button>
                    <Button
                      type="primary"
                      onClick={() => handleCheckIn(selectedTable)}
                      loading={checkInMutation.isPending}
                      disabled={!selectedTable.actions.check_in?.available}
                    >
                      Nhận bàn
                    </Button>
                    <Button
                      onClick={openMoveTableFormForSelectedTable}
                      loading={moveTableMutation.isPending}
                      disabled={!selectedTable.actions.move_table?.available || moveTableTargetOptions.length === 0}
                    >
                      Chuyển bàn
                    </Button>
                  </div>
                </Space>
              </Card>
            ) : null}

            {selectedTable.active_order ? (
              <Card size="small" type="inner" className="staff-table-board-inspector-section" title="Đơn đang phục vụ">
                <Space orientation="vertical" size={8}>
                  <Typography.Text strong>Đơn #{selectedTable.active_order.order_id}</Typography.Text>
                  <Space wrap>
                    <StatusChip label={selectedTable.active_order.status} tone="warning" />
                    <StatusChip label={selectedTable.active_order.order_type} />
                  </Space>
                  <div className="staff-action-row">
                    <Button
                      type="primary"
                      onClick={() =>
                        navigate(`/orders?${buildJourneySearch({
                          source: 'board',
                          tableId: selectedTable.table_id,
                          tableIds: selectedTable.reservation?.table_ids,
                          reservationId: selectedTable.reservation?.reservation_id,
                          reservationRowVersion: selectedTable.reservation?.row_version,
                          orderId: selectedTable.active_order?.order_id,
                          orderRowVersion: selectedTable.active_order?.row_version,
                        })}`)
                      }
                    >
                      Mở đơn hàng
                    </Button>
                    <Button
                      onClick={() =>
                        navigate(`/checkout?${buildJourneySearch({
                          source: 'board',
                          tableId: selectedTable.table_id,
                          tableIds: selectedTable.reservation?.table_ids,
                          reservationId: selectedTable.reservation?.reservation_id,
                          reservationRowVersion: selectedTable.reservation?.row_version,
                          orderId: selectedTable.active_order?.order_id,
                          orderRowVersion: selectedTable.active_order?.row_version,
                        })}`)
                      }
                    >
                      Mở thanh toán
                    </Button>
                  </div>
                </Space>
              </Card>
            ) : (
              <div className="staff-table-board-action-stack staff-table-board-action-panel">
                <div className="staff-table-board-action-panel-head">
                  <span>Thao tác với bàn này</span>
                  {selectedTableHasWalkInDraft ? <strong>Có bản nháp</strong> : null}
                </div>
                <Button
                  type="primary"
                  onClick={openWalkInFormForSelectedTable}
                  disabled={!selectedTable.availability.accepts_new_assignment}
                >
                  {selectedTableHasWalkInDraft ? 'Tiếp tục xếp khách' : 'Xếp khách vào bàn'}
                </Button>
                {session && can(session, 'reservation.manage') ? (
                  <Button
                    onClick={openPhoneReservationFormForSelectedTable}
                    disabled={!selectedTable.availability.accepts_new_assignment}
                  >
                    Đặt bàn hộ cho bàn này
                  </Button>
                ) : null}
                {session && can(session, 'waiting_list.manage') ? (
                  <Button
                    onClick={() =>
                      navigate(`/waiting-list?${buildJourneySearch({
                        source: 'board',
                        tableId: selectedTable.table_id,
                      })}`)
                    }
                  >
                    Thêm vào hàng chờ
                  </Button>
                ) : null}
                {session && can(session, 'table.release') && selectedTable.board_state === 'occupied_now' ? (
                  <Button
                    danger
                    onClick={() => handleReleaseTable(selectedTable)}
                    loading={releaseTableMutation.isPending}
                    disabled={selectedTable.reservation !== null || selectedTable.active_order !== null}
                  >
                    Trả bàn về Available
                  </Button>
                ) : null}
                {selectedTableHasWalkInDraft ? (
                  <Typography.Text type="secondary">
                    Đang giữ thông tin khách vãng lai đã nhập cho bàn này.
                  </Typography.Text>
                ) : null}
              </div>
            )}

            <Card size="small" type="inner" className="staff-table-board-inspector-section" title="Đặt bàn có thể gán cho bàn này">
              {selectedTable.candidate_reservations.length === 0 ? (
                <EmptyBlock
                  title="Không có gợi ý"
                  description="Hiện không có đặt bàn nào phù hợp để gán vào bàn này."
                />
              ) : (
                <Suspense fallback={<InlineLoading tip="Đang tải gợi ý gán cho bàn này..." />}>
                  <TableBoardCandidateReservationsTable
                    candidates={selectedTable.candidate_reservations}
                    assignCurrentPending={assignCurrentTableMutation.isPending}
                    onUseCurrentTable={(candidate) =>
                      assignCurrentTableMutation.mutate({
                        reservationId: candidate.reservation_id,
                        rowVersion: candidate.row_version,
                      })}
                  />
                </Suspense>
              )}
            </Card>
          </Space>
        )}
      </Card>

      <Card title="Đồng bộ sơ đồ" className="staff-table-board-sync-card">
        {boardChangesQuery.isLoading ? (
          <InlineLoading tip="Đang kiểm tra thay đổi sơ đồ..." />
        ) : boardChangesQuery.error ? (
          <ApiStateBlock
            error={boardChangesQuery.error}
            fallback="Không thể đọc luồng thay đổi của sơ đồ."
            onRetry={() => {
              void boardChangesQuery.refetch();
            }}
          />
        ) : (
          <Space orientation="vertical" size={10}>
            <Typography.Text type="secondary">
              Phiên bản realtime v{boardChangesQuery.data?.data.current_version ?? boardRealtimeVersion ?? 0}
            </Typography.Text>
            <Typography.Text type="secondary">
              Sự kiện: {boardChangesQuery.data?.data.events.length ?? 0}
            </Typography.Text>
            <Typography.Text type="secondary">
              Chu kỳ gợi ý: {(boardChangesQuery.data?.data.poll_hint_ms ?? boardData?.meta.realtime.poll_hint_ms ?? 0) / 1000} giây
            </Typography.Text>
          </Space>
        )}
      </Card>
    </Space>
  );

  return (
    <div data-testid="table-board-page">
      <SplitWorkspace main={main} side={side} variant="board-heavy" />
      {walkInOpen || phoneReservationOpen || moveTableOpen ? (
        <Suspense fallback={null}>
          <TableBoardDialogs
            walkInOpen={walkInOpen}
            walkInDraft={selectedTable ? walkInDrafts[selectedTable.table_id] ?? {} : {}}
            walkInSubmitting={walkInMutation.isPending}
            phoneReservationOpen={phoneReservationOpen}
            phoneReservationSubmitting={createPhoneReservationMutation.isPending}
            moveTableOpen={moveTableOpen}
            moveTableSubmitting={moveTableMutation.isPending}
            selectedTableCode={selectedTable?.table_code ?? null}
            moveTableReservationCode={selectedTable?.reservation?.reservation_code ?? null}
            moveTableSourceCode={selectedTable?.table_code ?? null}
            moveTableTargetOptions={moveTableTargetOptions}
            onWalkInCancel={closeWalkInForm}
            onWalkInSubmit={(values) => walkInMutation.mutate(values)}
            onWalkInValuesChange={saveWalkInDraftForSelectedTable}
            onPhoneReservationCancel={closePhoneReservationForm}
            onPhoneReservationSubmit={(values) => createPhoneReservationMutation.mutate(values)}
            onMoveTableCancel={closeMoveTableForm}
            onMoveTableSubmit={(values) => moveTableMutation.mutate(values)}
          />
        </Suspense>
      ) : null}
    </div>
  );
}
