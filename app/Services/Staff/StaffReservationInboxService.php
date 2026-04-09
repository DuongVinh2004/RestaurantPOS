<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\Listing\SafeLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class StaffReservationInboxService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $bucket = (string) ($filters['bucket'] ?? 'upcoming');
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $sortBy = $this->resolveSortBy((string) ($filters['sort_by'] ?? 'start_time'));
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? ($bucket === 'history' ? 'desc' : 'asc')));
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';
        $includeFinancials = (bool) ($filters['include_financials'] ?? false);

        $query = $this->newQuery($includeFinancials);

        $this->applyBucket($query, $bucket);
        $this->applyCommonFilters($query, $filters);
        $this->applyOrdering($query, $sortBy, $sortDir);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }


    public function newQuery(bool $includeFinancials = false): Builder
    {
        return Reservation::query()->select('reservations.*')->distinct()->with($this->relations($includeFinancials));
    }
    /**
     * @param Builder<Reservation> $query
     * @param array<string, mixed> $filters
     */
    public function applyCommonFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['reservation_code'])) {
            $term = trim((string) $filters['reservation_code']);
            $query->where('reservation_code', 'like', SafeLike::contains($term));
        }

        if (! empty($filters['source'])) {
            $query->where('source', trim((string) $filters['source']));
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['table_id'])) {
            $tableId = (int) $filters['table_id'];
            $query->whereHas('tables', static fn (Builder $tableQuery) => $tableQuery->where('restaurant_tables.table_id', $tableId));
        }

        if (! empty($filters['phone'])) {
            $phone = trim((string) $filters['phone']);
            $query->whereHas('user', static fn (Builder $userQuery) => $userQuery->where('phone', 'like', SafeLike::contains($phone)));
        }

        if (array_key_exists('deposit_acknowledged', $filters) && $filters['deposit_acknowledged'] !== null) {
            if ((bool) $filters['deposit_acknowledged']) {
                $query->whereNotNull('deposit_requirement_acknowledged_at');
            } else {
                $query->whereNull('deposit_requirement_acknowledged_at');
            }
        }

        if (! empty($filters['deposit_intent_status'])) {
            $intentStatus = trim((string) $filters['deposit_intent_status']);
            if ($intentStatus === 'None') {
                $query->where(function (Builder $intentQuery): void {
                    $intentQuery
                        ->whereNull('deposit_intent_status')
                        ->orWhere('deposit_intent_status', 'None');
                });
            } else {
                $query->where('deposit_intent_status', $intentStatus);
            }
        }

        if (! empty($filters['start_from'])) {
            $query->where('start_time', '>=', Carbon::parse((string) $filters['start_from'])->utc());
        }

        if (! empty($filters['start_to'])) {
            $query->where('start_time', '<=', Carbon::parse((string) $filters['start_to'])->utc());
        }

        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery
                    ->where('reservation_code', 'like', SafeLike::contains($term))
                    ->orWhere('notes', 'like', SafeLike::contains($term))
                    ->orWhereHas('user', static function (Builder $userQuery) use ($term): void {
                        $userQuery
                            ->where('full_name', 'like', SafeLike::contains($term))
                            ->orWhere('phone', 'like', SafeLike::contains($term))
                            ->orWhere('email', 'like', SafeLike::contains($term));
                    })
                    ->orWhereHas('tables', static function (Builder $tableQuery) use ($term): void {
                        $tableQuery
                            ->where('table_code', 'like', SafeLike::contains($term))
                            ->orWhere('zone', 'like', SafeLike::contains($term));
                    });
            });
        }
    }

    /**
     * @param Builder<Reservation> $query
     */
    private function applyBucket(Builder $query, string $bucket): void
    {
        $nowUtc = Carbon::now('UTC');
        $timezone = (string) config('app.timezone', 'UTC');
        $businessNow = Carbon::now($timezone);
        $from = $businessNow->copy()->startOfDay()->utc();
        $to = $businessNow->copy()->endOfDay()->utc();
        $terminalStatuses = [
            ReservationStatus::Cancelled->value,
            ReservationStatus::Expired->value,
            ReservationStatus::Completed->value,
            ReservationStatus::NoShow->value,
        ];

        switch ($bucket) {
            case 'history':
                $query->where(function (Builder $historyQuery) use ($nowUtc, $terminalStatuses): void {
                    $historyQuery
                        ->whereIn('status', $terminalStatuses)
                        ->orWhere('end_time', '<', $nowUtc);
                });
                break;
            case 'today':
                $query
                    ->whereNotIn('status', $terminalStatuses)
                    ->whereNull('cancelled_at')
                    ->whereNull('checked_out_at')
                    ->whereNull('no_show_at')
                    ->where('start_time', '<=', $to)
                    ->where('end_time', '>=', $from);
                break;
            case 'all':
                break;
            case 'upcoming':
            default:
                $query
                    ->whereNotIn('status', $terminalStatuses)
                    ->whereNull('cancelled_at')
                    ->whereNull('checked_out_at')
                    ->whereNull('no_show_at')
                    ->where('end_time', '>=', $nowUtc);
                break;
        }
    }

    /**
     * @param Builder<Reservation> $query
     */
    private function applyOrdering(Builder $query, string $sortBy, string $sortDir): void
    {
        $query->orderBy($sortBy, $sortDir);

        if ($sortBy !== 'reservation_id') {
            $query->orderBy('reservation_id', $sortDir);
        }
    }

    /**
     * @return list<string>
     */
    private function relations(bool $includeFinancials): array
    {
        $relations = [
            'user',
            'tables',
        ];

        if ($includeFinancials) {
            $relations[] = 'payments';
            $relations[] = 'appliedUserVoucher.voucher';
        }

        return $relations;
    }

    private function resolveSortBy(string $sortBy): string
    {
        return match ($sortBy) {
            'end_time', 'created_at', 'updated_at', 'reservation_id', 'guest_count' => $sortBy,
            default => 'start_time',
        };
    }
}
