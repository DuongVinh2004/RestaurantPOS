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
  kitchenStationDisplayName,
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
        label: kitchenStationDisplayName(station),
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
      <section className="staff-kitchen-hero" aria-label="Tình trạng khu bếp">
        <div className="staff-kitchen-hero-copy">
          <span className="staff-eyebrow">Khu bếp</span>
          <h2>Điều phối món</h2>
          <p>Chọn đúng chi nhánh và trạm bếp, sau đó xử lý phiếu theo từng trạng thái cho đến khi hết hàng đợi.</p>
        </div>

        <div className="staff-kitchen-hero-metrics" aria-label="Tải việc hiện tại của bếp">
          <Metric label="Chờ làm" value={workload.queued} tone="warning" />
          <Metric label="Đang làm" value={workload.fired} tone="processing" />
          <Metric label="Sẵn sàng" value={workload.ready} tone="success" />
          <Metric label="Đồng bộ" value={realtimeSummary.eventCount} tone={realtimeSummary.tone} />
        </div>
      </section>

      {!isOnline ? (
        <InlineWarning
          title="Kết nối bếp đang ngoại tuyến"
          description="Danh sách trạm và thao tác phiếu sẽ tự tải lại khi trình duyệt kết nối lại."
        />
      ) : null}

      {blockingState ?? (
        <>
          {stationsQuery.isLoading ? <InlineLoading tip="Đang tải trạm bếp..." /> : null}
          {stationsQuery.error ? (
            <TransientFailureState
              title="Chưa tải được trạm bếp"
              description={formatApiError(stationsQuery.error, 'Không thể tải danh sách trạm bếp.')}
              primaryAction={<Button onClick={() => stationsQuery.refetch()}>Tải lại</Button>}
            />
          ) : null}

          {!stationsQuery.isLoading && !stationsQuery.error && stations.length === 0 ? (
            <EmptyBlock
              title="Chưa có trạm bếp"
              description="Chi nhánh đang chọn chưa có trạm bếp đang hoạt động."
            />
          ) : null}

          {!stationsQuery.isLoading && !stationsQuery.error && stations.length > 0 ? (
            <div className="staff-kitchen-landing-grid">
              <section className="staff-kitchen-panel" aria-label="Chọn trạm bếp">
                <div className="staff-kitchen-section-head">
                  <div>
                    <span className="staff-eyebrow">Trạm bếp</span>
                    <h3>Chọn line làm việc</h3>
                  </div>
                  <StatusChip
                    label={stationContext.selectedStation ? kitchenStationDisplayName(stationContext.selectedStation) : 'Cần chọn trạm'}
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

                <div className="staff-kitchen-station-list staff-kitchen-station-list-landing" role="list" aria-label="Trạm bếp được phép thao tác">
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
                            <strong>{kitchenStationDisplayName(station)}</strong>
                          </div>
                          <span className="staff-kitchen-station-state">
                            Mở line
                          </span>
                        </div>
                        <p className="staff-kitchen-station-summary">{stationWorkloadLabel(station)}</p>
                      </div>
                    </button>
                  ))}
                </div>
              </section>

              <section className="staff-kitchen-panel staff-kitchen-panel-journey" aria-label="Quy trình bếp">
                <div className="staff-kitchen-section-head">
                  <div>
                    <span className="staff-eyebrow">Quy trình</span>
                    <h3>Xử lý phiếu nhanh</h3>
                  </div>
                  <StatusChip label={realtimeSummary.label} tone={realtimeSummary.tone} />
                </div>

                <div className="staff-kitchen-journey-list">
                  <JourneyStep title="1. Chọn trạm" description="Kiểm tra chi nhánh và trạm được gán trước khi thao tác." />
                  <JourneyStep title="2. Theo dõi line" description="Phiếu được chia rõ thành Chờ làm, Đang làm và Sẵn sàng." />
                  <JourneyStep title="3. Thao tác nhanh" description="Bắt đầu làm, báo đã xong hoặc gọi lại chỉ khi phiếu cho phép." />
                  <JourneyStep title="4. Đồng bộ" description="Bảng phiếu tự làm mới theo luồng thay đổi và khi kết nối trở lại." />
                </div>

                <Button
                  type="primary"
                  size="large"
                  disabled={!stationContext.selectedStation || !isOnline}
                  onClick={() => openBoard()}
                >
                  Mở bảng phiếu bếp
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
        title="Khu bếp đang ngoại tuyến"
        description="Kết nối lại trước khi tải hàng đợi trạm hoặc đổi trạng thái phiếu."
        primaryAction={<Button onClick={onRetry}>Đồng bộ lại</Button>}
      />
    );
  }

  return null;
}
