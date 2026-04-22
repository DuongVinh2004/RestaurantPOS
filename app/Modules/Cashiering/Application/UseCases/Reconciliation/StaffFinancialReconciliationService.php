<?php

declare(strict_types=1);

namespace App\Modules\Cashiering\Application\UseCases\Reconciliation;

use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use App\Support\Listing\SafeLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StaffFinancialReconciliationService
{
    public function __construct(
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return LengthAwarePaginator<int,array<string,mixed>>
     */
    public function paginate(array $filters = [], ?int $staffActorUserId = null): LengthAwarePaginator
    {
        $filters = $this->withAccessibleBranchScope($filters, $staffActorUserId);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));

        /** @var LengthAwarePaginatorImpl<int,object> $paginator */
        $paginator = $this->baseQuery($filters)
            ->paginate($perPage, ['*'], 'page', $page);

        return $paginator->through(fn (object $row): array => $this->transformListRow($row));
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(array $filters = [], ?int $staffActorUserId = null): array
    {
        $filters = $this->withAccessibleBranchScope($filters, $staffActorUserId);
        $limit = max(1, min((int) ($filters['limit'] ?? 500), 1000));

        return $this->baseQuery($filters)
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => $this->transformExportRow($this->transformListRow($row)))
            ->values()
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    public function show(int $reservationId, ?int $branchId = null, ?int $staffActorUserId = null): array
    {
        $branchScope = $this->resolveShowBranchScope($branchId, $staffActorUserId);

        /** @var Reservation $reservation */
        $reservation = Reservation::query()
            ->with([
                'user:user_id,full_name,email,phone',
                'payments.refundOfPayment',
                'payments.createdByUser:user_id,full_name,email',
            ])
            ->whereIn('branch_id', $branchScope)
            ->findOrFail($reservationId);

        $row = $this->baseQuery([
            'branch_scope' => $branchScope,
            'reservation_id' => $reservationId,
        ])->first();
        $summary = $row !== null
            ? $this->transformListRow($row)
            : $this->transformListRow((object) [
                'reservation_id' => (int) $reservation->reservation_id,
                'reservation_code' => (string) $reservation->reservation_code,
                'row_version' => (int) $reservation->row_version,
                'user_id' => $reservation->user_id,
                'customer_name' => $reservation->user?->full_name,
                'customer_email' => $reservation->user?->email,
                'customer_phone' => $reservation->user?->phone,
                'status' => (string) ($reservation->status?->value ?? $reservation->status),
                'deposit_status' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status),
                'deposit_required_amount' => (float) ($reservation->deposit_required_amount ?? 0),
                'deposit_paid_amount' => (float) ($reservation->deposit_paid_amount ?? 0),
                'final_bill_amount' => $reservation->final_bill_amount,
                'bill_currency' => $reservation->bill_currency,
                'start_time' => $reservation->start_time,
                'end_time' => $reservation->end_time,
                'billed_at' => $reservation->billed_at,
                'updated_at' => $reservation->updated_at,
                'payment_row_count' => 0,
                'refund_row_count' => 0,
                'payment_currency_count' => 0,
                'payment_currency' => $reservation->bill_currency,
                'last_payment_activity_at' => null,
                'last_refund_at' => null,
                'deposit_captured_amount' => 0.0,
                'deposit_refunded_amount' => 0.0,
                'deposit_raw_net_amount' => 0.0,
                'deposit_net_amount' => 0.0,
                'deposit_over_refunded_amount' => 0.0,
                'final_captured_amount' => 0.0,
                'final_refunded_amount' => 0.0,
                'final_raw_net_amount' => 0.0,
                'final_net_amount' => 0.0,
                'final_over_refunded_amount' => 0.0,
                'captured_amount' => 0.0,
                'refunded_amount' => 0.0,
                'raw_net_paid_amount' => 0.0,
                'net_paid_amount' => 0.0,
                'over_refunded_amount' => 0.0,
            ]);

        $payments = $reservation->payments
            ->sortBy('payment_id')
            ->values();

        return [
            'reservation' => [
                'reservation_id' => (int) $reservation->reservation_id,
                'reservation_code' => (string) $reservation->reservation_code,
                'row_version' => (int) $reservation->row_version,
                'status' => (string) ($reservation->status?->value ?? $reservation->status),
                'deposit_status' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status),
                'start_time' => $reservation->start_time?->utc()->toIso8601String(),
                'end_time' => $reservation->end_time?->utc()->toIso8601String(),
                'billed_at' => $reservation->billed_at?->utc()->toIso8601String(),
                'bill_currency' => (string) ($reservation->bill_currency ?? 'VND'),
                'customer' => [
                    'user_id' => $reservation->user_id !== null ? (int) $reservation->user_id : null,
                    'full_name' => $reservation->user?->full_name,
                    'email' => $reservation->user?->email,
                    'phone' => $reservation->user?->phone,
                ],
            ],
            'summary' => $summary,
            'payments' => $payments->map(fn (Payment $payment): array => $this->transformPaymentRow($payment))->all(),
            'method_breakdown' => $this->methodBreakdown($payments, (string) ($reservation->bill_currency ?? 'VND')),
        ];
    }

    /**
     * @return list<int>
     */
    private function resolveShowBranchScope(?int $branchId = null, ?int $staffActorUserId = null): array
    {
        if ($branchId !== null && ($staffActorUserId === null || $staffActorUserId <= 0)) {
            return [$branchId];
        }

        return $this->branchContextService->branchScopeOrAccessible($staffActorUserId, $branchId);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $aggregate = $this->paymentAggregateSubquery();

        $query = DB::table('reservations as reservations')
            ->leftJoin('users as customers', 'customers.user_id', '=', 'reservations.user_id')
            ->leftJoinSub($aggregate, 'payment_agg', function ($join): void {
                $join->on('payment_agg.reservation_id', '=', 'reservations.reservation_id');
            })
            ->select([
                'reservations.reservation_id',
                'reservations.reservation_code',
                'reservations.row_version',
                'reservations.user_id',
                'customers.full_name as customer_name',
                'customers.email as customer_email',
                'customers.phone as customer_phone',
                'reservations.status',
                'reservations.deposit_status',
                'reservations.deposit_required_amount',
                'reservations.deposit_paid_amount',
                'reservations.final_bill_amount',
                'reservations.bill_currency',
                'reservations.start_time',
                'reservations.end_time',
                'reservations.billed_at',
                'reservations.updated_at',
                DB::raw('COALESCE(payment_agg.payment_row_count, 0) as payment_row_count'),
                DB::raw('COALESCE(payment_agg.refund_row_count, 0) as refund_row_count'),
                DB::raw('COALESCE(payment_agg.payment_currency_count, 0) as payment_currency_count'),
                DB::raw('payment_agg.payment_currency as payment_currency'),
                DB::raw('payment_agg.last_payment_activity_at as last_payment_activity_at'),
                DB::raw('payment_agg.last_refund_at as last_refund_at'),
                DB::raw('COALESCE(payment_agg.deposit_captured_amount, 0) as deposit_captured_amount'),
                DB::raw('COALESCE(payment_agg.deposit_refunded_amount, 0) as deposit_refunded_amount'),
                DB::raw('COALESCE(payment_agg.deposit_raw_net_amount, 0) as deposit_raw_net_amount'),
                DB::raw('COALESCE(payment_agg.deposit_net_amount, 0) as deposit_net_amount'),
                DB::raw('COALESCE(payment_agg.deposit_over_refunded_amount, 0) as deposit_over_refunded_amount'),
                DB::raw('COALESCE(payment_agg.final_captured_amount, 0) as final_captured_amount'),
                DB::raw('COALESCE(payment_agg.final_refunded_amount, 0) as final_refunded_amount'),
                DB::raw('COALESCE(payment_agg.final_raw_net_amount, 0) as final_raw_net_amount'),
                DB::raw('COALESCE(payment_agg.final_net_amount, 0) as final_net_amount'),
                DB::raw('COALESCE(payment_agg.final_over_refunded_amount, 0) as final_over_refunded_amount'),
                DB::raw('COALESCE(payment_agg.captured_amount, 0) as captured_amount'),
                DB::raw('COALESCE(payment_agg.refunded_amount, 0) as refunded_amount'),
                DB::raw('COALESCE(payment_agg.raw_net_paid_amount, 0) as raw_net_paid_amount'),
                DB::raw('COALESCE(payment_agg.net_paid_amount, 0) as net_paid_amount'),
                DB::raw('COALESCE(payment_agg.over_refunded_amount, 0) as over_refunded_amount'),
            ]);

        $branchScope = array_values(array_unique(array_map(
            static fn ($value): int => (int) $value,
            array_filter((array) ($filters['branch_scope'] ?? []), static fn ($value): bool => $value !== null && $value !== ''),
        )));

        if ($branchScope !== []) {
            $query->whereIn('reservations.branch_id', $branchScope);
        } elseif (array_key_exists('branch_scope', $filters)) {
            $query->whereRaw('1 = 0');
        } elseif (isset($filters['branch_id']) && $filters['branch_id'] !== null) {
            $query->where('reservations.branch_id', (int) $filters['branch_id']);
        }

        $query->when(isset($filters['reservation_id']) && $filters['reservation_id'] !== null, function (Builder $builder) use ($filters): void {
            $builder->where('reservations.reservation_id', (int) $filters['reservation_id']);
        });

        $query->when(($filters['reservation_code'] ?? null) !== null, function (Builder $builder) use ($filters): void {
            $builder->where('reservations.reservation_code', 'like', SafeLike::contains(trim((string) $filters['reservation_code'])));
        });

        $query->when(isset($filters['user_id']) && $filters['user_id'] !== null, function (Builder $builder) use ($filters): void {
            $builder->where('reservations.user_id', (int) $filters['user_id']);
        });

        $query->when(($filters['status'] ?? null) !== null, function (Builder $builder) use ($filters): void {
            $builder->where('reservations.status', (string) $filters['status']);
        });

        $query->when(($filters['deposit_status'] ?? null) !== null, function (Builder $builder) use ($filters): void {
            $builder->where('reservations.deposit_status', (string) $filters['deposit_status']);
        });

        $query->when(($filters['payment_currency'] ?? null) !== null, function (Builder $builder) use ($filters): void {
            $builder->whereExists(function (Builder $sub) use ($filters): void {
                $sub->selectRaw('1')
                    ->from('payments as payment_currency_filter')
                    ->whereColumn('payment_currency_filter.reservation_id', 'reservations.reservation_id')
                    ->where('payment_currency_filter.currency', (string) $filters['payment_currency']);
            });
        });

        $query->when(
            ($filters['cashier_user_id'] ?? null) !== null
            || ($filters['activity_from'] ?? null) !== null
            || ($filters['activity_to'] ?? null) !== null,
            function (Builder $builder) use ($filters): void {
                $builder->whereExists(function (Builder $sub) use ($filters): void {
                    $sub->selectRaw('1')
                        ->from('payments as payment_activity_filter')
                        ->whereColumn('payment_activity_filter.reservation_id', 'reservations.reservation_id');

                    if (($filters['cashier_user_id'] ?? null) !== null) {
                        $sub->where('payment_activity_filter.created_by', (int) $filters['cashier_user_id']);
                    }

                    if (($filters['activity_from'] ?? null) !== null) {
                        $sub->whereRaw('COALESCE(payment_activity_filter.paid_at, payment_activity_filter.created_at) >= ?', [
                            $this->normalizeDateTime((string) $filters['activity_from']),
                        ]);
                    }

                    if (($filters['activity_to'] ?? null) !== null) {
                        $sub->whereRaw('COALESCE(payment_activity_filter.paid_at, payment_activity_filter.created_at) <= ?', [
                            $this->normalizeDateTime((string) $filters['activity_to'], true),
                        ]);
                    }
                });
            }
        );

        if (($filters['has_discrepancy'] ?? null) !== null) {
            $wantDiscrepancy = (bool) $filters['has_discrepancy'];

            if ($wantDiscrepancy) {
                $query->where(function (Builder $builder): void {
                    $builder->whereRaw('ABS(COALESCE(payment_agg.deposit_net_amount, 0) - COALESCE(reservations.deposit_paid_amount, 0)) > 0.009')
                        ->orWhereRaw('COALESCE(payment_agg.over_refunded_amount, 0) > 0.009')
                        ->orWhereRaw('COALESCE(payment_agg.payment_currency_count, 0) > 1');
                });
            } else {
                $query->whereRaw('ABS(COALESCE(payment_agg.deposit_net_amount, 0) - COALESCE(reservations.deposit_paid_amount, 0)) <= 0.009')
                    ->whereRaw('COALESCE(payment_agg.over_refunded_amount, 0) <= 0.009')
                    ->whereRaw('COALESCE(payment_agg.payment_currency_count, 0) <= 1');
            }
        }

        [$sortColumn, $sortDirection] = $this->resolveSort(
            (string) ($filters['sort_by'] ?? 'last_payment_activity_at'),
            (string) ($filters['sort_dir'] ?? 'desc')
        );

        return $query
            ->orderByRaw($sortColumn.' '.$sortDirection)
            ->orderBy('reservations.reservation_id', $sortDirection);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function withAccessibleBranchScope(array $filters, ?int $staffActorUserId = null): array
    {
        $filters['branch_scope'] = $this->branchContextService->branchScopeOrAccessible(
            $staffActorUserId,
            isset($filters['branch_id']) && $filters['branch_id'] !== null ? (int) $filters['branch_id'] : null,
        );

        return $filters;
    }

    private function paymentAggregateSubquery(): Builder
    {
        return DB::table('payments as payment_rows')
            ->leftJoin('payments as refund_sources', 'refund_sources.payment_id', '=', 'payment_rows.refund_of_payment_id')
            ->selectRaw('payment_rows.reservation_id as reservation_id')
            ->selectRaw('COUNT(*) as payment_row_count')
            ->selectRaw("SUM(CASE WHEN payment_rows.payment_type = 'Refund' THEN 1 ELSE 0 END) as refund_row_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN payment_rows.currency IS NOT NULL AND payment_rows.currency <> '' THEN payment_rows.currency END) as payment_currency_count")
            ->selectRaw("MIN(CASE WHEN payment_rows.currency IS NOT NULL AND payment_rows.currency <> '' THEN payment_rows.currency ELSE NULL END) as payment_currency")
            ->selectRaw('MAX(COALESCE(payment_rows.paid_at, payment_rows.created_at)) as last_payment_activity_at')
            ->selectRaw("MAX(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' THEN COALESCE(payment_rows.paid_at, payment_rows.created_at) ELSE NULL END) as last_refund_at")
            ->selectRaw("SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) as deposit_captured_amount")
            ->selectRaw("SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END) as deposit_refunded_amount")
            ->selectRaw("SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) as final_captured_amount")
            ->selectRaw("SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END) as final_refunded_amount")
            ->selectRaw("SUM(CASE WHEN payment_rows.payment_type IN ('Deposit', 'Final') AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) as captured_amount")
            ->selectRaw("SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' THEN payment_rows.amount ELSE 0 END) as refunded_amount")
            ->selectRaw("(SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END)) as deposit_raw_net_amount")
            ->selectRaw("CASE WHEN ((SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END))) < 0 THEN 0 ELSE ((SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END))) END as deposit_net_amount")
            ->selectRaw("CASE WHEN ((SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END))) < 0 THEN 0 ELSE ((SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END))) END as deposit_over_refunded_amount")
            ->selectRaw("(SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END)) as final_raw_net_amount")
            ->selectRaw("CASE WHEN ((SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END))) < 0 THEN 0 ELSE ((SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END))) END as final_net_amount")
            ->selectRaw("CASE WHEN ((SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END))) < 0 THEN 0 ELSE ((SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END))) END as final_over_refunded_amount")
            ->selectRaw("((SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END)) + (SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END))) as raw_net_paid_amount")
            ->selectRaw("((CASE WHEN ((SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END))) < 0 THEN 0 ELSE ((SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END))) END) + (CASE WHEN ((SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END))) < 0 THEN 0 ELSE ((SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END))) END)) as net_paid_amount")
            ->selectRaw("((CASE WHEN ((SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END))) < 0 THEN 0 ELSE ((SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Deposit' THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Deposit' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END))) END) + (CASE WHEN ((SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END))) < 0 THEN 0 ELSE ((SUM(CASE WHEN payment_rows.payment_type = 'Refund' AND payment_rows.status = 'Refunded' AND refund_sources.payment_type = 'Final' THEN payment_rows.amount ELSE 0 END) - SUM(CASE WHEN payment_rows.payment_type = 'Final' AND payment_rows.status IN ('Success', 'Partial') THEN payment_rows.amount ELSE 0 END))) END)) as over_refunded_amount")
            ->groupBy('payment_rows.reservation_id');
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveSort(string $sortBy, string $sortDirection): array
    {
        $direction = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $map = [
            'reservation_id' => 'reservations.reservation_id',
            'start_time' => 'reservations.start_time',
            'updated_at' => 'reservations.updated_at',
            'final_bill_amount' => 'reservations.final_bill_amount',
            'net_paid_amount' => 'COALESCE(payment_agg.net_paid_amount, 0)',
            'refunded_amount' => 'COALESCE(payment_agg.refunded_amount, 0)',
            'last_payment_activity_at' => 'COALESCE(payment_agg.last_payment_activity_at, reservations.updated_at)',
        ];

        return [$map[$sortBy] ?? $map['last_payment_activity_at'], $direction];
    }

    /**
     * @return array<string,mixed>
     */
    private function transformListRow(object $row): array
    {
        $depositRequired = $this->money($row->deposit_required_amount ?? 0.0);
        $depositStoredPaid = $this->money($row->deposit_paid_amount ?? 0.0);
        $finalBillAmount = $row->final_bill_amount !== null ? $this->money($row->final_bill_amount) : null;
        $depositNet = $this->money($row->deposit_net_amount ?? 0.0);
        $netPaid = $this->money($row->net_paid_amount ?? 0.0);
        $depositSyncGapMinor = Money::minorUnits($depositNet) - Money::minorUnits($depositStoredPaid);
        $billBalanceMinor = $finalBillAmount !== null ? Money::minorUnits($finalBillAmount) - Money::minorUnits($netPaid) : null;
        $billOutstanding = $billBalanceMinor !== null ? Money::minorToFloat(max(0, $billBalanceMinor)) : null;
        $billOverpaid = $billBalanceMinor !== null ? Money::minorToFloat(max(0, -1 * $billBalanceMinor)) : null;
        $overRefundedAmount = $this->money($row->over_refunded_amount ?? 0.0);
        $hasMixedCurrencies = (int) ($row->payment_currency_count ?? 0) > 1;
        $hasDepositSyncGap = $depositSyncGapMinor !== 0;
        $hasOverRefund = Money::minorUnits($overRefundedAmount, true) > 0;
        $hasBillOutstanding = $billOutstanding !== null && Money::minorUnits($billOutstanding, true) > 0;
        $hasBillOverpaid = $billOverpaid !== null && Money::minorUnits($billOverpaid, true) > 0;
        $hasDiscrepancy = $hasDepositSyncGap || $hasOverRefund || $hasMixedCurrencies;

        $discrepancyReasons = [];
        if ($hasDepositSyncGap) {
            $discrepancyReasons[] = 'deposit_sync_gap';
        }
        if ($hasOverRefund) {
            $discrepancyReasons[] = 'over_refunded';
        }
        if ($hasMixedCurrencies) {
            $discrepancyReasons[] = 'mixed_payment_currencies';
        }
        if ($hasBillOutstanding) {
            $discrepancyReasons[] = 'bill_outstanding';
        }
        if ($hasBillOverpaid) {
            $discrepancyReasons[] = 'bill_overpaid';
        }

        return [
            'reservation' => [
                'reservation_id' => (int) $row->reservation_id,
                'reservation_code' => (string) $row->reservation_code,
                'row_version' => (int) ($row->row_version ?? 0),
                'status' => (string) $row->status,
                'deposit_status' => (string) $row->deposit_status,
                'start_time' => $this->toIso8601String($row->start_time),
                'end_time' => $this->toIso8601String($row->end_time),
                'billed_at' => $this->toIso8601String($row->billed_at),
                'updated_at' => $this->toIso8601String($row->updated_at),
                'bill_currency' => (string) ($row->bill_currency ?? 'VND'),
                'customer' => [
                    'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                    'full_name' => $row->customer_name,
                    'email' => $row->customer_email,
                    'phone' => $row->customer_phone,
                ],
            ],
            'payment_summary' => [
                'payment_count' => (int) ($row->payment_row_count ?? 0),
                'refund_count' => (int) ($row->refund_row_count ?? 0),
                'captured_amount' => $this->money($row->captured_amount ?? 0.0),
                'refunded_amount' => $this->money($row->refunded_amount ?? 0.0),
                'net_paid_amount' => $netPaid,
                'deposit_captured_amount' => $this->money($row->deposit_captured_amount ?? 0.0),
                'deposit_refunded_amount' => $this->money($row->deposit_refunded_amount ?? 0.0),
                'deposit_net_amount' => $depositNet,
                'final_captured_amount' => $this->money($row->final_captured_amount ?? 0.0),
                'final_refunded_amount' => $this->money($row->final_refunded_amount ?? 0.0),
                'final_net_amount' => $this->money($row->final_net_amount ?? 0.0),
                'over_refunded_amount' => $overRefundedAmount,
                'last_payment_activity_at' => $this->toIso8601String($row->last_payment_activity_at),
                'last_refund_at' => $this->toIso8601String($row->last_refund_at),
                'currency' => [
                    'currency' => $hasMixedCurrencies ? null : ($row->payment_currency ?? $row->bill_currency ?? 'VND'),
                    'has_mixed_currencies' => $hasMixedCurrencies,
                ],
            ],
            'reconciliation' => [
                'deposit_required_amount' => $depositRequired,
                'deposit_recorded_paid_amount' => $depositStoredPaid,
                'deposit_computed_net_amount' => $depositNet,
                'deposit_sync_gap_amount' => Money::minorToFloat($depositSyncGapMinor),
                'final_bill_amount' => $finalBillAmount,
                'bill_outstanding_amount' => $billOutstanding,
                'bill_overpaid_amount' => $billOverpaid,
            ],
            'flags' => [
                'has_refunds' => (int) ($row->refund_row_count ?? 0) > 0,
                'has_payments' => (int) ($row->payment_row_count ?? 0) > 0,
                'has_discrepancy' => $hasDiscrepancy,
                'has_deposit_sync_gap' => $hasDepositSyncGap,
                'has_over_refund' => $hasOverRefund,
                'has_mixed_payment_currencies' => $hasMixedCurrencies,
                'has_bill_outstanding' => $hasBillOutstanding,
                'has_bill_overpaid' => $hasBillOverpaid,
                'discrepancy_reasons' => $discrepancyReasons,
                'is_fully_settled' => $finalBillAmount !== null ? ($billOutstanding !== null && Money::minorUnits($billOutstanding, true) <= 0) : false,
            ],
        ];
    }

    /**
     * @param  Collection<int,Payment>  $payments
     * @return array<int,array<string,mixed>>
     */
    private function methodBreakdown(Collection $payments, string $fallbackCurrency): array
    {
        $buckets = [];

        foreach ($payments as $payment) {
            $method = trim((string) ($payment->payment_method ?? 'Other'));
            if ($method === '') {
                $method = 'Other';
            }

            if (! array_key_exists($method, $buckets)) {
                $buckets[$method] = [
                    'payment_method' => $method,
                    'captured_amount_minor' => 0,
                    'refunded_amount_minor' => 0,
                    'net_amount_minor' => 0,
                    'currency' => trim((string) ($payment->currency ?? $fallbackCurrency)) ?: $fallbackCurrency,
                ];
            }

            $amountMinor = Money::minorUnits($payment->amount ?? 0, true);
            $paymentType = (string) ($payment->payment_type ?? '');
            $status = (string) ($payment->status?->value ?? $payment->status ?? '');

            if (in_array($paymentType, ['Deposit', 'Final'], true) && PaymentSummary::isCapturedStatus($status)) {
                $buckets[$method]['captured_amount_minor'] += $amountMinor;
                $buckets[$method]['net_amount_minor'] += $amountMinor;

                continue;
            }

            if ($paymentType === 'Refund' && PaymentSummary::isRefundedStatus($status)) {
                $buckets[$method]['refunded_amount_minor'] += $amountMinor;
                $buckets[$method]['net_amount_minor'] -= $amountMinor;
            }
        }

        return collect($buckets)
            ->map(static fn (array $bucket): array => [
                'payment_method' => $bucket['payment_method'],
                'captured_amount' => Money::minorToFloat((int) $bucket['captured_amount_minor']),
                'refunded_amount' => Money::minorToFloat((int) $bucket['refunded_amount_minor']),
                'net_amount' => Money::minorToFloat((int) $bucket['net_amount_minor']),
                'currency' => $bucket['currency'],
            ])
            ->sortBy('payment_method')
            ->values()
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function transformPaymentRow(Payment $payment): array
    {
        $refundSource = $payment->refundOfPayment;

        return [
            'payment_id' => (int) $payment->payment_id,
            'reservation_id' => (int) $payment->reservation_id,
            'refund_of_payment_id' => $payment->refund_of_payment_id !== null ? (int) $payment->refund_of_payment_id : null,
            'payment_type' => (string) ($payment->payment_type ?? ''),
            'status' => (string) ($payment->status?->value ?? $payment->status ?? ''),
            'amount' => $this->money($payment->amount),
            'currency' => (string) ($payment->currency ?? 'VND'),
            'payment_method' => (string) ($payment->payment_method ?? 'Other'),
            'payment_provider' => (string) ($payment->payment_provider ?? 'Other'),
            'transaction_code' => $payment->transaction_code,
            'paid_at' => $payment->paid_at?->utc()->toIso8601String(),
            'created_at' => $payment->created_at?->utc()->toIso8601String(),
            'created_by' => [
                'user_id' => $payment->created_by !== null ? (int) $payment->created_by : null,
                'full_name' => $payment->createdByUser?->full_name,
                'email' => $payment->createdByUser?->email,
            ],
            'refund_target_payment_type' => PaymentSummary::resolveRefundTargetPaymentType($payment),
            'refund_source_payment' => $refundSource !== null ? [
                'payment_id' => (int) $refundSource->payment_id,
                'payment_type' => (string) ($refundSource->payment_type ?? ''),
                'amount' => $this->money($refundSource->amount),
                'transaction_code' => $refundSource->transaction_code,
            ] : null,
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function transformExportRow(array $row): array
    {
        return [
            'reservation_id' => data_get($row, 'reservation.reservation_id'),
            'reservation_code' => data_get($row, 'reservation.reservation_code'),
            'reservation_status' => data_get($row, 'reservation.status'),
            'deposit_status' => data_get($row, 'reservation.deposit_status'),
            'customer_user_id' => data_get($row, 'reservation.customer.user_id'),
            'customer_name' => data_get($row, 'reservation.customer.full_name'),
            'customer_email' => data_get($row, 'reservation.customer.email'),
            'customer_phone' => data_get($row, 'reservation.customer.phone'),
            'start_time' => data_get($row, 'reservation.start_time'),
            'end_time' => data_get($row, 'reservation.end_time'),
            'bill_currency' => data_get($row, 'reservation.bill_currency'),
            'payment_count' => data_get($row, 'payment_summary.payment_count'),
            'refund_count' => data_get($row, 'payment_summary.refund_count'),
            'captured_amount' => $this->formatExportMoney(data_get($row, 'payment_summary.captured_amount')),
            'refunded_amount' => $this->formatExportMoney(data_get($row, 'payment_summary.refunded_amount')),
            'net_paid_amount' => $this->formatExportMoney(data_get($row, 'payment_summary.net_paid_amount')),
            'deposit_required_amount' => $this->formatExportMoney(data_get($row, 'reconciliation.deposit_required_amount')),
            'deposit_recorded_paid_amount' => $this->formatExportMoney(data_get($row, 'reconciliation.deposit_recorded_paid_amount')),
            'deposit_computed_net_amount' => $this->formatExportMoney(data_get($row, 'reconciliation.deposit_computed_net_amount')),
            'deposit_sync_gap_amount' => $this->formatExportMoney(data_get($row, 'reconciliation.deposit_sync_gap_amount')),
            'final_bill_amount' => $this->formatExportMoney(data_get($row, 'reconciliation.final_bill_amount')),
            'bill_outstanding_amount' => $this->formatExportMoney(data_get($row, 'reconciliation.bill_outstanding_amount')),
            'bill_overpaid_amount' => $this->formatExportMoney(data_get($row, 'reconciliation.bill_overpaid_amount')),
            'deposit_captured_amount' => $this->formatExportMoney(data_get($row, 'payment_summary.deposit_captured_amount')),
            'deposit_refunded_amount' => $this->formatExportMoney(data_get($row, 'payment_summary.deposit_refunded_amount')),
            'final_captured_amount' => $this->formatExportMoney(data_get($row, 'payment_summary.final_captured_amount')),
            'final_refunded_amount' => $this->formatExportMoney(data_get($row, 'payment_summary.final_refunded_amount')),
            'last_payment_activity_at' => data_get($row, 'payment_summary.last_payment_activity_at'),
            'last_refund_at' => data_get($row, 'payment_summary.last_refund_at'),
            'payment_currency' => data_get($row, 'payment_summary.currency.currency'),
            'has_mixed_payment_currencies' => data_get($row, 'payment_summary.currency.has_mixed_currencies') ? '1' : '0',
            'has_discrepancy' => data_get($row, 'flags.has_discrepancy') ? '1' : '0',
            'has_deposit_sync_gap' => data_get($row, 'flags.has_deposit_sync_gap') ? '1' : '0',
            'has_over_refund' => data_get($row, 'flags.has_over_refund') ? '1' : '0',
            'has_bill_outstanding' => data_get($row, 'flags.has_bill_outstanding') ? '1' : '0',
            'has_bill_overpaid' => data_get($row, 'flags.has_bill_overpaid') ? '1' : '0',
            'discrepancy_reasons' => implode(',', array_values((array) data_get($row, 'flags.discrepancy_reasons', []))),
            'is_fully_settled' => data_get($row, 'flags.is_fully_settled') ? '1' : '0',
        ];
    }

    private function formatExportMoney(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Money::format($value);
    }

    private function money(mixed $value): float
    {
        return Money::toFloat($value ?? 0);
    }

    private function toIso8601String(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }

    private function normalizeDateTime(string $value, bool $endOfDay = false): string
    {
        $date = Carbon::parse($value)->utc();

        if ($endOfDay && strlen(trim($value)) <= 10) {
            $date = $date->endOfDay();
        }

        return $date->format('Y-m-d H:i:s.u');
    }
}
