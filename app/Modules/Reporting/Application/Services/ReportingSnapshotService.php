<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Services;

use App\Modules\CheckoutPayments\Domain\Models\BillingInvoice;
use App\Modules\CheckoutPayments\Domain\Models\CashierShift;
use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\Reporting\Domain\Models\ReportingDailyInventoryMovementSnapshot;
use App\Modules\Reporting\Domain\Models\ReportingDailyOperationSnapshot;
use App\Modules\Reporting\Domain\Models\ReportingDailySalesSnapshot;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\WaitingList\Domain\Models\WaitingList;
use App\Models\IngredientStockMovement;
use App\Modules\CheckoutPayments\Domain\ValueObjects\PaymentSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReportingSnapshotService
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function rebuild(array $filters = [], ?int $actorUserId = null): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $runSales = (bool) ($filters['include_sales'] ?? true);
        $runOperations = (bool) ($filters['include_operations'] ?? true);
        $runInventory = (bool) ($filters['include_inventory'] ?? true);

        if (! $runSales && ! $runOperations && ! $runInventory) {
            throw new InvalidArgumentException('At least one reporting snapshot family must be included.');
        }

        return DB::transaction(function () use ($startDate, $endDate, $branchId, $runSales, $runOperations, $runInventory, $actorUserId): array {
            $result = [
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'branch_id' => $branchId,
                'rebuild' => [],
                'warnings' => [],
                'refreshed_at' => now('UTC')->toIso8601String(),
                'requested_by' => $actorUserId,
            ];

            if ($runSales) {
                $rows = $this->buildSalesRows($startDate, $endDate, $branchId);
                $this->replaceSalesRows($startDate, $endDate, $branchId, $rows);
                $result['rebuild']['sales'] = [
                    'row_count' => count($rows),
                    'business_dates' => $this->countDistinctBusinessDates($rows),
                    'is_empty' => count($rows) === 0,
                ];
                if ($rows === []) {
                    $result['warnings'][] = 'sales_snapshot_empty_for_requested_scope';
                }
            }

            if ($runOperations) {
                $rows = $this->buildOperationRows($startDate, $endDate, $branchId);
                $this->replaceOperationRows($startDate, $endDate, $branchId, $rows);
                $result['rebuild']['operations'] = [
                    'row_count' => count($rows),
                    'business_dates' => $this->countDistinctBusinessDates($rows),
                    'is_empty' => count($rows) === 0,
                ];
                if ($rows === []) {
                    $result['warnings'][] = 'operations_snapshot_empty_for_requested_scope';
                }
            }

            if ($runInventory) {
                $rows = $this->buildInventoryRows($startDate, $endDate, $branchId);
                $this->replaceInventoryRows($startDate, $endDate, $branchId, $rows);
                $result['rebuild']['inventory'] = [
                    'row_count' => count($rows),
                    'business_dates' => $this->countDistinctBusinessDates($rows),
                    'ingredient_count' => $this->countDistinct($rows, 'ingredient_id'),
                    'is_empty' => count($rows) === 0,
                ];
                if ($rows === []) {
                    $result['warnings'][] = 'inventory_snapshot_empty_for_requested_scope';
                }
            }

            if (count($result['warnings']) > 0 && count($result['rebuild']) === count($result['warnings'])) {
                $result['warnings'][] = 'requested_reporting_scope_empty';
            }

            return $result;
        }, 3);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginateDailySales(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? config('booking.reporting_page_default', 25)), (int) config('booking.reporting_page_max', 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        [$startDate, $endDate] = $this->resolveDateRange($filters, 7);
        [$sortColumn, $sortDirection] = $this->resolveSalesSort(
            (string) ($filters['sort_by'] ?? 'business_date'),
            (string) ($filters['sort_dir'] ?? 'desc'),
        );

        /** @var LengthAwarePaginatorImpl<int,ReportingDailySalesSnapshot> $paginator */
        $paginator = ReportingDailySalesSnapshot::query()
            ->with(['branch:branch_id,branch_code,branch_name,is_default'])
            ->when(isset($filters['branch_id']), static fn ($query) => $query->where('branch_id', (int) $filters['branch_id']))
            ->when(isset($filters['currency']) && trim((string) $filters['currency']) !== '', static fn ($query) => $query->where('currency', strtoupper(trim((string) $filters['currency']))))
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'business_date', static fn ($query) => $query->orderByDesc('business_date'))
            ->when($sortColumn !== 'branch_id', static fn ($query) => $query->orderBy('branch_id'))
            ->when($sortColumn !== 'currency', static fn ($query) => $query->orderBy('currency'))
            ->paginate($perPage, ['*'], 'page', $page);

        return $paginator;
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginateDailyOperations(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? config('booking.reporting_page_default', 25)), (int) config('booking.reporting_page_max', 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        [$startDate, $endDate] = $this->resolveDateRange($filters, 7);
        [$sortColumn, $sortDirection] = $this->resolveOperationsSort(
            (string) ($filters['sort_by'] ?? 'business_date'),
            (string) ($filters['sort_dir'] ?? 'desc'),
        );

        /** @var LengthAwarePaginatorImpl<int,ReportingDailyOperationSnapshot> $paginator */
        $paginator = ReportingDailyOperationSnapshot::query()
            ->with(['branch:branch_id,branch_code,branch_name,is_default'])
            ->when(isset($filters['branch_id']), static fn ($query) => $query->where('branch_id', (int) $filters['branch_id']))
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'business_date', static fn ($query) => $query->orderByDesc('business_date'))
            ->when($sortColumn !== 'branch_id', static fn ($query) => $query->orderBy('branch_id'))
            ->paginate($perPage, ['*'], 'page', $page);

        return $paginator;
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    public function paginateDailyInventory(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? config('booking.reporting_page_default', 25)), (int) config('booking.reporting_page_max', 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        [$startDate, $endDate] = $this->resolveDateRange($filters, 7);
        [$sortColumn, $sortDirection] = $this->resolveInventorySort(
            (string) ($filters['sort_by'] ?? 'business_date'),
            (string) ($filters['sort_dir'] ?? 'desc'),
        );

        /** @var LengthAwarePaginatorImpl<int,ReportingDailyInventoryMovementSnapshot> $paginator */
        $paginator = ReportingDailyInventoryMovementSnapshot::query()
            ->with([
                'branch:branch_id,branch_code,branch_name,is_default',
                'ingredient:ingredient_id,code,name,unit_code,is_active',
            ])
            ->when(isset($filters['branch_id']), static fn ($query) => $query->where('branch_id', (int) $filters['branch_id']))
            ->when(isset($filters['ingredient_id']), static fn ($query) => $query->where('ingredient_id', (int) $filters['ingredient_id']))
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'business_date', static fn ($query) => $query->orderByDesc('business_date'))
            ->when($sortColumn !== 'branch_id', static fn ($query) => $query->orderBy('branch_id'))
            ->when($sortColumn !== 'ingredient_id', static fn ($query) => $query->orderBy('ingredient_id'))
            ->when($sortColumn !== 'unit_code', static fn ($query) => $query->orderBy('unit_code'))
            ->paginate($perPage, ['*'], 'page', $page);

        return $paginator;
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    public function filteredSnapshotHealth(string $family, array $filters = [], ?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');
        [$startDate, $endDate] = $this->resolveDateRange($filters, 7);
        $staleThresholdSeconds = max(1, (int) config('booking.ops.reporting_snapshot_stale_hours', 48)) * 3600;

        [$query, $scopeColumns] = $this->filteredSnapshotHealthQuery($family, $filters, $startDate, $endDate);

        $rowCount = (int) (clone $query)->count();
        $latestBusinessDate = (clone $query)->max('business_date');
        $latestRefreshedAt = (clone $query)->max('refreshed_at');
        $latestRefreshAgeSeconds = $latestRefreshedAt !== null
            ? max(0, Carbon::parse((string) $latestRefreshedAt)->utc()->diffInSeconds($now))
            : null;
        [
            $scopeCount,
            $staleScopeCount,
            $staleScopeExamples,
            $healthReferenceRefreshedAt,
            $healthReferenceRefreshAgeSeconds,
        ] = $this->filteredSnapshotScopeFreshnessSummary(clone $query, $scopeColumns, $now);
        $isEmpty = $rowCount === 0;
        $isStale = ! $isEmpty && (
            $staleScopeCount > 0
            || ($healthReferenceRefreshAgeSeconds !== null && $healthReferenceRefreshAgeSeconds >= $staleThresholdSeconds)
        );
        $reasons = [];
        if ($isEmpty) {
            $reasons[] = 'reporting_snapshot_empty';
        }
        if ($isStale) {
            $reasons[] = 'reporting_snapshot_stale';
        }
        if ($staleScopeCount > 0 && $staleScopeCount < $scopeCount) {
            $reasons[] = 'reporting_snapshot_scope_partial';
        }

        return [
            'family' => $family,
            'row_count' => $rowCount,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'latest_business_date' => $latestBusinessDate !== null ? (string) $latestBusinessDate : null,
            'latest_refreshed_at_utc' => $latestRefreshedAt !== null ? Carbon::parse((string) $latestRefreshedAt)->utc()->toIso8601String() : null,
            'latest_refresh_age_seconds' => $latestRefreshAgeSeconds,
            'scope_count' => $scopeCount,
            'healthy_scope_count' => max(0, $scopeCount - $staleScopeCount),
            'stale_scope_count' => $staleScopeCount,
            'stale_scope_examples' => $staleScopeExamples,
            'health_reference_refreshed_at_utc' => $healthReferenceRefreshedAt !== null ? Carbon::parse((string) $healthReferenceRefreshedAt)->utc()->toIso8601String() : null,
            'health_reference_refresh_age_seconds' => $healthReferenceRefreshAgeSeconds,
            'stale_threshold_seconds' => $staleThresholdSeconds,
            'is_empty' => $isEmpty,
            'is_stale' => $isStale,
            'status' => $reasons === [] ? 'ok' : 'degraded',
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{0:mixed,1:list<string>}
     */
    private function filteredSnapshotHealthQuery(string $family, array $filters, string $startDate, string $endDate): array
    {
        return match ($family) {
            'sales' => [
                ReportingDailySalesSnapshot::query()
                    ->when(isset($filters['branch_id']), static fn ($q) => $q->where('branch_id', (int) $filters['branch_id']))
                    ->when(isset($filters['currency']) && trim((string) $filters['currency']) !== '', static fn ($q) => $q->where('currency', strtoupper(trim((string) $filters['currency']))))
                    ->whereBetween('business_date', [$startDate, $endDate]),
                ['branch_id', 'currency'],
            ],
            'operations' => [
                ReportingDailyOperationSnapshot::query()
                    ->when(isset($filters['branch_id']), static fn ($q) => $q->where('branch_id', (int) $filters['branch_id']))
                    ->whereBetween('business_date', [$startDate, $endDate]),
                ['branch_id'],
            ],
            'inventory' => [
                ReportingDailyInventoryMovementSnapshot::query()
                    ->when(isset($filters['branch_id']), static fn ($q) => $q->where('branch_id', (int) $filters['branch_id']))
                    ->when(isset($filters['ingredient_id']), static fn ($q) => $q->where('ingredient_id', (int) $filters['ingredient_id']))
                    ->whereBetween('business_date', [$startDate, $endDate]),
                ['branch_id', 'ingredient_id'],
            ],
            default => throw new InvalidArgumentException('Unknown reporting snapshot family.'),
        };
    }

    /**
     * @param  mixed  $query
     * @param  list<string>  $scopeColumns
     * @return array{0:int,1:int,2:list<array<string,mixed>>,3:?string,4:?int}
     */
    private function filteredSnapshotScopeFreshnessSummary($query, array $scopeColumns, Carbon $now): array
    {
        $staleThreshold = $now->copy()->subHours(max(1, (int) config('booking.ops.reporting_snapshot_stale_hours', 48)));

        $groupedQuery = $query
            ->select($scopeColumns)
            ->selectRaw('MAX(refreshed_at) AS latest_refreshed_at')
            ->groupBy($scopeColumns);

        $groupedSubquery = DB::query()->fromSub($groupedQuery->toBase(), 'reporting_scope_freshness');
        $scopeCount = (int) (clone $groupedSubquery)->count();

        if ($scopeCount === 0) {
            return [0, 0, [], null, null];
        }

        $staleScopesQuery = (clone $groupedSubquery)
            ->where(function ($builder) use ($staleThreshold): void {
                $builder
                    ->whereNull('latest_refreshed_at')
                    ->orWhere('latest_refreshed_at', '<=', $staleThreshold);
            });

        $staleScopeCount = (int) (clone $staleScopesQuery)->count();
        $staleScopes = (clone $staleScopesQuery)
            ->orderBy('latest_refreshed_at')
            ->limit(3)
            ->get();

        $examples = [];
        $healthReferenceRefreshedAt = null;
        $healthReferenceRefreshAgeSeconds = null;

        foreach ($staleScopes as $scope) {
            $example = [];

            foreach ($scopeColumns as $column) {
                $example[$column] = $scope->{$column} ?? null;
            }

            $latestRefreshedAt = $scope->latest_refreshed_at ?? null;
            $example['latest_refreshed_at_utc'] = $latestRefreshedAt !== null
                ? Carbon::parse((string) $latestRefreshedAt)->utc()->toIso8601String()
                : null;
            $example['latest_refresh_age_seconds'] = $this->snapshotRefreshAgeSeconds($latestRefreshedAt, $now);

            if ($example['latest_refresh_age_seconds'] !== null) {
                $healthReferenceRefreshAgeSeconds = max(
                    $healthReferenceRefreshAgeSeconds ?? 0,
                    (int) $example['latest_refresh_age_seconds'],
                );
            }

            if ($healthReferenceRefreshedAt === null || ($latestRefreshedAt !== null && (string) $latestRefreshedAt < $healthReferenceRefreshedAt)) {
                $healthReferenceRefreshedAt = $latestRefreshedAt !== null ? (string) $latestRefreshedAt : $healthReferenceRefreshedAt;
            }

            $examples[] = $example;
        }

        if ($staleScopeCount > count($examples)) {
            $oldestStaleRefresh = (clone $staleScopesQuery)->min('latest_refreshed_at');
            $oldestStaleAge = $this->snapshotRefreshAgeSeconds($oldestStaleRefresh, $now);

            if ($oldestStaleAge !== null) {
                $healthReferenceRefreshAgeSeconds = max($healthReferenceRefreshAgeSeconds ?? 0, $oldestStaleAge);
            }
            if ($oldestStaleRefresh !== null && $healthReferenceRefreshedAt === null) {
                $healthReferenceRefreshedAt = (string) $oldestStaleRefresh;
            }
        }

        if ($healthReferenceRefreshedAt === null) {
            $healthReferenceRefreshedAt = (clone $groupedSubquery)->min('latest_refreshed_at');
        }
        if ($healthReferenceRefreshAgeSeconds === null) {
            $healthReferenceRefreshAgeSeconds = $this->snapshotRefreshAgeSeconds($healthReferenceRefreshedAt, $now);
        }

        return [
            $scopeCount,
            $staleScopeCount,
            $examples,
            $healthReferenceRefreshedAt !== null ? (string) $healthReferenceRefreshedAt : null,
            $healthReferenceRefreshAgeSeconds,
        ];
    }

    private function snapshotRefreshAgeSeconds(mixed $value, Carbon $now): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (int) max(0, Carbon::parse((string) $value)->utc()->diffInSeconds($now));
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveDateRange(array $filters, int $defaultDays = 30): array
    {
        $endDate = isset($filters['end_date']) && $filters['end_date'] !== null
            ? Carbon::parse((string) $filters['end_date'], 'UTC')->toDateString()
            : Carbon::now('UTC')->toDateString();

        $startDate = isset($filters['start_date']) && $filters['start_date'] !== null
            ? Carbon::parse((string) $filters['start_date'], 'UTC')->toDateString()
            : Carbon::parse($endDate, 'UTC')->subDays(max(0, $defaultDays - 1))->toDateString();

        if ($startDate > $endDate) {
            throw new InvalidArgumentException('start_date must be before or equal to end_date.');
        }

        return [$startDate, $endDate];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveSalesSort(string $sortBy, string $sortDir): array
    {
        $direction = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        return match ($sortBy) {
            'branch_id' => ['branch_id', $direction],
            'currency' => ['currency', $direction],
            'gross_bill_amount' => ['gross_bill_amount', $direction],
            'net_paid_amount' => ['net_paid_amount', $direction],
            'billed_reservation_count' => ['billed_reservation_count', $direction],
            default => ['business_date', $direction],
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveOperationsSort(string $sortBy, string $sortDir): array
    {
        $direction = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        return match ($sortBy) {
            'branch_id' => ['branch_id', $direction],
            'scheduled_reservation_count' => ['scheduled_reservation_count', $direction],
            'completed_count' => ['completed_count', $direction],
            'waiting_list_created_count' => ['waiting_list_created_count', $direction],
            'waiting_list_seated_count' => ['waiting_list_seated_count', $direction],
            default => ['business_date', $direction],
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveInventorySort(string $sortBy, string $sortDir): array
    {
        $direction = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        return match ($sortBy) {
            'branch_id' => ['branch_id', $direction],
            'ingredient_id' => ['ingredient_id', $direction],
            'movement_count' => ['movement_count', $direction],
            'net_quantity_delta' => ['net_quantity_delta', $direction],
            'last_movement_at' => ['last_movement_at', $direction],
            default => ['business_date', $direction],
        };
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildSalesRows(string $startDate, string $endDate, ?int $branchId): array
    {
        $rows = [];
        $refreshedAt = now('UTC');
        $startAt = Carbon::parse($startDate, 'UTC')->startOfDay();
        $endAt = Carbon::parse($endDate, 'UTC')->endOfDay();

        Reservation::query()
            ->select(['reservation_id', 'branch_id', 'billed_at', 'bill_currency', 'guest_count', 'discount_amount', 'final_bill_amount'])
            ->whereNotNull('billed_at')
            ->whereBetween('billed_at', [$startAt, $endAt])
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('reservation_id')
            ->get()
            ->each(function (Reservation $reservation) use (&$rows, $refreshedAt): void {
                $key = $this->salesKey(
                    (int) $reservation->branch_id,
                    $reservation->billed_at?->utc()->toDateString() ?? now('UTC')->toDateString(),
                    (string) ($reservation->bill_currency ?? 'VND')
                );

                $row = $rows[$key] ?? $this->emptySalesRow((int) $reservation->branch_id, (string) $this->dateValue($reservation->billed_at), (string) ($reservation->bill_currency ?? 'VND'), $refreshedAt);
                $discount = round((float) ($reservation->discount_amount ?? 0), 2);
                $total = round((float) ($reservation->final_bill_amount ?? 0), 2);
                $row['billed_reservation_count']++;
                $row['billed_guest_count'] += (int) ($reservation->guest_count ?? 0);
                $row['discount_amount'] += $discount;
                $row['billed_total_amount'] += $total;
                $row['gross_bill_amount'] += round($discount + $total, 2);
                $rows[$key] = $row;
            });

        BillingInvoice::query()
            ->select([
                'billing_invoices.billing_invoice_id',
                'billing_invoices.issued_at',
                'billing_invoices.total_amount',
                'billing_invoices.tax_amount',
                'billing_invoices.currency',
                'reservations.branch_id',
            ])
            ->join('reservations', 'reservations.reservation_id', '=', 'billing_invoices.reservation_id')
            ->where('billing_invoices.invoice_status', 'Issued')
            ->whereBetween('billing_invoices.issued_at', [$startAt, $endAt])
            ->when($branchId !== null, static fn ($query) => $query->where('reservations.branch_id', $branchId))
            ->orderBy('billing_invoices.billing_invoice_id')
            ->get()
            ->each(function (object $invoice) use (&$rows, $refreshedAt): void {
                $date = Carbon::parse((string) $invoice->issued_at, 'UTC')->toDateString();
                $currency = strtoupper(trim((string) ($invoice->currency ?? 'VND')));
                $key = $this->salesKey((int) $invoice->branch_id, $date, $currency);
                $row = $rows[$key] ?? $this->emptySalesRow((int) $invoice->branch_id, $date, $currency, $refreshedAt);
                $row['invoice_issued_count']++;
                $row['invoiced_total_amount'] += round((float) ($invoice->total_amount ?? 0), 2);
                $row['invoiced_tax_amount'] += round((float) ($invoice->tax_amount ?? 0), 2);
                $rows[$key] = $row;
            });

        $paymentBuckets = [];
        Payment::query()
            ->with('refundOfPayment')
            ->where(function ($query) use ($startAt, $endAt): void {
                $query->whereBetween('paid_at', [$startAt, $endAt])
                    ->orWhere(function ($inner) use ($startAt, $endAt): void {
                        $inner->whereNull('paid_at')->whereBetween('created_at', [$startAt, $endAt]);
                    });
            })
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('payment_id')
            ->get()
            ->each(function (Payment $payment) use (&$paymentBuckets): void {
                $activityAt = $payment->paid_at?->utc() ?? $payment->created_at?->utc();
                if (! $activityAt instanceof Carbon) {
                    return;
                }

                $currency = strtoupper(trim((string) ($payment->currency ?? 'VND')));
                $key = $this->salesKey((int) ($payment->branch_id ?? 1), $activityAt->toDateString(), $currency);
                $bucket = $paymentBuckets[$key] ?? [
                    'branch_id' => (int) ($payment->branch_id ?? 1),
                    'business_date' => $activityAt->toDateString(),
                    'currency' => $currency,
                    'payments' => [],
                ];
                $bucket['payments'][] = $payment;
                $paymentBuckets[$key] = $bucket;
            });

        foreach ($paymentBuckets as $key => $bucket) {
            /** @var array<int,Payment> $payments */
            $payments = $bucket['payments'];
            $row = $rows[$key] ?? $this->emptySalesRow((int) $bucket['branch_id'], (string) $bucket['business_date'], (string) $bucket['currency'], $refreshedAt);
            $summary = PaymentSummary::fromPayments($payments);
            $row['payment_row_count'] += count($payments);
            $row['refund_row_count'] += count(array_filter($payments, static fn (Payment $payment): bool => (string) $payment->payment_type === 'Refund'));
            $row['captured_amount'] += round((float) ($summary['captured_amount'] ?? 0), 2);
            $row['refunded_amount'] += round((float) ($summary['refunded_amount'] ?? 0), 2);
            $row['net_paid_amount'] += round((float) ($summary['net_paid_amount'] ?? 0), 2);
            $row['deposit_net_amount'] += round((float) ($summary['deposit_net_amount'] ?? 0), 2);
            $row['final_net_amount'] += round((float) ($summary['final_net_amount'] ?? 0), 2);
            $rows[$key] = $row;
        }

        CashierShift::query()
            ->select(['cashier_shift_id', 'branch_id', 'currency', 'closed_at', 'cash_discrepancy_amount'])
            ->where('status', 'Closed')
            ->whereBetween('closed_at', [$startAt, $endAt])
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('cashier_shift_id')
            ->get()
            ->each(function (CashierShift $shift) use (&$rows, $refreshedAt): void {
                $date = $shift->closed_at?->utc()->toDateString() ?? now('UTC')->toDateString();
                $currency = strtoupper(trim((string) ($shift->currency ?? 'VND')));
                $key = $this->salesKey((int) $shift->branch_id, $date, $currency);
                $row = $rows[$key] ?? $this->emptySalesRow((int) $shift->branch_id, $date, $currency, $refreshedAt);
                $row['cashier_shift_closed_count']++;
                $row['cash_discrepancy_amount'] += round((float) ($shift->cash_discrepancy_amount ?? 0), 2);
                $rows[$key] = $row;
            });

        return $this->normalizeRows(array_values($rows), 2);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildOperationRows(string $startDate, string $endDate, ?int $branchId): array
    {
        $rows = [];
        $refreshedAt = now('UTC');
        $startAt = Carbon::parse($startDate, 'UTC')->startOfDay();
        $endAt = Carbon::parse($endDate, 'UTC')->endOfDay();

        Reservation::query()
            ->select([
                'reservation_id',
                'branch_id',
                'start_time',
                'end_time',
                'checked_in_at',
                'checked_out_at',
                'cancelled_at',
                'no_show_at',
                'guest_count',
                'status',
            ])
            ->where(function ($query) use ($startAt, $endAt): void {
                $query->whereBetween('start_time', [$startAt, $endAt])
                    ->orWhereBetween('checked_in_at', [$startAt, $endAt])
                    ->orWhereBetween('checked_out_at', [$startAt, $endAt])
                    ->orWhereBetween('cancelled_at', [$startAt, $endAt])
                    ->orWhereBetween('no_show_at', [$startAt, $endAt]);
            })
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('reservation_id')
            ->get()
            ->each(function (Reservation $reservation) use (&$rows, $refreshedAt, $startDate, $endDate): void {
                $branch = (int) ($reservation->branch_id ?? 1);
                $guestCount = (int) ($reservation->guest_count ?? 0);

                $startDateValue = $this->dateValue($reservation->start_time);
                if ($startDateValue >= $startDate && $startDateValue <= $endDate) {
                    $row = $this->touchOperationRow($rows, $branch, $startDateValue, $refreshedAt);
                    $row['scheduled_reservation_count']++;
                    $row['scheduled_guest_count'] += $guestCount;
                    if ($reservation->start_time !== null && $reservation->end_time !== null) {
                        $minutes = max(0, $reservation->start_time->diffInMinutes($reservation->end_time, false));
                        $row['scheduled_minutes_total'] += $minutes;
                    }
                    $rows[$this->operationKey($branch, $startDateValue)] = $row;
                }

                $checkedInDate = $reservation->checked_in_at?->utc()->toDateString();
                if ($checkedInDate !== null && $checkedInDate >= $startDate && $checkedInDate <= $endDate) {
                    $row = $this->touchOperationRow($rows, $branch, $checkedInDate, $refreshedAt);
                    $row['checked_in_count']++;
                    $rows[$this->operationKey($branch, $checkedInDate)] = $row;
                }

                $effectiveCompletedAt = $reservation->checked_out_at?->utc();
                if (! $effectiveCompletedAt instanceof Carbon && (string) ($reservation->status?->value ?? $reservation->status) === 'Completed') {
                    $effectiveCompletedAt = $reservation->end_time?->utc();
                }

                if ($effectiveCompletedAt instanceof Carbon) {
                    $completedDate = $effectiveCompletedAt->toDateString();
                    if ($completedDate >= $startDate && $completedDate <= $endDate) {
                        $row = $this->touchOperationRow($rows, $branch, $completedDate, $refreshedAt);
                        $row['completed_count']++;
                        if ($reservation->checked_in_at !== null) {
                            $turnMinutes = max(0, $reservation->checked_in_at->diffInMinutes($effectiveCompletedAt, false));
                            $row['turn_count']++;
                            $row['turn_minutes_total'] += $turnMinutes;
                        }
                        $rows[$this->operationKey($branch, $completedDate)] = $row;
                    }
                }

                $cancelledDate = $reservation->cancelled_at?->utc()->toDateString();
                if ($cancelledDate !== null && $cancelledDate >= $startDate && $cancelledDate <= $endDate) {
                    $row = $this->touchOperationRow($rows, $branch, $cancelledDate, $refreshedAt);
                    $row['cancelled_count']++;
                    $rows[$this->operationKey($branch, $cancelledDate)] = $row;
                }

                $noShowDate = $reservation->no_show_at?->utc()->toDateString();
                if ($noShowDate !== null && $noShowDate >= $startDate && $noShowDate <= $endDate) {
                    $row = $this->touchOperationRow($rows, $branch, $noShowDate, $refreshedAt);
                    $row['no_show_count']++;
                    $rows[$this->operationKey($branch, $noShowDate)] = $row;
                }
            });

        WaitingList::query()
            ->select([
                'waiting_id',
                'branch_id',
                'requested_at',
                'notified_at',
                'seated_at',
                'cancelled_at',
                'customer_confirmed_arrival_at',
            ])
            ->where(function ($query) use ($startAt, $endAt): void {
                $query->whereBetween('requested_at', [$startAt, $endAt])
                    ->orWhereBetween('notified_at', [$startAt, $endAt])
                    ->orWhereBetween('seated_at', [$startAt, $endAt])
                    ->orWhereBetween('cancelled_at', [$startAt, $endAt])
                    ->orWhereBetween('customer_confirmed_arrival_at', [$startAt, $endAt]);
            })
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('waiting_id')
            ->get()
            ->each(function (WaitingList $entry) use (&$rows, $refreshedAt, $startDate, $endDate): void {
                $branch = (int) ($entry->branch_id ?? 1);
                foreach ([
                    'requested_at' => 'waiting_list_created_count',
                    'notified_at' => 'waiting_list_notified_count',
                    'seated_at' => 'waiting_list_seated_count',
                    'cancelled_at' => 'waiting_list_cancelled_count',
                    'customer_confirmed_arrival_at' => 'waiting_list_confirmed_arrival_count',
                ] as $field => $metric) {
                    $date = $entry->{$field}?->utc()->toDateString();
                    if ($date === null || $date < $startDate || $date > $endDate) {
                        continue;
                    }

                    $row = $this->touchOperationRow($rows, $branch, $date, $refreshedAt);
                    $row[$metric]++;
                    $rows[$this->operationKey($branch, $date)] = $row;
                }
            });

        return array_values($rows);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildInventoryRows(string $startDate, string $endDate, ?int $branchId): array
    {
        $rows = [];
        $refreshedAt = now('UTC');
        $startAt = Carbon::parse($startDate, 'UTC')->startOfDay();
        $endAt = Carbon::parse($endDate, 'UTC')->endOfDay();

        IngredientStockMovement::query()
            ->select([
                'movement_id',
                'branch_id',
                'ingredient_id',
                'movement_type',
                'quantity_delta',
                'unit_code',
                'reference_type',
                'created_at',
            ])
            ->whereBetween('created_at', [$startAt, $endAt])
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('movement_id')
            ->get()
            ->each(function (IngredientStockMovement $movement) use (&$rows, $refreshedAt): void {
                $date = $movement->created_at?->utc()->toDateString() ?? now('UTC')->toDateString();
                $key = $this->inventoryKey((int) ($movement->branch_id ?? 1), $date, (int) $movement->ingredient_id, (string) $movement->unit_code);
                $row = $rows[$key] ?? $this->emptyInventoryRow((int) ($movement->branch_id ?? 1), $date, (int) $movement->ingredient_id, (string) $movement->unit_code, $refreshedAt);
                $row['movement_count']++;
                if ((string) $movement->reference_type === 'PurchaseReceipt') {
                    $row['purchase_receipt_movement_count']++;
                }

                $absoluteQuantity = round(abs((float) ($movement->quantity_delta ?? 0)), 3);
                $signedQuantity = round((float) ($movement->quantity_delta ?? 0), 3);

                switch ((string) $movement->movement_type) {
                    case IngredientStockMovement::TYPE_STOCK_IN:
                        $row['stock_in_quantity'] += $absoluteQuantity;
                        break;
                    case IngredientStockMovement::TYPE_STOCK_OUT:
                        $row['stock_out_quantity'] += $absoluteQuantity;
                        break;
                    case IngredientStockMovement::TYPE_ADJUSTMENT_INCREASE:
                        $row['adjustment_increase_quantity'] += $absoluteQuantity;
                        break;
                    case IngredientStockMovement::TYPE_ADJUSTMENT_DECREASE:
                        $row['adjustment_decrease_quantity'] += $absoluteQuantity;
                        break;
                    case IngredientStockMovement::TYPE_WASTAGE:
                        $row['wastage_quantity'] += $absoluteQuantity;
                        break;
                }

                $row['net_quantity_delta'] += $signedQuantity;
                $currentLastMovement = $row['last_movement_at'];
                $row['last_movement_at'] = $currentLastMovement === null || ((string) $movement->created_at > (string) $currentLastMovement)
                    ? $movement->created_at?->copy()->utc()->toDateTimeString()
                    : $currentLastMovement;
                $rows[$key] = $row;
            });

        return $this->normalizeRows(array_values($rows), 3);
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function replaceSalesRows(string $startDate, string $endDate, ?int $branchId, array $rows): void
    {
        ReportingDailySalesSnapshot::query()
            ->whereBetween('business_date', [$startDate, $endDate])
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->delete();

        if ($rows !== []) {
            ReportingDailySalesSnapshot::query()->insert($rows);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function replaceOperationRows(string $startDate, string $endDate, ?int $branchId, array $rows): void
    {
        ReportingDailyOperationSnapshot::query()
            ->whereBetween('business_date', [$startDate, $endDate])
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->delete();

        if ($rows !== []) {
            ReportingDailyOperationSnapshot::query()->insert($rows);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function replaceInventoryRows(string $startDate, string $endDate, ?int $branchId, array $rows): void
    {
        ReportingDailyInventoryMovementSnapshot::query()
            ->whereBetween('business_date', [$startDate, $endDate])
            ->when($branchId !== null, static fn ($query) => $query->where('branch_id', $branchId))
            ->delete();

        if ($rows !== []) {
            ReportingDailyInventoryMovementSnapshot::query()->insert($rows);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function emptySalesRow(int $branchId, string $date, string $currency, Carbon $refreshedAt): array
    {
        return [
            'branch_id' => $branchId,
            'business_date' => $date,
            'currency' => strtoupper(trim($currency)) !== '' ? strtoupper(trim($currency)) : 'VND',
            'billed_reservation_count' => 0,
            'billed_guest_count' => 0,
            'gross_bill_amount' => 0.0,
            'discount_amount' => 0.0,
            'billed_total_amount' => 0.0,
            'invoice_issued_count' => 0,
            'invoiced_total_amount' => 0.0,
            'invoiced_tax_amount' => 0.0,
            'payment_row_count' => 0,
            'refund_row_count' => 0,
            'captured_amount' => 0.0,
            'refunded_amount' => 0.0,
            'net_paid_amount' => 0.0,
            'deposit_net_amount' => 0.0,
            'final_net_amount' => 0.0,
            'cashier_shift_closed_count' => 0,
            'cash_discrepancy_amount' => 0.0,
            'refreshed_at' => $refreshedAt,
            'created_at' => $refreshedAt,
            'updated_at' => $refreshedAt,
        ];
    }

    /**
     * @param  array<string,array<string,mixed>>  $rows
     * @return array<string,mixed>
     */
    private function touchOperationRow(array $rows, int $branchId, string $date, Carbon $refreshedAt): array
    {
        return $rows[$this->operationKey($branchId, $date)] ?? [
            'branch_id' => $branchId,
            'business_date' => $date,
            'scheduled_reservation_count' => 0,
            'scheduled_guest_count' => 0,
            'scheduled_minutes_total' => 0,
            'checked_in_count' => 0,
            'completed_count' => 0,
            'cancelled_count' => 0,
            'no_show_count' => 0,
            'turn_count' => 0,
            'turn_minutes_total' => 0,
            'waiting_list_created_count' => 0,
            'waiting_list_notified_count' => 0,
            'waiting_list_seated_count' => 0,
            'waiting_list_cancelled_count' => 0,
            'waiting_list_confirmed_arrival_count' => 0,
            'refreshed_at' => $refreshedAt,
            'created_at' => $refreshedAt,
            'updated_at' => $refreshedAt,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyInventoryRow(int $branchId, string $date, int $ingredientId, string $unitCode, Carbon $refreshedAt): array
    {
        return [
            'branch_id' => $branchId,
            'business_date' => $date,
            'ingredient_id' => $ingredientId,
            'unit_code' => trim($unitCode) !== '' ? trim($unitCode) : 'unit',
            'movement_count' => 0,
            'purchase_receipt_movement_count' => 0,
            'stock_in_quantity' => 0.0,
            'stock_out_quantity' => 0.0,
            'adjustment_increase_quantity' => 0.0,
            'adjustment_decrease_quantity' => 0.0,
            'wastage_quantity' => 0.0,
            'net_quantity_delta' => 0.0,
            'last_movement_at' => null,
            'refreshed_at' => $refreshedAt,
            'created_at' => $refreshedAt,
            'updated_at' => $refreshedAt,
        ];
    }

    private function salesKey(int $branchId, string $date, string $currency): string
    {
        return implode('|', [$branchId, $date, strtoupper(trim($currency) !== '' ? trim($currency) : 'VND')]);
    }

    private function operationKey(int $branchId, string $date): string
    {
        return implode('|', [$branchId, $date]);
    }

    private function inventoryKey(int $branchId, string $date, int $ingredientId, string $unitCode): string
    {
        return implode('|', [$branchId, $date, $ingredientId, trim($unitCode) !== '' ? trim($unitCode) : 'unit']);
    }

    private function dateValue(?Carbon $value): string
    {
        return $value?->utc()->toDateString() ?? now('UTC')->toDateString();
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return list<array<string,mixed>>
     */
    private function normalizeRows(array $rows, int $precision): array
    {
        foreach ($rows as &$row) {
            foreach ($row as $key => $value) {
                if (! is_float($value) && ! is_int($value)) {
                    continue;
                }

                if (str_ends_with((string) $key, '_amount') || str_contains((string) $key, '_quantity') || $key === 'net_quantity_delta') {
                    $row[$key] = round((float) $value, $precision);
                }
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function countDistinctBusinessDates(array $rows): int
    {
        return $this->countDistinct($rows, 'business_date');
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     */
    private function countDistinct(array $rows, string $field): int
    {
        return collect($rows)
            ->pluck($field)
            ->filter(static fn ($value): bool => $value !== null && $value !== '')
            ->unique()
            ->count();
    }
}
