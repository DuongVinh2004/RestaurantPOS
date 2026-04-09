<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AuditTrailQueryService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 50), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));

        $query = AuditLog::query()
            ->with([
                'actorUser:user_id,full_name',
                'subjects',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('audit_id');

        if (($filters['actor_user_id'] ?? null) !== null) {
            $query->where('actor_user_id', (int) $filters['actor_user_id']);
        }

        if (($filters['actor_type'] ?? null) !== null) {
            $query->where('actor_type', (string) $filters['actor_type']);
        }

        if (($filters['action'] ?? null) !== null) {
            $query->where('action', (string) $filters['action']);
        }

        if (($filters['date_from'] ?? null) !== null) {
            $query->where('created_at', '>=', $this->normalizeDateTime((string) $filters['date_from']));
        }

        if (($filters['date_to'] ?? null) !== null) {
            $query->where('created_at', '<=', $this->normalizeDateTime((string) $filters['date_to'], true));
        }

        foreach ($this->subjectFilters($filters) as $subjectFilter) {
            $this->applySubjectFilter(
                $query,
                (string) $subjectFilter['type'],
                (string) $subjectFilter['id'],
            );
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (AuditLog $log): array => $this->present($log))
        );

        return $paginator;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function subjectFilters(array $filters): array
    {
        $subjectFilters = [];

        foreach ([
            'reservation_id' => 'reservation',
            'order_id' => 'reservation_order',
            'payment_id' => 'payment',
            'waiting_id' => 'waiting_list',
            'table_id' => 'restaurant_table',
            'cashier_shift_id' => 'cashier_shift',
        ] as $filterKey => $subjectType) {
            if (($filters[$filterKey] ?? null) === null) {
                continue;
            }

            $subjectFilters[] = [
                'type' => $subjectType,
                'id' => (string) $filters[$filterKey],
            ];
        }

        if (($filters['subject_type'] ?? null) !== null && ($filters['subject_id'] ?? null) !== null) {
            $subjectFilters[] = [
                'type' => (string) $filters['subject_type'],
                'id' => (string) $filters['subject_id'],
            ];
        }

        return $subjectFilters;
    }

    private function applySubjectFilter($query, string $subjectType, string $subjectId): void
    {
        $query->where(function ($inner) use ($subjectType, $subjectId): void {
            $inner
                ->where(function ($primary) use ($subjectType, $subjectId): void {
                    $primary
                        ->where('entity_type', $subjectType)
                        ->where('entity_id', $subjectId);
                })
                ->orWhereHas('subjects', function ($subjectQuery) use ($subjectType, $subjectId): void {
                    $subjectQuery
                        ->where('subject_type', $subjectType)
                        ->where('subject_id', $subjectId);
                });
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function present(AuditLog $log): array
    {
        $meta = is_array($log->meta_json) ? $log->meta_json : [];
        $subjects = collect($log->subjects)
            ->map(static fn ($subject): array => [
                'type' => (string) $subject->subject_type,
                'id' => (string) $subject->subject_id,
                'role' => $subject->subject_role !== null ? (string) $subject->subject_role : null,
            ])
            ->prepend([
                'type' => (string) $log->entity_type,
                'id' => (string) $log->entity_id,
                'role' => 'primary',
            ])
            ->unique(static fn (array $subject): string => implode('|', [
                $subject['type'],
                $subject['id'],
                (string) ($subject['role'] ?? ''),
            ]))
            ->values()
            ->all();

        return [
            'audit_id' => (int) $log->audit_id,
            'action' => (string) $log->action,
            'occurred_at' => $log->created_at?->utc()->toIso8601String(),
            'primary_subject' => [
                'type' => (string) $log->entity_type,
                'id' => (string) $log->entity_id,
            ],
            'subjects' => $subjects,
            'actor' => [
                'user_id' => $log->actor_user_id !== null ? (int) $log->actor_user_id : null,
                'type' => $log->actor_type !== null ? (string) $log->actor_type : null,
                'key' => $log->actor_key !== null ? (string) $log->actor_key : null,
                'user' => $log->actorUser
                    ? [
                        'user_id' => (int) $log->actorUser->user_id,
                        'full_name' => (string) ($log->actorUser->full_name ?? ''),
                    ]
                    : null,
            ],
            'request' => [
                'request_id' => $log->request_id !== null ? (string) $log->request_id : null,
                'ip' => $log->ip !== null ? (string) $log->ip : null,
                'user_agent' => $log->user_agent !== null ? (string) $log->user_agent : null,
                'method' => data_get($meta, 'request.method'),
                'path' => data_get($meta, 'request.path'),
            ],
            'before' => is_array($log->before_json) ? $log->before_json : null,
            'after' => is_array($log->after_json) ? $log->after_json : null,
            'summary' => is_array($log->summary_json) ? $log->summary_json : null,
            'meta' => $meta !== [] ? $meta : null,
        ];
    }

    private function normalizeDateTime(string $value, bool $endOfDay = false): string
    {
        $date = trim($value);
        if ($date === '') {
            return now('UTC')->toDateTimeString();
        }

        $carbon = Carbon::parse($date);

        return ($endOfDay ? $carbon->endOfDay() : $carbon->startOfDay())->utc()->toDateTimeString();
    }
}
