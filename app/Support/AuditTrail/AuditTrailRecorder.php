<?php

declare(strict_types=1);

namespace App\Support\AuditTrail;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AuditTrailRecorder
{
    public function __construct(
        private readonly AuditTrailActorResolver $actorResolver,
    ) {}

    /**
     * @param  array<string,mixed>  $structured
     * @param  array<string,mixed>  $logContext
     * @param  array<string,mixed>  $requestContext
     */
    public function record(
        string $eventName,
        array $structured,
        array $logContext = [],
        string $level = 'info',
        array $requestContext = [],
    ): bool
    {
        $subjects = $this->normalizeSubjects($structured);
        if ($subjects === []) {
            return false;
        }

        $primarySubject = $subjects[0];
        $actor = $this->actorResolver->resolve((array) ($structured['actor'] ?? []));
        $occurredAt = $this->resolveOccurredAt($structured['occurred_at'] ?? null);

        $row = [
            'actor_user_id' => $actor['user_id'],
            'actor_type' => $this->truncate($actor['type'] ?? null, 40),
            'actor_key' => $this->truncate($actor['key'] ?? null, 120),
            'entity_type' => $this->truncate((string) $primarySubject['type'], 50),
            'entity_id' => $this->truncate((string) $primarySubject['id'], 64),
            'action' => $this->truncate((string) ($structured['action'] ?? $eventName), 50) ?? $this->truncate($eventName, 50),
            'before_json' => $this->encodeJson($structured['before'] ?? null),
            'after_json' => $this->encodeJson($structured['after'] ?? null),
            'summary_json' => $this->encodeJson($structured['summary'] ?? null),
            'meta_json' => $this->encodeJson($this->buildMeta($eventName, $level, $structured, $logContext, $requestContext)),
            'request_id' => $this->truncate($requestContext['request_id'] ?? null, 64),
            'ip' => $this->truncate($requestContext['ip'] ?? null, 45),
            'user_agent' => $this->truncate($requestContext['user_agent'] ?? null, 255),
            'created_at' => $occurredAt->format('Y-m-d H:i:s.u'),
        ];

        $auditTable = (string) config('audit.tables.logs', 'audit_logs');
        $subjectTable = (string) config('audit.tables.subjects', 'audit_log_subjects');

        DB::transaction(function () use ($row, $subjects, $occurredAt, $auditTable, $subjectTable): void {
            $auditId = (int) DB::table($auditTable)->insertGetId($row);

            $subjectRows = [];
            foreach ($subjects as $subject) {
                $subjectRows[] = [
                    'audit_id' => $auditId,
                    'subject_type' => $this->truncate((string) $subject['type'], 50),
                    'subject_id' => $this->truncate((string) $subject['id'], 64),
                    'subject_role' => $this->truncate($subject['role'] ?? null, 32),
                    'created_at' => $occurredAt->format('Y-m-d H:i:s.u'),
                ];
            }

            if ($subjectRows !== []) {
                DB::table($subjectTable)->insert($subjectRows);
            }
        });

        return true;
    }

    /**
     * @param  array<string,mixed>  $structured
     * @return list<array{type:string,id:string,role:?string}>
     */
    private function normalizeSubjects(array $structured): array
    {
        $subjects = [];

        if (is_array($structured['primary_subject'] ?? null)) {
            $normalized = $this->normalizeSubject((array) $structured['primary_subject'], 'primary');
            if ($normalized !== null) {
                $subjects[] = $normalized;
            }
        }

        if (($structured['entity_type'] ?? null) !== null && ($structured['entity_id'] ?? null) !== null) {
            $normalized = $this->normalizeSubject([
                'type' => $structured['entity_type'],
                'id' => $structured['entity_id'],
                'role' => 'primary',
            ], 'primary');
            if ($normalized !== null) {
                $subjects[] = $normalized;
            }
        }

        foreach ((array) ($structured['subjects'] ?? []) as $subject) {
            if (! is_array($subject)) {
                continue;
            }

            $normalized = $this->normalizeSubject($subject, $subject['role'] ?? null);
            if ($normalized !== null) {
                $subjects[] = $normalized;
            }
        }

        $deduplicated = [];
        $seen = [];
        foreach ($subjects as $subject) {
            $key = $subject['type'].'|'.$subject['id'].'|'.($subject['role'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduplicated[] = $subject;
        }

        return $deduplicated;
    }

    /**
     * @param  array<string,mixed>  $subject
     * @return array{type:string,id:string,role:?string}|null
     */
    private function normalizeSubject(array $subject, mixed $defaultRole): ?array
    {
        $type = $this->truncate($subject['type'] ?? null, 50);
        $id = $this->truncate($subject['id'] ?? null, 64);
        if ($type === null || $id === null) {
            return null;
        }

        return [
            'type' => $type,
            'id' => $id,
            'role' => $this->truncate($subject['role'] ?? $defaultRole, 32),
        ];
    }

    private function resolveOccurredAt(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->utc();
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value)->utc();
        }

        return Carbon::now('UTC');
    }

    /**
     * @param  array<string,mixed>  $structured
     * @param  array<string,mixed>  $logContext
     * @param  array<string,mixed>  $requestContext
     * @return array<string,mixed>|null
     */
    private function buildMeta(string $eventName, string $level, array $structured, array $logContext, array $requestContext): ?array
    {
        $meta = (array) ($structured['meta'] ?? []);
        $source = $structured['source'] ?? null;
        $requestMeta = array_filter([
            'request_id' => $requestContext['request_id'] ?? null,
            'method' => $requestContext['method'] ?? null,
            'path' => $requestContext['path'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $payload = array_filter([
            'event_name' => $eventName,
            'level' => $level,
            'source' => $source,
            'request' => $requestMeta !== [] ? $requestMeta : null,
            'context' => $logContext !== [] ? $logContext : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        if ($meta !== []) {
            $payload = array_merge($payload, $meta);
        }

        return $payload !== [] ? $payload : null;
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function truncate(mixed $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? mb_substr($normalized, 0, $length) : null;
    }

}
