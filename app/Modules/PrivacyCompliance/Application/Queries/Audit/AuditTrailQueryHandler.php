<?php

declare(strict_types=1);

namespace App\Modules\PrivacyCompliance\Application\Queries\Audit;

use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\PrivacyCompliance\Domain\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuditTrailQueryHandler
{
    public function __construct(
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    public function paginate(array $filters = [], ?int $staffActorUserId = null): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 50), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $branchScope = $this->branchContextService->branchScopeOrAccessible(
            $staffActorUserId,
            isset($filters['branch_id']) && $filters['branch_id'] !== null ? (int) $filters['branch_id'] : null,
        );

        $query = AuditLog::query()
            ->with([
                'actorUser:user_id,full_name',
                'subjects',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('audit_id');

        $this->applyBranchScopeFilter($query, $branchScope);

        if (($filters['actor_user_id'] ?? null) !== null) {
            $query->where('actor_user_id', (int) $filters['actor_user_id']);
        }

        if (($filters['actor_type'] ?? null) !== null) {
            $query->where('actor_type', (string) $filters['actor_type']);
        }

        if (($filters['action'] ?? null) !== null) {
            $query->where('action', (string) $filters['action']);
        }

        if (($filters['request_id'] ?? null) !== null) {
            $query->where('request_id', (string) $filters['request_id']);
        }

        if (($filters['date_from'] ?? null) !== null) {
            $query->where('created_at', '>=', $this->normalizeDateTime((string) $filters['date_from']));
        }

        if (($filters['date_to'] ?? null) !== null) {
            $query->where('created_at', '<=', $this->normalizeDateTime((string) $filters['date_to'], true));
        }

        if (($filters['q'] ?? null) !== null) {
            $this->applySearchFilter($query, (string) $filters['q']);
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

    private function applyBranchFilter($query, int $branchId): void
    {
        $query->where(function ($branchQuery) use ($branchId): void {
            $this->applyBranchContextFilter($branchQuery, $branchId);

            foreach ($this->branchOwnedSubjectLookups($branchId) as $subjectType => $idQuery) {
                $branchQuery->orWhere(function ($entityQuery) use ($subjectType, $idQuery): void {
                    $entityQuery
                        ->where('entity_type', $subjectType)
                        ->whereIn('entity_id', $idQuery);
                });

                $branchQuery->orWhereHas('subjects', function ($subjectQuery) use ($subjectType, $idQuery): void {
                    $subjectQuery
                        ->where('subject_type', $subjectType)
                        ->whereIn('subject_id', $idQuery);
                });
            }
        });
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function applyBranchScopeFilter($query, array $branchIds): void
    {
        $normalizedBranchIds = array_values(array_unique(array_map(
            static fn ($value): int => (int) $value,
            array_filter($branchIds, static fn ($value): bool => $value !== null && $value !== ''),
        )));

        if ($normalizedBranchIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($scopeQuery) use ($normalizedBranchIds): void {
            foreach ($normalizedBranchIds as $index => $branchId) {
                if ($index === 0) {
                    $this->applyBranchFilter($scopeQuery, $branchId);

                    continue;
                }

                $scopeQuery->orWhere(function ($branchQuery) use ($branchId): void {
                    $this->applyBranchFilter($branchQuery, $branchId);
                });
            }
        });
    }

    private function applyBranchContextFilter($query, int $branchId): void
    {
        $driver = (string) DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.branch_id')) = ?", [(string) $branchId])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.request.branch_id')) = ?", [(string) $branchId]);

            return;
        }

        $query
            ->whereRaw("json_extract(meta_json, '$.branch_id') = ?", [$branchId])
            ->orWhereRaw("json_extract(meta_json, '$.request.branch_id') = ?", [$branchId]);
    }

    /**
     * @return array<string,mixed>
     */
    private function branchOwnedSubjectLookups(int $branchId): array
    {
        return [
            'reservation' => DB::table('reservations')
                ->select('reservation_id')
                ->where('branch_id', $branchId),
            'reservation_order' => DB::table('reservation_orders')
                ->select('reservation_orders.order_id')
                ->join('reservations', 'reservations.reservation_id', '=', 'reservation_orders.reservation_id')
                ->where('reservations.branch_id', $branchId),
            'payment' => DB::table('payments')
                ->select('payment_id')
                ->where('branch_id', $branchId),
            'waiting_list' => DB::table('waiting_list')
                ->select('waiting_id')
                ->where('branch_id', $branchId),
            'restaurant_table' => DB::table('restaurant_tables')
                ->select('table_id')
                ->where('branch_id', $branchId),
            'cashier_shift' => DB::table('cashier_shifts')
                ->select('cashier_shift_id')
                ->where('branch_id', $branchId),
        ];
    }

    private function applySearchFilter($query, string $searchTerm): void
    {
        $term = trim($searchTerm);
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function ($searchQuery) use ($like): void {
            $searchQuery
                ->where('action', 'like', $like)
                ->orWhere('request_id', 'like', $like)
                ->orWhere('entity_type', 'like', $like)
                ->orWhere('entity_id', 'like', $like)
                ->orWhere('actor_key', 'like', $like)
                ->orWhereHas('actorUser', function ($actorQuery) use ($like): void {
                    $actorQuery->where('full_name', 'like', $like);
                })
                ->orWhereHas('subjects', function ($subjectQuery) use ($like): void {
                    $subjectQuery
                        ->where('subject_type', 'like', $like)
                        ->orWhere('subject_id', 'like', $like)
                        ->orWhere('subject_role', 'like', $like);
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
                'branch_id' => $this->presentBranchId($meta),
            ],
            'before' => is_array($log->before_json) ? $log->before_json : null,
            'after' => is_array($log->after_json) ? $log->after_json : null,
            'summary' => is_array($log->summary_json) ? $log->summary_json : null,
            'meta' => $meta !== [] ? $meta : null,
        ];
    }

    private function presentBranchId(array $meta): ?int
    {
        $candidate = data_get($meta, 'branch_id', data_get($meta, 'request.branch_id'));

        return is_numeric($candidate) ? (int) $candidate : null;
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
