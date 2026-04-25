import { useEffect, useMemo } from 'react';
import { Button } from 'antd';
import { useNavigate } from 'react-router-dom';
import type { KitchenStation } from '../../../../shared/api/sdk';
import { formatApiError } from '../../../../shared/api/errors';
import { buildJourneySearch } from '../../../../app/router/journey';
import { staffRoutePaths } from '../../../../app/router/workspace-paths';
import { useAuthStore } from '../../../../app/store/auth-store';
import { useFlowStore } from '../../../../app/store/flow-store';
import { useOnlineStatus } from '../../../../shared/hooks/useOnlineStatus';
import {
  BranchPolicyState,
  EmptyBlock,
  InlineLoading,
  InlineWarning,
  PermissionDeniedState,
  TransientFailureState,
} from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import {
  resolveKitchenBranchGuard,
  resolveKitchenStationContext,
  resolveKitchenWorkspaceGuard,
  stationWorkloadLabel,
  summarizeKitchenRealtime,
} from '../../../../domains/kitchen/kitchen-workspace';
import {
  useKitchenChangesQuery,
  useKitchenStationsQuery,
} from '../../../../domains/kitchen/useKitchenWorkspace';

export function KitchenLandingPage() {
  const navigate = useNavigate();
  const session = useAuthStore((state) => state.session);
  const branchId = useFlowStore((state) => state.branchId);
  const selectedStationId = useFlowStore((state) => state.selectedStationId);
  const setStationContext = useFlowStore((state) => state.setStationContext);
  const isOnline = useOnlineStatus();
  const workspaceGuard = resolveKitchenWorkspaceGuard(session);
  const branchGuard = resolveKitchenBranchGuard(session, branchId);
  const canLoadStations = !workspaceGuard && !branchGuard && isOnline;

  const stationsQuery = useKitchenStationsQuery({
    branchId,
    enabled: canLoadStations,
  });
  const stations = useMemo(() => stationsQuery.data?.data ?? [], [stationsQuery.data?.data]);
  const stationContext = useMemo(
    () => resolveKitchenStationContext({
      session,
      stations,
      requestedStationId: selectedStationId,
    }),
    [selectedStationId, session, stations],
  );
  const realtimeVersion = stationsQuery.data?.meta?.realtime.current_version ?? null;
  const changesQuery = useKitchenChangesQuery({
    branchId,
    currentVersion: realtimeVersion,
    enabled: canLoadStations,
  });
  const realtimeSummary = summarizeKitchenRealtime(changesQuery.data?.data);
  const workload = useMemo(
    () => stations.reduce((carry, station) => ({
      queued: carry.queued + station.ticket_counts.queued,
      fired: carry.fired + station.ticket_counts.fired,
      ready: carry.ready + station.ticket_counts.ready,
    }), { queued: 0, fired: 0, ready: 0 }),
    [stations],
  );

  useEffect(() => {
    if (!isOnline) {
      return;
    }

    void stationsQuery.refetch();
    void changesQuery.refetch();
  }, [changesQuery, isOnline, stationsQuery]);

  function openBoard(station: KitchenStation | null = stationContext.selectedStation) {
    const stationId = station?.station_id ?? stationContext.selectedStationId;
    const search = buildJourneySearch({
      source: 'kitchen',
      stationId: stationId ?? undefined,
    });

    if (station) {
      setStationContext({
        stationId: station.station_id,
        label: station.name,
        source: 'kitchen',
      });
    }

    navigate({
      pathname: staffRoutePaths.kitchen.board,
      search: search ? `?${search}` : '',
    });
  }

  const blockingState = renderKitchenBlockingState({
    workspaceGuard,
    branchGuard,
    stationGuard: stationContext.guard?.kind === 'missing-assigned-station' ? stationContext.guard : null,
    isOnline,
    onRetry: () => {
      void stationsQuery.refetch();
      void changesQuery.refetch();
    },
  });

  return (
    <div className="staff-kitchen-workspace" data-testid="kitchen-landing-page">
      <section className="staff-kitchen-hero" aria-label="Kitchen workspace status">
        <div className="staff-kitchen-hero-copy">
          <span className="staff-eyebrow">Kitchen workspace</span>
          <h2>Line control</h2>
          <p>Lock a branch and station, then stay in the ticket queue until the line is clear.</p>
        </div>

        <div className="staff-kitchen-hero-metrics" aria-label="Kitchen workload">
          <Metric label="Queued" value={workload.queued} tone="warning" />
          <Metric label="In prep" value={workload.fired} tone="processing" />
          <Metric label="Ready" value={workload.ready} tone="success" />
          <Metric label="Sync" value={realtimeSummary.eventCount} tone={realtimeSummary.tone} />
        </div>
      </section>

      {!isOnline ? (
        <InlineWarning
          title="Kitchen connection is offline"
          description="Station reads and ticket actions will resume when the browser reconnects."
        />
      ) : null}

      {blockingState ?? (
        <>
          {stationsQuery.isLoading ? <InlineLoading tip="Loading kitchen stations..." /> : null}
          {stationsQuery.error ? (
            <TransientFailureState
              title="Kitchen stations are not available"
              description={formatApiError(stationsQuery.error, 'Could not load kitchen stations.')}
              primaryAction={<Button onClick={() => stationsQuery.refetch()}>Retry</Button>}
            />
          ) : null}

          {!stationsQuery.isLoading && !stationsQuery.error && stations.length === 0 ? (
            <EmptyBlock
              title="No kitchen stations are configured"
              description="The selected branch has no active station surface for the kitchen workspace."
            />
          ) : null}

          {!stationsQuery.isLoading && !stationsQuery.error && stations.length > 0 ? (
            <div className="staff-kitchen-landing-grid">
              <section className="staff-kitchen-panel" aria-label="Station selection">
                <div className="staff-kitchen-section-head">
                  <div>
                    <span className="staff-eyebrow">Station context</span>
                    <h3>Select station</h3>
                  </div>
                  <StatusChip
                    label={stationContext.selectedStation ? stationContext.selectedStation.name : 'Selection required'}
                    tone={stationContext.selectedStation ? 'processing' : 'warning'}
                  />
                </div>

                {stationContext.guard?.kind === 'station-selection-required' ? (
                  <InlineWarning
                    title={stationContext.guard.title}
                    description={stationContext.guard.description}
                  />
                ) : null}

                {stationContext.guard?.kind === 'invalid-station' ? (
                  <BranchPolicyState
                    title={stationContext.guard.title}
                    description={stationContext.guard.description}
                    meta={stationContext.guard.meta}
                  />
                ) : null}

                <div className="staff-kitchen-station-list staff-kitchen-station-list-landing" role="list" aria-label="Assigned kitchen stations">
                  {stationContext.selectableStations.map((station) => (
                    <button
                      key={station.station_id}
                      type="button"
                      className={`staff-kitchen-station-card${station.station_id === stationContext.selectedStationId ? ' staff-card-selected' : ''}`}
                      aria-pressed={station.station_id === stationContext.selectedStationId}
                      onClick={() => openBoard(station)}
                    >
                      <div className="staff-kitchen-station-card-main">
                        <div className="staff-kitchen-station-card-head">
                          <div className="staff-kitchen-station-copy">
                            <span className="staff-kitchen-station-code">{station.code}</span>
                            <strong>{station.name}</strong>
                          </div>
                          <span className="staff-kitchen-station-state">
                            Open line
                          </span>
                        </div>
                        <p className="staff-kitchen-station-summary">{stationWorkloadLabel(station)}</p>
                      </div>
                    </button>
                  ))}
                </div>
              </section>

              <section className="staff-kitchen-panel staff-kitchen-panel-journey" aria-label="Kitchen journey">
                <div className="staff-kitchen-section-head">
                  <div>
                    <span className="staff-eyebrow">Line journey</span>
                    <h3>Run the queue</h3>
                  </div>
                  <StatusChip label={realtimeSummary.label} tone={realtimeSummary.tone} />
                </div>

                <div className="staff-kitchen-journey-list">
                  <JourneyStep title="1. Station" description="Confirm assigned station and branch context before taking ticket actions." />
                  <JourneyStep title="2. Queue" description="Work Queued, In prep, and Ready lanes without leaving the kitchen workspace." />
                  <JourneyStep title="3. Fast actions" description="Fire, bump, and recall only when the ticket lifecycle allows the transition." />
                  <JourneyStep title="4. Live changes" description="The line refreshes from the kitchen change cursor and refetches on reconnect." />
                </div>

                <Button
                  type="primary"
                  size="large"
                  disabled={!stationContext.selectedStation || !isOnline}
                  onClick={() => openBoard()}
                >
                  Open ticket queue
                </Button>
              </section>
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}

function Metric({
  label,
  value,
  tone,
}: {
  label: string;
  value: number;
  tone: 'success' | 'warning' | 'processing' | 'default';
}) {
  return (
    <div className={`staff-kitchen-metric staff-kitchen-metric-${tone}`}>
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function JourneyStep({ title, description }: { title: string; description: string }) {
  return (
    <div className="staff-kitchen-journey-step">
      <strong>{title}</strong>
      <span>{description}</span>
    </div>
  );
}

function renderKitchenBlockingState({
  workspaceGuard,
  branchGuard,
  stationGuard,
  isOnline,
  onRetry,
}: {
  workspaceGuard: ReturnType<typeof resolveKitchenWorkspaceGuard>;
  branchGuard: ReturnType<typeof resolveKitchenBranchGuard>;
  stationGuard: ReturnType<typeof resolveKitchenStationContext>['guard'];
  isOnline: boolean;
  onRetry: () => void;
}) {
  if (workspaceGuard) {
    return (
      <PermissionDeniedState
        variant="page"
        title={workspaceGuard.title}
        description={workspaceGuard.description}
      />
    );
  }

  if (branchGuard) {
    return (
      <BranchPolicyState
        variant="page"
        title={branchGuard.title}
        description={branchGuard.description}
        meta={branchGuard.meta}
      />
    );
  }

  if (stationGuard) {
    return (
      <BranchPolicyState
        variant="page"
        title={stationGuard.title}
        description={stationGuard.description}
        meta={stationGuard.meta}
      />
    );
  }

  if (!isOnline) {
    return (
      <TransientFailureState
        variant="page"
        title="Kitchen workspace is offline"
        description="Reconnect before loading station queues or changing ticket state."
        primaryAction={<Button onClick={onRetry}>Retry sync</Button>}
      />
    );
  }

  return null;
}
