import { Button, Card, Typography } from 'antd';
import { MoveRight } from 'lucide-react';
import { EmptyBlock, InlineError, InlineLoading } from '../../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../../shared/ui/status/StatusChip';
import type { DashboardTableBoardModel } from '../dashboard-view-model';

export function MiniTableBoardCard({
  snapshot,
  onOpen,
  loading = false,
  error = null,
}: {
  snapshot: DashboardTableBoardModel;
  onOpen: (path: string) => void;
  loading?: boolean;
  error?: string | null;
}) {
  return (
    <Card className="staff-dashboard-card staff-dashboard-card-board">
      <div className="staff-dashboard-card-head">
        <div>
          <Typography.Title level={4}>{snapshot.title}</Typography.Title>
          <Typography.Paragraph type="secondary" className="staff-dashboard-card-description">
            {snapshot.description}
          </Typography.Paragraph>
          {snapshot.priorityHint ? (
            <Typography.Paragraph className="staff-dashboard-card-priority-hint" type="secondary">
              {snapshot.priorityHint}
            </Typography.Paragraph>
          ) : null}
        </div>

        <div className="staff-dashboard-card-head-actions">
          {snapshot.urgencyLabel ? (
            <span className={`staff-dashboard-urgency-badge staff-dashboard-urgency-badge-${snapshot.urgencyTone ?? 'default'}`}>
              {snapshot.urgencyLabel}
            </span>
          ) : null}
          <Button type="link" className="staff-link-button" onClick={() => onOpen(snapshot.path)}>
            {snapshot.actionLabel}
          </Button>
        </div>
      </div>

      <div className="staff-dashboard-metric-grid">
        {snapshot.metrics.map((metric) => (
          <div
            key={`${snapshot.title}-${metric.label}`}
            className={`staff-dashboard-metric-card staff-dashboard-metric-card-${metric.tone ?? 'default'}`}
          >
            <span className="staff-dashboard-metric-label">{metric.label}</span>
            <span className="staff-dashboard-metric-value">{metric.value}</span>
          </div>
        ))}
      </div>

      {loading ? <InlineLoading tip="Đang tải sơ đồ bàn..." /> : null}
      {error ? <InlineError message={error} /> : null}
      {!loading && !error && snapshot.boardCells.length === 0 ? (
        <EmptyBlock title={snapshot.emptyTitle} description={snapshot.emptyDescription} />
      ) : null}

      {!loading && !error && snapshot.boardCells.length > 0 ? (
        <>
          <div className="staff-dashboard-board-grid">
            {snapshot.boardCells.map((cell) => (
              <button
                key={cell.key}
                type="button"
                className={`staff-dashboard-board-cell staff-dashboard-board-cell-${cell.stateTone}`}
                onClick={() => onOpen(cell.path)}
              >
                <span className="staff-dashboard-board-cell-label">{cell.label}</span>
                <span className="staff-dashboard-board-cell-meta">{cell.meta}</span>
                <StatusChip label={cell.stateLabel} tone={cell.stateTone} />
              </button>
            ))}
          </div>

          <div className="staff-dashboard-subsection">
            <div className="staff-dashboard-subsection-head">
              <Typography.Text strong>Bàn cần chú ý</Typography.Text>
              <Typography.Text type="secondary">Ưu tiên các bàn đang mở đơn hoặc có hành động kế tiếp.</Typography.Text>
            </div>

            <div className="staff-dashboard-list">
              {snapshot.attentionItems.map((item) => (
                <button
                  key={item.key}
                  type="button"
                  className="staff-dashboard-list-item staff-dashboard-list-item-button"
                  onClick={() => onOpen(item.path)}
                >
                  <div className="staff-dashboard-list-copy">
                    <div className="staff-dashboard-list-title-row">
                      <Typography.Text strong>{item.title}</Typography.Text>
                      {item.statusLabel ? <StatusChip label={item.statusLabel} tone={item.statusTone} /> : null}
                    </div>
                    <Typography.Text>{item.subtitle}</Typography.Text>
                    {item.meta ? <Typography.Text type="secondary">{item.meta}</Typography.Text> : null}
                  </div>

                  <span className="staff-dashboard-inline-cta">
                    {item.actionLabel ?? 'Mở'}
                    <MoveRight size={14} strokeWidth={2.2} />
                  </span>
                </button>
              ))}
            </div>
          </div>
        </>
      ) : null}
    </Card>
  );
}
