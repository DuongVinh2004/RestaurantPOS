<?php

declare(strict_types=1);

namespace App\Modules\Cashiering\Application\UseCases\Shifts;

use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Cashiering\Domain\Models\CashierShift;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use App\Support\Listing\SafeLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffCashierShiftService
{
    private const STALE_ROW_VERSION_MESSAGE = 'The row_version is stale (row_version mismatch). Reload the resource and try again.';

    public function __construct(
        private readonly StaffBranchContextService $staffBranchContextService,
    ) {}

    public function openShift(
        int $cashierUserId,
        float $openingFloatAmount = 0.0,
        string $currency = 'VND',
        ?string $terminalCode = null,
        string $openingNote = '',
        ?int $openedBy = null,
        mixed $branchId = null,
    ): CashierShift {
        $currency = $this->normalizeCurrency($currency);
        $terminalCode = $this->normalizeNullableString($terminalCode);
        $openingNote = $this->normalizeNullableString($openingNote) ?? '';
        $openingFloatAmount = Money::toFloat($openingFloatAmount, true);
        $openedBy ??= $cashierUserId;

        return DB::transaction(function () use (
            $cashierUserId,
            $openingFloatAmount,
            $currency,
            $terminalCode,
            $openingNote,
            $openedBy,
            $branchId
        ): CashierShift {
            User::query()->where('user_id', $cashierUserId)->lockForUpdate()->firstOrFail();

            $existing = CashierShift::query()
                ->where('cashier_user_id', $cashierUserId)
                ->where('status', 'Open')
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CashierShift) {
                throw ValidationException::withMessages([
                    'cashier_shift' => ['Cashier already has an open shift. Close the current shift before opening a new one.'],
                ]);
            }

            $openedAt = Carbon::now('UTC');
            $shift = new CashierShift;
            $shift->branch_id = $this->staffBranchContextService->assertCashierShiftBranchEligible($cashierUserId, $branchId);
            $shift->shift_code = $this->generateShiftCode($cashierUserId, $openedAt);
            $shift->cashier_user_id = $cashierUserId;
            $shift->status = 'Open';
            $shift->currency = $currency;
            $shift->terminal_code = $terminalCode;
            $shift->opening_float_amount = $openingFloatAmount;
            $shift->expected_cash_amount = null;
            $shift->actual_cash_amount = null;
            $shift->cash_discrepancy_amount = null;
            $shift->opened_at = $openedAt;
            $shift->closed_at = null;
            $shift->opened_by = $openedBy;
            $shift->closed_by = null;
            $shift->opening_note = $openingNote !== '' ? $openingNote : null;
            $shift->closing_note = null;
            $shift->save();

            AuditEvent::info('staff.cashier_shift.opened', [
                'cashier_shift_id' => (int) $shift->cashier_shift_id,
                'branch_id' => (int) $shift->branch_id,
                'branch' => $shift->branch ? [
                    'branch_id' => (int) $shift->branch->branch_id,
                    'branch_code' => (string) $shift->branch->branch_code,
                    'branch_name' => (string) $shift->branch->branch_name,
                    'is_default' => (bool) $shift->branch->is_default,
                ] : null,
                'cashier_user_id' => $cashierUserId,
                'opened_by' => $openedBy,
                'currency' => $currency,
                'opening_float_amount' => $openingFloatAmount,
                'terminal_code' => $terminalCode,
            ]);

            return $shift->refresh();
        });
    }

    public function closeShift(
        int $shiftId,
        float $actualCashAmount,
        ?int $expectedRowVersion = null,
        string $closingNote = '',
        ?int $closedBy = null,
        ?int $cashierUserId = null,
    ): CashierShift {
        $actualCashAmount = Money::toFloat($actualCashAmount, true);
        $closingNote = $this->normalizeNullableString($closingNote) ?? '';

        return DB::transaction(function () use ($shiftId, $actualCashAmount, $expectedRowVersion, $closingNote, $closedBy, $cashierUserId): CashierShift {
            /** @var CashierShift $shift */
            $query = CashierShift::query()
                ->whereKey($shiftId)
                ->when($cashierUserId !== null && $cashierUserId > 0, static fn (Builder $builder) => $builder->where('cashier_user_id', $cashierUserId));

            if ($cashierUserId !== null && $cashierUserId > 0) {
                $this->applyBranchScope($query, $this->staffBranchContextService->accessibleBranchIds($cashierUserId));
            }

            /** @var CashierShift $shift */
            $shift = $query->lockForUpdate()
                ->firstOrFail();
            $this->assertOpenShift($shift);
            $this->assertExpectedRowVersion($shift, $expectedRowVersion);

            $closedAt = Carbon::now('UTC');
            $snapshot = $this->buildSnapshot($shift, $closedAt);
            $expectedCashAmount = Money::toFloat(data_get($snapshot, 'cash.raw.expected_cash_amount', 0), true);
            $discrepancyMinor = Money::minorUnits($actualCashAmount, true) - Money::minorUnits($expectedCashAmount, true);
            $discrepancy = Money::minorToFloat($discrepancyMinor);

            $shift->status = 'Closed';
            $shift->closed_at = $closedAt;
            $shift->closed_by = $closedBy ?? $shift->cashier_user_id;
            $shift->expected_cash_amount = $expectedCashAmount;
            $shift->actual_cash_amount = $actualCashAmount;
            $shift->cash_discrepancy_amount = $discrepancy;
            $shift->closing_note = $closingNote !== '' ? $closingNote : null;
            $shift->save();

            AuditEvent::info('staff.cashier_shift.closed', [
                'cashier_shift_id' => (int) $shift->cashier_shift_id,
                'branch_id' => (int) $shift->branch_id,
                'branch' => $shift->branch ? [
                    'branch_id' => (int) $shift->branch->branch_id,
                    'branch_code' => (string) $shift->branch->branch_code,
                    'branch_name' => (string) $shift->branch->branch_name,
                    'is_default' => (bool) $shift->branch->is_default,
                ] : null,
                'cashier_user_id' => (int) $shift->cashier_user_id,
                'closed_by' => (int) ($shift->closed_by ?? 0),
                'expected_cash_amount' => $expectedCashAmount,
                'actual_cash_amount' => $actualCashAmount,
                'cash_discrepancy_amount' => $discrepancy,
                'payment_count' => (int) data_get($snapshot, 'payment.summary.payment_count', 0),
                'refund_count' => (int) data_get($snapshot, 'payment.summary.refund_count', 0),
            ]);

            return $shift->refresh();
        });
    }

    public function currentOpenShift(int $cashierUserId, ?int $branchId = null): ?CashierShift
    {
        $branchScope = $this->staffBranchContextService->branchScopeOrAccessible($cashierUserId, $branchId);
        if ($branchScope === []) {
            return null;
        }

        return CashierShift::query()
            ->where('cashier_user_id', $cashierUserId)
            ->whereIn('branch_id', $branchScope)
            ->where('status', 'Open')
            ->latest('cashier_shift_id')
            ->first();
    }

    public function requireOpenShiftForMutation(int $cashierUserId, int $branchId, ?string $currency = null): CashierShift
    {
        $shift = CashierShift::query()
            ->where('cashier_user_id', $cashierUserId)
            ->where('branch_id', $branchId)
            ->where('status', 'Open')
            ->lockForUpdate()
            ->latest('cashier_shift_id')
            ->first();

        if (! $shift instanceof CashierShift) {
            throw ValidationException::withMessages([
                'cashier_shift' => ['Open a cashier shift for this branch before recording settlement or refund mutations.'],
            ]);
        }

        $requestedCurrency = $this->normalizeNullableString($currency);
        if (
            $requestedCurrency !== null
            && $this->normalizeCurrency((string) ($shift->currency ?? 'VND')) !== $this->normalizeCurrency($requestedCurrency)
        ) {
            throw ValidationException::withMessages([
                'cashier_shift' => ['Open cashier shift currency must match the mutation currency for this branch.'],
            ]);
        }

        return $shift;
    }

    public function paginateShiftHistory(int $cashierUserId, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 12), 100));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $sortBy = $this->resolveShiftSortBy((string) ($filters['sort_by'] ?? 'opened_at'));
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc'));
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';
        $branchScope = $this->staffBranchContextService->branchScopeOrAccessible(
            $cashierUserId,
            ! empty($filters['branch_id']) ? (int) $filters['branch_id'] : null,
        );

        $query = CashierShift::query()
            ->with([
                'branch:branch_id,branch_code,branch_name,is_default',
                'cashierUser:user_id,full_name,email',
                'openedByUser:user_id,full_name,email',
                'closedByUser:user_id,full_name,email',
            ])
            ->where('cashier_user_id', $cashierUserId);
        $this->applyBranchScope($query, $branchScope);

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['shift_code'])) {
            $query->where('shift_code', 'like', SafeLike::contains(trim((string) $filters['shift_code'])));
        }

        if (! empty($filters['terminal_code'])) {
            $query->where('terminal_code', 'like', SafeLike::contains(trim((string) $filters['terminal_code'])));
        }

        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);

            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery
                    ->where('shift_code', 'like', SafeLike::contains($term))
                    ->orWhere('terminal_code', 'like', SafeLike::contains($term))
                    ->orWhereHas('branch', static function (Builder $branchQuery) use ($term): void {
                        $branchQuery
                            ->where('branch_code', 'like', SafeLike::contains($term))
                            ->orWhere('branch_name', 'like', SafeLike::contains($term));
                    });
            });
        }

        $query->orderBy($sortBy, $sortDir);

        if ($sortBy !== 'cashier_shift_id') {
            $query->orderBy('cashier_shift_id', 'desc');
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findShiftOrFail(int $shiftId, ?int $cashierUserId = null): CashierShift
    {
        $query = CashierShift::query()
            ->when($cashierUserId !== null && $cashierUserId > 0, static fn (Builder $builder) => $builder->where('cashier_user_id', $cashierUserId));

        if ($cashierUserId !== null && $cashierUserId > 0) {
            $this->applyBranchScope($query, $this->staffBranchContextService->accessibleBranchIds($cashierUserId));
        }

        return $query->findOrFail($shiftId);
    }

    /**
     * @return array<string,mixed>
     */
    public function toPayload(CashierShift $shift): array
    {
        $shift->loadMissing([
            'branch:branch_id,branch_code,branch_name,is_default',
            'cashierUser:user_id,full_name,email',
            'openedByUser:user_id,full_name,email',
            'closedByUser:user_id,full_name,email',
        ]);

        $snapshot = $this->buildSnapshot($shift);

        return [
            'cashier_shift_id' => (int) $shift->cashier_shift_id,
            'branch_id' => (int) $shift->branch_id,
            'branch' => $shift->branch ? [
                'branch_id' => (int) $shift->branch->branch_id,
                'branch_code' => (string) $shift->branch->branch_code,
                'branch_name' => (string) $shift->branch->branch_name,
                'is_default' => (bool) $shift->branch->is_default,
            ] : null,
            'shift_code' => (string) $shift->shift_code,
            'status' => (string) $shift->status,
            'currency' => (string) ($shift->currency ?? 'VND'),
            'terminal_code' => $shift->terminal_code,
            'row_version' => (int) ($shift->row_version ?? 1),
            'opened_at' => $shift->opened_at?->utc()->toIso8601String(),
            'closed_at' => $shift->closed_at?->utc()->toIso8601String(),
            'opening_float_amount' => $this->money($shift->opening_float_amount),
            'expected_cash_amount' => $this->moneyOrNull($shift->expected_cash_amount),
            'actual_cash_amount' => $this->moneyOrNull($shift->actual_cash_amount),
            'cash_discrepancy_amount' => $this->moneyOrNull($shift->cash_discrepancy_amount),
            'opening_note' => $shift->opening_note,
            'closing_note' => $shift->closing_note,
            'cashier' => $this->userPayload($shift->cashierUser, (int) $shift->cashier_user_id),
            'opened_by' => $this->userPayload($shift->openedByUser, $shift->opened_by !== null ? (int) $shift->opened_by : null),
            'closed_by' => $this->userPayload($shift->closedByUser, $shift->closed_by !== null ? (int) $shift->closed_by : null),
            'summary' => [
                'payments' => $snapshot['payment']['summary'],
                'cash' => $snapshot['cash']['summary'],
                'methods' => $snapshot['methods'],
            ],
            'flags' => [
                'is_open' => (string) $shift->status === 'Open',
                'has_payments' => (bool) data_get($snapshot, 'payment.summary.payment_count', 0),
                'has_refunds' => (bool) data_get($snapshot, 'payment.summary.refund_count', 0),
                'has_mixed_payment_currencies' => (bool) data_get($snapshot, 'payment.summary.currency.has_mixed_currencies', false),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSnapshot(CashierShift $shift, ?Carbon $asOf = null): array
    {
        $effectiveEnd = $shift->closed_at?->utc();
        if (! $effectiveEnd instanceof Carbon) {
            $effectiveEnd = $asOf?->copy()->utc() ?? Carbon::now('UTC');
        }

        $payments = $this->paymentsForShift($shift, $effectiveEnd);
        $paymentSummary = PaymentSummary::fromPayments($payments);
        $currencySummary = PaymentSummary::summarizeCurrencies($payments, (string) ($shift->currency ?? 'VND'));
        $methodSummary = $this->methodSummaries($payments, (string) ($shift->currency ?? 'VND'));
        $cashSummary = $this->cashSummary($payments, $shift->opening_float_amount ?? 0, (string) ($shift->currency ?? 'VND'));

        return [
            'payment' => [
                'summary' => [
                    'captured_total' => $this->money((float) ($paymentSummary['captured_amount'] ?? 0.0)),
                    'refunded_total' => $this->money((float) ($paymentSummary['refunded_amount'] ?? 0.0)),
                    'net_paid_total' => $this->money((float) ($paymentSummary['net_paid_amount'] ?? 0.0)),
                    'deposit_net' => $this->money((float) ($paymentSummary['deposit_net_amount'] ?? 0.0)),
                    'final_net' => $this->money((float) ($paymentSummary['final_net_amount'] ?? 0.0)),
                    'payment_count' => $payments->count(),
                    'refund_count' => $payments->filter(fn (Payment $payment): bool => (string) ($payment->payment_type ?? '') === 'Refund')->count(),
                    'currency' => [
                        'currency' => $currencySummary['currency'],
                        'currencies' => $currencySummary['currencies'],
                        'has_mixed_currencies' => (bool) ($currencySummary['has_mixed_currencies'] ?? false),
                    ],
                ],
                'raw' => $paymentSummary,
            ],
            'cash' => $cashSummary,
            'methods' => $methodSummary,
        ];
    }

    /**
     * @return Collection<int,Payment>
     */
    private function paymentsForShift(CashierShift $shift, Carbon $effectiveEnd): Collection
    {
        $openedAt = $shift->opened_at?->copy()->utc() ?? Carbon::parse((string) $shift->created_at)->utc();
        // Persisted payment timestamps are stored at second precision in test SQLite and
        // production schema dumps, so compare using canonical second-precision strings.
        $start = $openedAt->toDateTimeString();
        $end = $effectiveEnd->copy()->utc()->toDateTimeString();
        $shiftCurrency = $this->normalizeCurrency((string) ($shift->currency ?? 'VND'));

        /** @var Collection<int,Payment> $payments */
        $payments = Payment::query()
            ->with('refundOfPayment')
            ->where('created_by', (int) $shift->cashier_user_id)
            ->where('branch_id', (int) $shift->branch_id)
            ->whereRaw('COALESCE(paid_at, created_at) >= ? AND COALESCE(paid_at, created_at) <= ?', [$start, $end])
            ->whereRaw('UPPER(TRIM(COALESCE(currency, ?))) = ?', [$shiftCurrency, $shiftCurrency])
            ->orderBy('payment_id')
            ->get();

        return $payments;
    }

    /**
     * @param  Collection<int,Payment>  $payments
     * @return array<int,array<string,mixed>>
     */
    private function methodSummaries(Collection $payments, string $fallbackCurrency): array
    {
        $buckets = [];

        foreach ($payments as $payment) {
            $method = $this->normalizeMethod((string) ($payment->payment_method ?? $payment->payment_provider ?? 'Other'));
            $currency = $this->normalizeCurrency((string) ($payment->currency ?? $fallbackCurrency));
            $key = $method.'|'.$currency;
            $bucket = $buckets[$key] ?? [
                'payment_method' => $method,
                'currency' => $currency,
                'captured_amount_minor' => 0,
                'refunded_amount_minor' => 0,
                'payment_count' => 0,
                'refund_count' => 0,
            ];

            $amountMinor = Money::minorUnits($payment->amount ?? 0, true);
            if ($amountMinor <= 0) {
                $buckets[$key] = $bucket;

                continue;
            }

            $status = (string) ($payment->status?->value ?? $payment->status);
            $paymentType = (string) ($payment->payment_type ?? '');

            if (in_array($paymentType, ['Deposit', 'Final'], true) && PaymentSummary::isCapturedStatus($status)) {
                $bucket['captured_amount_minor'] += $amountMinor;
                $bucket['payment_count']++;
            }

            if ($paymentType === 'Refund' && PaymentSummary::isRefundedStatus($status)) {
                $bucket['refunded_amount_minor'] += $amountMinor;
                $bucket['refund_count']++;
            }

            $buckets[$key] = $bucket;
        }

        $rows = [];
        foreach ($buckets as $bucket) {
            $capturedMinor = (int) $bucket['captured_amount_minor'];
            $refundedMinor = (int) $bucket['refunded_amount_minor'];
            $rows[] = [
                'payment_method' => $bucket['payment_method'],
                'currency' => $bucket['currency'],
                'captured_amount' => Money::formatMinor($capturedMinor),
                'refunded_amount' => Money::formatMinor($refundedMinor),
                'net_amount' => Money::formatMinor($capturedMinor - $refundedMinor),
                'payment_count' => (int) $bucket['payment_count'],
                'refund_count' => (int) $bucket['refund_count'],
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return [$left['payment_method'], $left['currency']] <=> [$right['payment_method'], $right['currency']];
        });

        return $rows;
    }

    /**
     * @param  Collection<int,Payment>  $payments
     * @return array<string,mixed>
     */
    private function cashSummary(Collection $payments, float|int|string|null $openingFloatAmount, string $shiftCurrency): array
    {
        $capturedMinor = 0;
        $refundedMinor = 0;
        $currencyMismatches = [];

        foreach ($payments as $payment) {
            if (! $this->isCashLike($payment)) {
                continue;
            }

            $currency = $this->normalizeCurrency((string) ($payment->currency ?? $shiftCurrency));
            if ($currency !== $shiftCurrency) {
                $currencyMismatches[$currency] = true;

                continue;
            }

            $amountMinor = Money::minorUnits($payment->amount ?? 0, true);
            if ($amountMinor <= 0) {
                continue;
            }

            $status = (string) ($payment->status?->value ?? $payment->status);
            $paymentType = (string) ($payment->payment_type ?? '');

            if (in_array($paymentType, ['Deposit', 'Final'], true) && PaymentSummary::isCapturedStatus($status)) {
                $capturedMinor += $amountMinor;

                continue;
            }

            if ($paymentType === 'Refund' && PaymentSummary::isRefundedStatus($status)) {
                $refundedMinor += $amountMinor;
            }
        }

        $openingFloatMinor = Money::minorUnits($openingFloatAmount, true);
        $expectedMinor = $openingFloatMinor + $capturedMinor - $refundedMinor;

        return [
            'summary' => [
                'currency' => $shiftCurrency,
                'opening_float_amount' => Money::formatMinor($openingFloatMinor),
                'captured_amount' => Money::formatMinor($capturedMinor),
                'refunded_amount' => Money::formatMinor($refundedMinor),
                'expected_cash_amount' => Money::formatMinor($expectedMinor),
                'excluded_cash_currencies' => array_values(array_keys($currencyMismatches)),
                'has_excluded_cash_currencies' => $currencyMismatches !== [],
            ],
            'raw' => [
                'opening_float_amount' => Money::minorToFloat($openingFloatMinor),
                'captured_amount' => Money::minorToFloat($capturedMinor),
                'refunded_amount' => Money::minorToFloat($refundedMinor),
                'expected_cash_amount' => Money::minorToFloat($expectedMinor),
            ],
        ];
    }

    private function assertOpenShift(CashierShift $shift): void
    {
        if ((string) $shift->status !== 'Open') {
            throw ValidationException::withMessages([
                'cashier_shift' => ['Only open cashier shifts can be closed.'],
            ]);
        }
    }

    private function assertExpectedRowVersion(CashierShift $shift, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($shift->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => [self::STALE_ROW_VERSION_MESSAGE],
            ]);
        }
    }

    /**
     * @param  list<int>  $branchScope
     */
    private function applyBranchScope(Builder $query, array $branchScope): void
    {
        if ($branchScope === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('branch_id', $branchScope);
    }

    private function generateShiftCode(int $cashierUserId, Carbon $openedAt): string
    {
        return sprintf(
            'CSH-%s-%d-%s',
            $openedAt->copy()->utc()->format('YmdHis'),
            $cashierUserId,
            Str::upper(Str::random(6))
        );
    }

    private function normalizeCurrency(?string $currency): string
    {
        $normalized = strtoupper(trim((string) $currency));

        return $normalized !== '' ? $normalized : 'VND';
    }

    private function normalizeMethod(string $method): string
    {
        $normalized = trim($method);

        return $normalized !== '' ? $normalized : 'Other';
    }

    private function resolveShiftSortBy(string $sortBy): string
    {
        return match ($sortBy) {
            'closed_at', 'cashier_shift_id', 'shift_code' => $sortBy,
            default => 'opened_at',
        };
    }

    private function isCashLike(Payment $payment): bool
    {
        $method = strtolower(trim((string) ($payment->payment_method ?? '')));
        $provider = strtolower(trim((string) ($payment->payment_provider ?? '')));

        return $method === 'cash' || $provider === 'cash';
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function money(float|int|string|null $value): string
    {
        return Money::format($value ?? 0, false);
    }

    private function moneyOrNull(float|int|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->money($value);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function userPayload(?User $user, ?int $fallbackUserId = null): ?array
    {
        $userId = $user?->user_id !== null ? (int) $user->user_id : $fallbackUserId;
        if ($userId === null || $userId <= 0) {
            return null;
        }

        return [
            'user_id' => $userId,
            'full_name' => $user?->full_name,
            'email' => $user?->email,
        ];
    }
}
