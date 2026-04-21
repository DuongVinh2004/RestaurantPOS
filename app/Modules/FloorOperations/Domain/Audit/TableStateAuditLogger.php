<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Domain\Audit;

use App\Support\AuditEvent;
use App\Support\AuditTrail\AuditTrailActorResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class TableStateAuditLogger
{
    /**
     * @param array<int,mixed> $beforeRows
     * @param array<int,mixed> $afterRows
     * @param array<string,mixed> $context
     */
    public static function insertTransitions(array $beforeRows, array $afterRows, string $action, ?int $actorUserId = null, array $context = [], ?Carbon $occurredAt = null): void
    {
        $records = self::buildTransitionRecords($beforeRows, $afterRows, $action, $actorUserId, $context, $occurredAt);
        if ($records === []) {
            return;
        }

        $rows = array_map(static function (array $record): array {
            $record['before_json'] = $record['before_json'] !== null ? json_encode($record['before_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $record['after_json'] = $record['after_json'] !== null ? json_encode($record['after_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $record['summary_json'] = $record['summary_json'] !== null ? json_encode($record['summary_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $record['meta_json'] = $record['meta_json'] !== null ? json_encode($record['meta_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

            return $record;
        }, $records);

        try {
            DB::table('audit_logs')->insert($rows);
        } catch (Throwable $e) {
            AuditEvent::warning('table_state_audit_insert_failed', [
                'action' => $action,
                'actor_user_id' => $actorUserId,
                'table_ids' => array_map(static fn (array $record): int => (int) $record['entity_id'], $records),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<int,mixed> $beforeRows
     * @param array<int,mixed> $afterRows
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function buildTransitionRecords(array $beforeRows, array $afterRows, string $action, ?int $actorUserId = null, array $context = [], ?Carbon $occurredAt = null): array
    {
        $occurredAt ??= Carbon::now('UTC');
        $normalizedBefore = self::normalizeRows($beforeRows);
        $normalizedAfter = self::normalizeRows($afterRows);
        $tableIds = array_values(array_unique(array_merge(array_keys($normalizedBefore), array_keys($normalizedAfter))));
        sort($tableIds);

        $request = app()->bound('request') ? request() : null;
        $actor = app(AuditTrailActorResolver::class)->resolve($actorUserId !== null ? ['user_id' => $actorUserId] : []);
        $ip = $request?->ip();
        $userAgent = $request?->userAgent();
        $requestId = $request?->attributes?->get('request_id');

        $records = [];
        foreach ($tableIds as $tableId) {
            $before = $normalizedBefore[$tableId] ?? null;
            $after = $normalizedAfter[$tableId] ?? null;

            if ($before === null && $after === null) {
                continue;
            }

            if (($before['status'] ?? null) === ($after['status'] ?? null)) {
                continue;
            }

            $afterPayload = $after;
            if ($afterPayload !== null && $context !== []) {
                $afterPayload = array_merge($afterPayload, ['context' => $context]);
            }

            $records[] = [
                'actor_user_id' => $actor['user_id'] ?? $actorUserId,
                'actor_type' => $actor['type'] ?? null,
                'actor_key' => $actor['key'] ?? null,
                'entity_type' => 'restaurant_table',
                'entity_id' => (string) $tableId,
                'action' => substr($action, 0, 50),
                'before_json' => $before,
                'after_json' => $afterPayload,
                'summary_json' => [
                    'from_status' => $before['status'] ?? null,
                    'to_status' => $after['status'] ?? null,
                ],
                'meta_json' => [
                    'source' => 'table_state_audit',
                    'context' => $context !== [] ? $context : null,
                    'request' => array_filter([
                        'request_id' => $requestId !== null ? (string) $requestId : null,
                        'path' => $request?->path(),
                        'method' => $request?->getMethod(),
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                ],
                'request_id' => $requestId !== null ? (string) $requestId : null,
                'ip' => $ip,
                'user_agent' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
                'created_at' => $occurredAt->copy()->utc()->format('Y-m-d H:i:s.u'),
            ];
        }

        return $records;
    }

    /**
     * @param array<int,mixed> $rows
     * @return array<int,array{table_id:int,status:?string,row_version:?int,updated_at:?string}>
     */
    private static function normalizeRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $tableId = self::extractInt($row, 'table_id');
            if ($tableId <= 0) {
                continue;
            }

            $updatedAt = self::extractScalar($row, 'updated_at');
            if ($updatedAt instanceof Carbon) {
                $updatedAt = $updatedAt->copy()->utc()->format('Y-m-d H:i:s.u');
            } elseif ($updatedAt instanceof \DateTimeInterface) {
                $updatedAt = Carbon::instance(\DateTimeImmutable::createFromInterface($updatedAt))->utc()->format('Y-m-d H:i:s.u');
            } elseif (is_string($updatedAt)) {
                $updatedAt = trim($updatedAt) !== '' ? $updatedAt : null;
            } else {
                $updatedAt = null;
            }

            $normalized[$tableId] = [
                'table_id' => $tableId,
                'status' => self::extractString($row, 'status'),
                'row_version' => self::extractNullableInt($row, 'row_version'),
                'updated_at' => $updatedAt,
            ];
        }

        ksort($normalized);

        return $normalized;
    }

    private static function extractInt(mixed $row, string $key): int
    {
        return (int) (self::extractScalar($row, $key) ?? 0);
    }

    private static function extractNullableInt(mixed $row, string $key): ?int
    {
        $value = self::extractScalar($row, $key);
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function extractString(mixed $row, string $key): ?string
    {
        $value = self::extractScalar($row, $key);
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private static function extractScalar(mixed $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        if (is_object($row)) {
            if (isset($row->{$key}) || property_exists($row, $key)) {
                return $row->{$key};
            }

            if (method_exists($row, 'getAttribute')) {
                return $row->getAttribute($key);
            }
        }

        return null;
    }
}

