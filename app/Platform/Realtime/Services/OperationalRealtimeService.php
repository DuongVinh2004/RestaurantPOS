<?php

declare(strict_types=1);

namespace App\Platform\Realtime\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class OperationalRealtimeService
{
    public const TOPIC_BOARD = 'board';

    public const TOPIC_WAITING_LIST = 'waiting_list';

    public const TOPIC_KITCHEN = 'kitchen';

    /**
     * @return array<string,mixed>
     */
    public function describeTopic(string $topic, string $changesUri, array $defaultRefreshTargets = []): array
    {
        $topic = $this->normalizeTopic($topic);
        $backend = $this->backendStatus();

        return [
            'enabled' => (bool) $backend['usable'],
            'topic' => $topic,
            'channel' => $this->channelName($topic),
            'current_version' => (bool) $backend['usable'] ? $this->currentVersion($topic) : 0,
            'changes_uri' => $changesUri,
            'polling_compatible' => true,
            'default_refresh_targets' => array_values(array_unique(array_map('strval', $defaultRefreshTargets))),
            'poll_hint_ms' => $this->pollHintMs(),
            'backend_status' => $backend['status'],
            'backend_store' => $backend['store'],
            'backend_driver' => $backend['driver'],
            'backend_reason' => $backend['reason'],
            'trusted_backend' => (bool) $backend['trusted'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function readTopic(string $topic, int $afterVersion = 0, int $limit = 20, ?array $branchScope = null): array
    {
        $topic = $this->normalizeTopic($topic);
        $limit = max(1, min(100, $limit));
        $afterVersion = max(0, $afterVersion);
        $branchScope = $branchScope !== null ? $this->normalizeBranchIds($branchScope) : null;
        $backend = $this->backendStatus();
        $currentVersion = (bool) $backend['usable'] ? $this->currentVersion($topic) : 0;

        if (! (bool) $backend['usable']) {
            return [
                'enabled' => false,
                'topic' => $topic,
                'channel' => $this->channelName($topic),
                'after_version' => $afterVersion,
                'current_version' => $currentVersion,
                'oldest_available_version' => $currentVersion,
                'events' => [],
                'has_changes' => false,
                'stale_cursor' => false,
                'poll_hint_ms' => $this->pollHintMs(),
                'backend_status' => $backend['status'],
                'backend_store' => $backend['store'],
                'backend_driver' => $backend['driver'],
                'backend_reason' => $backend['reason'],
                'trusted_backend' => (bool) $backend['trusted'],
            ];
        }

        $events = $this->filterEventsByBranchScope(
            $this->readRecentEvents($topic),
            $branchScope,
        );
        $oldestAvailableVersion = $events !== []
            ? (int) ($events[0]['version'] ?? 0)
            : $currentVersion;

        $staleCursor = false;
        if ($afterVersion > 0 && $currentVersion > $afterVersion) {
            $staleCursor = $events === [] || $afterVersion < max(0, $oldestAvailableVersion - 1);
        }

        $filtered = array_values(array_filter($events, static function (array $event) use ($afterVersion): bool {
            return (int) ($event['version'] ?? 0) > $afterVersion;
        }));

        return [
            'enabled' => true,
            'topic' => $topic,
            'channel' => $this->channelName($topic),
            'after_version' => $afterVersion,
            'current_version' => $currentVersion,
            'oldest_available_version' => $oldestAvailableVersion,
            'events' => array_slice($filtered, 0, $limit),
            'has_changes' => $branchScope === null ? $currentVersion > $afterVersion : $filtered !== [],
            'stale_cursor' => $staleCursor,
            'poll_hint_ms' => $this->pollHintMs(),
            'backend_status' => $backend['status'],
            'backend_store' => $backend['store'],
            'backend_driver' => $backend['driver'],
            'backend_reason' => $backend['reason'],
            'trusted_backend' => (bool) $backend['trusted'],
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  list<string>  $refreshTargets
     * @return array<string,mixed>|null
     */
    public function publishBoardEvent(string $eventType, array $payload = [], array $refreshTargets = ['board']): ?array
    {
        return $this->publish(self::TOPIC_BOARD, $eventType, $payload, $refreshTargets);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  list<string>  $refreshTargets
     * @return array<string,mixed>|null
     */
    public function publishWaitingListEvent(string $eventType, array $payload = [], array $refreshTargets = ['waiting_list']): ?array
    {
        return $this->publish(self::TOPIC_WAITING_LIST, $eventType, $payload, $refreshTargets);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  list<string>  $refreshTargets
     * @return array<string,mixed>|null
     */
    public function publishKitchenEvent(string $eventType, array $payload = [], array $refreshTargets = ['kitchen']): ?array
    {
        return $this->publish(self::TOPIC_KITCHEN, $eventType, $payload, $refreshTargets);
    }

    public function currentVersion(string $topic): int
    {
        $topic = $this->normalizeTopic($topic);
        if (! (bool) ($this->backendStatus()['usable'] ?? false)) {
            return 0;
        }

        $store = $this->store();
        $value = $store->get($this->versionKey($topic), 0);
        $version = is_numeric($value) ? (int) $value : 0;

        $events = $this->readRecentEvents($topic);
        if ($events !== []) {
            $latestEventVersion = (int) ($events[array_key_last($events)]['version'] ?? 0);
            $version = max($version, $latestEventVersion);
        }

        return max(0, $version);
    }

    public function isEnabled(): bool
    {
        return (bool) config('booking.realtime.enabled', true);
    }

    /**
     * @return array{
     *   enabled:bool,
     *   usable:bool,
     *   trusted:bool,
     *   status:string,
     *   store:?string,
     *   driver:?string,
     *   reason:?string,
     *   error:?string
     * }
     */
    public function backendStatus(): array
    {
        $store = $this->configuredStoreName();
        $driver = $this->configuredStoreDriver($store);

        if (! $this->isEnabled()) {
            return [
                'enabled' => false,
                'usable' => false,
                'trusted' => false,
                'status' => 'disabled',
                'store' => $store !== '' ? $store : null,
                'driver' => $driver,
                'reason' => 'realtime_disabled',
                'error' => null,
            ];
        }

        if ($store === '' || $driver === null) {
            return [
                'enabled' => true,
                'usable' => false,
                'trusted' => false,
                'status' => 'degraded',
                'store' => $store !== '' ? $store : null,
                'driver' => $driver,
                'reason' => 'cache_store_not_configured',
                'error' => null,
            ];
        }

        if (! $this->driverAllowed($driver)) {
            return [
                'enabled' => true,
                'usable' => false,
                'trusted' => false,
                'status' => 'degraded',
                'store' => $store,
                'driver' => $driver,
                'reason' => 'unsafe_cache_store_driver',
                'error' => null,
            ];
        }

        try {
            Cache::store($store);
        } catch (Throwable $exception) {
            return [
                'enabled' => true,
                'usable' => false,
                'trusted' => false,
                'status' => 'degraded',
                'store' => $store,
                'driver' => $driver,
                'reason' => 'cache_store_unavailable',
                'error' => $exception->getMessage(),
            ];
        }

        return [
            'enabled' => true,
            'usable' => true,
            'trusted' => true,
            'status' => 'ok',
            'store' => $store,
            'driver' => $driver,
            'reason' => null,
            'error' => null,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  list<string>  $refreshTargets
     * @return array<string,mixed>|null
     */
    private function publish(string $topic, string $eventType, array $payload, array $refreshTargets): ?array
    {
        $topic = $this->normalizeTopic($topic);
        $eventType = trim($eventType);

        if (! (bool) ($this->backendStatus()['usable'] ?? false) || $eventType === '') {
            return null;
        }

        $payload = $this->withResolvedBranchScope($payload);
        $version = $this->nextVersion($topic);
        $envelope = [
            'topic' => $topic,
            'channel' => $this->channelName($topic),
            'version' => $version,
            'type' => $eventType,
            'occurred_at' => Carbon::now('UTC')->toIso8601String(),
            'refresh_targets' => array_values(array_unique(array_filter(array_map('strval', $refreshTargets), static fn (string $value): bool => $value !== ''))),
            'payload' => $payload,
        ];

        $events = $this->normalizeEventHistory($topic, [
            ...$this->readRecentEvents($topic),
            $envelope,
        ]);
        $this->persistRecentEvents($topic, $events);

        return $envelope;
    }

    private function nextVersion(string $topic): int
    {
        $store = $this->store();
        $key = $this->versionKey($topic);
        $currentVersion = $this->currentVersion($topic);

        if ($currentVersion > 0) {
            $store->forever($key, $currentVersion);
        }

        try {
            $next = $store->increment($key);
        } catch (Throwable) {
            $current = max($currentVersion, is_numeric($store->get($key, 0)) ? (int) $store->get($key, 0) : 0);
            $next = is_numeric($current) ? ((int) $current + 1) : 1;
            $store->forever($key, $next);
        }

        if (! is_numeric($next)) {
            $next = 1;
        }

        $version = max(1, (int) $next);
        $store->forever($key, $version);

        return $version;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function readRecentEvents(string $topic): array
    {
        $store = $this->store();

        try {
            $events = $store->get($this->eventsKey($topic), []);
        } catch (Throwable) {
            $events = Cache::store('array')->get($this->eventsKey($topic), []);
        }

        if (! is_array($events)) {
            return [];
        }

        return $this->normalizeEventHistory($topic, $events);
    }

    private function versionKey(string $topic): string
    {
        return sprintf('booking:realtime:%s:version', $topic);
    }

    private function eventsKey(string $topic): string
    {
        return sprintf('booking:realtime:%s:events', $topic);
    }

    private function channelName(string $topic): string
    {
        return match ($topic) {
            self::TOPIC_WAITING_LIST => 'staff.waiting_list',
            self::TOPIC_KITCHEN => 'staff.kitchen',
            default => 'staff.board',
        };
    }

    private function normalizeTopic(string $topic): string
    {
        $topic = trim($topic);

        return match ($topic) {
            self::TOPIC_WAITING_LIST => self::TOPIC_WAITING_LIST,
            self::TOPIC_KITCHEN => self::TOPIC_KITCHEN,
            default => self::TOPIC_BOARD,
        };
    }

    private function recentEventLimit(): int
    {
        return max(10, min(200, (int) config('booking.realtime.recent_event_limit', 50)));
    }

    private function eventTtlSeconds(): int
    {
        return max(60, (int) config('booking.realtime.event_ttl_seconds', 300));
    }

    private function pollHintMs(): int
    {
        return max(500, (int) config('booking.realtime.poll_hint_ms', 5000));
    }

    /**
     * @param  list<mixed>  $events
     * @return list<array<string,mixed>>
     */
    private function normalizeEventHistory(string $topic, array $events): array
    {
        $normalizedByVersion = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $normalized = $this->normalizeEventEnvelope($topic, $event);
            if ($normalized === null) {
                continue;
            }

            $normalizedByVersion[(string) $normalized['version']] = $normalized;
        }

        $normalizedEvents = array_values($normalizedByVersion);
        usort($normalizedEvents, static fn (array $left, array $right): int => ((int) ($left['version'] ?? 0)) <=> ((int) ($right['version'] ?? 0)));

        return array_values(array_slice($normalizedEvents, -$this->recentEventLimit()));
    }

    /**
     * @param  array<string,mixed>  $event
     * @return array<string,mixed>|null
     */
    private function normalizeEventEnvelope(string $topic, array $event): ?array
    {
        $channel = $this->channelName($topic);
        $version = is_numeric($event['version'] ?? null) ? (int) $event['version'] : 0;
        $type = trim((string) ($event['type'] ?? ''));

        if (
            trim((string) ($event['topic'] ?? '')) !== $topic
            || trim((string) ($event['channel'] ?? '')) !== $channel
            || $version <= 0
            || $type === ''
        ) {
            return null;
        }

        return [
            'topic' => $topic,
            'channel' => $channel,
            'version' => $version,
            'type' => $type,
            'occurred_at' => trim((string) ($event['occurred_at'] ?? '')),
            'refresh_targets' => array_values(array_unique(array_filter(array_map('strval', (array) ($event['refresh_targets'] ?? [])), static fn (string $value): bool => $value !== ''))),
            'payload' => is_array($event['payload'] ?? null) ? $event['payload'] : [],
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $events
     * @param  list<int>|null  $branchScope
     * @return list<array<string,mixed>>
     */
    private function filterEventsByBranchScope(array $events, ?array $branchScope): array
    {
        if ($branchScope === null) {
            return $events;
        }

        if ($branchScope === []) {
            return [];
        }

        return array_values(array_filter(
            $events,
            fn (array $event): bool => array_intersect($this->eventBranchIds($event), $branchScope) !== [],
        ));
    }

    /**
     * @param  array<string,mixed>  $event
     * @return list<int>
     */
    private function eventBranchIds(array $event): array
    {
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

        return $this->normalizeBranchIds(array_merge(
            $this->extractPositiveIds($payload['branch_id'] ?? null),
            $this->extractPositiveIds($payload['branch_ids'] ?? null),
        ));
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function withResolvedBranchScope(array $payload): array
    {
        $branchIds = $this->resolveBranchIdsFromPayload($payload);
        if ($branchIds === []) {
            return $payload;
        }

        $payload['branch_ids'] = $branchIds;
        $existingBranchId = is_numeric($payload['branch_id'] ?? null) ? (int) $payload['branch_id'] : 0;
        if (count($branchIds) === 1 && $existingBranchId <= 0) {
            $payload['branch_id'] = $branchIds[0];
        }

        return $payload;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return list<int>
     */
    private function resolveBranchIdsFromPayload(array $payload): array
    {
        $branchIds = array_merge(
            $this->extractPositiveIds($payload['branch_id'] ?? null),
            $this->extractPositiveIds($payload['branch_ids'] ?? null),
        );

        if ($branchIds !== []) {
            return $this->normalizeBranchIds($branchIds);
        }

        $branchIds = array_merge(
            $branchIds,
            $this->pluckBranchIds('reservations', 'reservation_id', $this->extractPositiveIds($payload['reservation_id'] ?? null)),
            $this->pluckBranchIds('reservations', 'reservation_id', $this->extractPositiveIds($payload['reservation_ids'] ?? null)),
            $this->pluckBranchIds('restaurant_tables', 'table_id', $this->extractPositiveIds($payload['table_id'] ?? null)),
            $this->pluckBranchIds('restaurant_tables', 'table_id', $this->extractPositiveIds($payload['table_ids'] ?? null)),
            $this->pluckBranchIds('waiting_list', 'waiting_id', $this->extractPositiveIds($payload['waiting_id'] ?? null)),
            $this->pluckBranchIds('waiting_list', 'waiting_id', $this->extractPositiveIds($payload['waiting_ids'] ?? null)),
            $this->pluckBranchIds('waiting_list', 'waiting_id', $this->extractPositiveIds($payload['source_waiting_id'] ?? null)),
            $this->pluckBranchIds('waiting_list', 'waiting_id', $this->extractPositiveIds($payload['advanced_waiting_id'] ?? null)),
            $this->pluckBranchIds('table_holds', 'hold_id', $this->extractScalarIds($payload['hold_id'] ?? null)),
            $this->pluckBranchIds('table_holds', 'hold_id', $this->extractScalarIds($payload['hold_ids'] ?? null)),
            $this->pluckOrderBranchIds($this->extractPositiveIds($payload['order_id'] ?? null)),
            $this->pluckOrderBranchIds($this->extractPositiveIds($payload['order_ids'] ?? null)),
            $this->pluckTicketBranchIds($this->extractPositiveIds($payload['ticket_id'] ?? null)),
            $this->pluckTicketBranchIds($this->extractPositiveIds($payload['ticket_ids'] ?? null)),
        );

        return $this->normalizeBranchIds($branchIds);
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function pluckBranchIds(string $table, string $idColumn, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            return $this->normalizeBranchIds(
                DB::table($table)
                    ->whereIn($idColumn, $ids)
                    ->pluck('branch_id')
                    ->all(),
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<int>
     */
    private function pluckOrderBranchIds(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        try {
            return $this->normalizeBranchIds(
                DB::table('reservation_orders')
                    ->join('reservations', 'reservations.reservation_id', '=', 'reservation_orders.reservation_id')
                    ->whereIn('reservation_orders.order_id', $orderIds)
                    ->pluck('reservations.branch_id')
                    ->all(),
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $ticketIds
     * @return list<int>
     */
    private function pluckTicketBranchIds(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        try {
            return $this->normalizeBranchIds(
                DB::table('kitchen_order_item_tickets')
                    ->join('reservations', 'reservations.reservation_id', '=', 'kitchen_order_item_tickets.reservation_id')
                    ->whereIn('kitchen_order_item_tickets.ticket_id', $ticketIds)
                    ->pluck('reservations.branch_id')
                    ->all(),
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<int>
     */
    private function extractPositiveIds(mixed $value): array
    {
        $ids = [];
        foreach ((array) $value as $candidate) {
            if (! is_numeric($candidate)) {
                continue;
            }

            $id = (int) $candidate;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $this->normalizeBranchIds($ids);
    }

    /**
     * @return list<int|string>
     */
    private function extractScalarIds(mixed $value): array
    {
        $ids = [];
        foreach ((array) $value as $candidate) {
            if (is_int($candidate) || is_string($candidate)) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '') {
                    $ids[] = $candidate;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int,mixed>  $ids
     * @return list<int>
     */
    private function normalizeBranchIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $ids),
            static fn (int $id): bool => $id > 0,
        )));
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<array<string,mixed>>  $events
     */
    private function persistRecentEvents(string $topic, array $events): void
    {
        $store = $this->store();

        try {
            $store->put($this->eventsKey($topic), $events, now()->addSeconds($this->eventTtlSeconds()));
        } catch (Throwable) {
            Cache::store('array')->put($this->eventsKey($topic), $events, $this->eventTtlSeconds());
        }
    }

    private function store(): Repository
    {
        return Cache::store($this->configuredStoreName());
    }

    private function configuredStoreName(): string
    {
        return trim((string) config('booking.realtime.cache_store', ''));
    }

    private function configuredStoreDriver(string $store): ?string
    {
        if ($store === '') {
            return null;
        }

        $driver = trim((string) config('cache.stores.'.$store.'.driver', ''));

        return $driver !== '' ? $driver : null;
    }

    private function driverAllowed(string $driver): bool
    {
        if ($this->isProductionLikeEnvironment()) {
            return in_array($driver, $this->distributedStoreDrivers(), true);
        }

        return in_array($driver, array_values(array_unique(array_merge(
            $this->distributedStoreDrivers(),
            $this->localFallbackStoreDrivers(),
        ))), true);
    }

    private function isProductionLikeEnvironment(): bool
    {
        $environment = trim((string) config('app.env', app()->environment()));
        if ($environment === '') {
            return false;
        }

        return in_array($environment, $this->productionLikeEnvironments(), true);
    }

    /**
     * @return list<string>
     */
    private function productionLikeEnvironments(): array
    {
        return array_values(array_filter(array_map(
            'strval',
            (array) config('booking.realtime.production_like_environments', ['production', 'staging'])
        )));
    }

    /**
     * @return list<string>
     */
    private function distributedStoreDrivers(): array
    {
        return array_values(array_filter(array_map(
            'strval',
            (array) config('booking.realtime.distributed_store_drivers', ['redis', 'memcached', 'database', 'dynamodb'])
        )));
    }

    /**
     * @return list<string>
     */
    private function localFallbackStoreDrivers(): array
    {
        return array_values(array_filter(array_map(
            'strval',
            (array) config('booking.realtime.local_fallback_store_drivers', ['file', 'array'])
        )));
    }
}
