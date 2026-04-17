import { QueueSnapshotCard } from './QueueSnapshotCard';
import type { DashboardSnapshotModel } from '../dashboard-view-model';

export function ConversationSnapshotCard({
  snapshot,
  onOpen,
  loading,
  error,
}: {
  snapshot: DashboardSnapshotModel;
  onOpen: (path: string) => void;
  loading?: boolean;
  error?: string | null;
}) {
  return (
    <QueueSnapshotCard
      snapshot={snapshot}
      onOpen={onOpen}
      loading={loading}
      error={error}
    />
  );
}
