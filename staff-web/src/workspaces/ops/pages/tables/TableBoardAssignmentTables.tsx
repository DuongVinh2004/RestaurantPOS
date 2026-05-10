import { Button, Space, Typography } from 'antd';
import type { StaffTableBoardRow, StaffTableBoardUnassignedReservation } from '../../../../shared/api/sdk';
import { formatDateTime } from '../../../../shared/utils/format';
import {
  getReservationGuestLabel,
  isReservationSnapshotOnlyGuest,
  RESERVATION_SNAPSHOT_GUEST_LABEL,
} from '../../../../domains/reservations/reservation-guest';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';

type CandidateReservation = StaffTableBoardRow['candidate_reservations'][number];

export function TableBoardUnassignedReservationsTable({
  assignBestFitPending,
  onAssignBestFit,
  onOpenDetail,
  reservations,
}: {
  assignBestFitPending: boolean;
  onAssignBestFit?: (reservation: StaffTableBoardUnassignedReservation) => void;
  onOpenDetail: (reservation: StaffTableBoardUnassignedReservation) => void;
  reservations: Array<StaffTableBoardUnassignedReservation>;
}) {
  return (
    <div className="staff-table-board-assignment-list" role="list">
      {reservations.map((reservation) => (
        <article key={reservation.reservation_id} className="staff-table-board-assignment-row" role="listitem">
          <div className="staff-table-board-assignment-main">
            <div className="staff-table-board-assignment-head">
              <Typography.Text strong>{reservation.reservation_code}</Typography.Text>
              <span className="staff-table-board-assignment-caption">
                {reservation.guest_count} khach
              </span>
            </div>

            <Space wrap size={8} className="staff-table-board-assignment-meta">
              <Typography.Text type="secondary">{getReservationGuestLabel(reservation)}</Typography.Text>
              {isReservationSnapshotOnlyGuest(reservation) ? (
                <StatusChip label={RESERVATION_SNAPSHOT_GUEST_LABEL} tone="processing" variant="freshness" />
              ) : null}
            </Space>

            <div className="staff-table-board-assignment-summary">
              <span>Bat dau: {formatDateTime(reservation.start_time)}</span>
              <span>Ban goi y: {reservation.orchestration.best_fit_table?.table_code ?? 'Chua co goi y'}</span>
            </div>
          </div>

          <div className="staff-table-board-assignment-actions">
            {onAssignBestFit ? (
              <Button onClick={() => onAssignBestFit(reservation)} loading={assignBestFitPending}>
                Gan phu hop nhat
              </Button>
            ) : null}
            <Button onClick={() => onOpenDetail(reservation)}>
              Mo chi tiet
            </Button>
          </div>
        </article>
      ))}
    </div>
  );
}

export function TableBoardCandidateReservationsTable({
  assignCurrentPending,
  candidates,
  onOpenReservation,
  onUseCurrentTable,
}: {
  assignCurrentPending: boolean;
  candidates: Array<CandidateReservation>;
  onOpenReservation?: (candidate: CandidateReservation) => void;
  onUseCurrentTable?: (candidate: CandidateReservation) => void;
}) {
  return (
    <div className="staff-table-board-assignment-list staff-table-board-assignment-list-compact" role="list">
      {candidates.map((candidate) => (
        <article key={candidate.reservation_id} className="staff-table-board-assignment-row" role="listitem">
          <div className="staff-table-board-assignment-main">
            <div className="staff-table-board-assignment-head">
              <Typography.Text strong>{candidate.reservation_code}</Typography.Text>
              <span className="staff-table-board-assignment-caption">
                {candidate.guest_count} khach
              </span>
            </div>

            <Space wrap size={8} className="staff-table-board-assignment-meta">
              <Typography.Text type="secondary">{getReservationGuestLabel(candidate)}</Typography.Text>
              {isReservationSnapshotOnlyGuest(candidate) ? (
                <StatusChip label={RESERVATION_SNAPSHOT_GUEST_LABEL} tone="processing" variant="freshness" />
              ) : null}
            </Space>

            <div className="staff-table-board-assignment-flags">
              {candidate.flags.due_soon ? <StatusChip label="Sap den" tone="warning" /> : null}
              {candidate.flags.overdue ? <StatusChip label="Qua gio" tone="error" /> : null}
              {!candidate.flags.due_soon && !candidate.flags.overdue ? (
                <span className="staff-table-board-assignment-caption">Khong co co canh bao</span>
              ) : null}
            </div>
          </div>

          <div className="staff-table-board-assignment-actions">
            {onUseCurrentTable ? (
              <Button
                size="small"
                onClick={() => onUseCurrentTable(candidate)}
                loading={assignCurrentPending}
              >
                Dung ban nay
              </Button>
            ) : null}
            {onOpenReservation ? (
              <Button size="small" onClick={() => onOpenReservation(candidate)}>
                Mo dat ban
              </Button>
            ) : null}
          </div>
        </article>
      ))}
    </div>
  );
}
