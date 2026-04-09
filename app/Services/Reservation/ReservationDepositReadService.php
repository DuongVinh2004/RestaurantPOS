<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationDepositPaymentSession;
use App\Support\PaymentSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReservationDepositReadService
{
    public function __construct(
        private readonly ReservationDepositSelfServiceStateService $depositSelfServiceStateService,
    ) {}

    /**
     * @param iterable<int,Payment>|null $payments
     * @param iterable<int,ReservationDepositPaymentSession>|null $paymentSessions
     * @param array<string,mixed>|null $paymentSummary
     * @return array<string,mixed>
     */
    public function buildSnapshot(
        Reservation $reservation,
        ?iterable $payments = null,
        ?iterable $paymentSessions = null,
        ?array $paymentSummary = null,
        string $fallbackCurrency = 'VND',
        bool $includeSelfService = true,
    ): array {
        $paymentCollection = $this->normalizePayments($payments);
        $sessionCollection = $this->normalizeSessions($paymentSessions);
        $summary = $paymentSummary ?? PaymentSummary::fromPayments($paymentCollection);

        $requiredAmount = round(max(0.0, (float) ($reservation->deposit_required_amount ?? 0.0)), 2);
        $paidAmount = round(max(0.0, (float) ($summary['deposit_net_amount'] ?? $reservation->deposit_paid_amount ?? 0.0)), 2);
        $remainingAmount = round(max(0.0, $requiredAmount - $paidAmount), 2);
        $depositPayments = $paymentCollection
            ->filter(fn (Payment $payment): bool => $this->isDepositRelatedPayment($payment))
            ->values();

        $currencyMeta = PaymentSummary::summarizeCurrencies(
            $depositPayments,
            (string) ($reservation->bill_currency ?: $fallbackCurrency)
        );

        $state = $includeSelfService
            ? $this->depositSelfServiceStateService->buildState($reservation, $summary)
            : [];
        $sessionSummary = $this->buildPaymentSessionSummary($sessionCollection);

        return [
            'status' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? null),
            'required_amount' => $this->money($requiredAmount),
            'paid_amount' => $this->money($paidAmount),
            'remaining_amount' => $this->money($remainingAmount),
            'outstanding_amount' => $this->money($remainingAmount),
            'currency' => $currencyMeta['currency'] ?? (string) ($reservation->bill_currency ?: $fallbackCurrency),
            'currencies' => array_values((array) ($currencyMeta['currencies'] ?? [])),
            'has_mixed_currencies' => (bool) ($currencyMeta['has_mixed_currencies'] ?? false),
            'status_flags' => [
                'deposit_required' => $requiredAmount > 0.0001,
                'payment_recorded' => $paidAmount > 0.0001,
                'requires_collection' => $requiredAmount > 0.0001 && $remainingAmount > 0.0001,
                'fully_paid' => $requiredAmount > 0.0001 && $remainingAmount <= 0.0001,
                'has_refund' => (float) ($summary['deposit_refunded_amount'] ?? 0.0) > 0.0001,
                'is_refunded' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? '') === 'Refunded',
                'is_partially_refunded' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? '') === 'PartiallyRefunded',
                'is_forfeited' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? '') === 'Forfeited',
                'has_open_payment_session' => (bool) ($sessionSummary['has_open_session'] ?? false),
            ],
            'payment_summary' => [
                'deposit_captured' => $this->money((float) ($summary['deposit_captured_amount'] ?? 0.0)),
                'deposit_refunded' => $this->money((float) ($summary['deposit_refunded_amount'] ?? 0.0)),
                'deposit_net' => $this->money((float) ($summary['deposit_net_amount'] ?? 0.0)),
                'remaining_amount' => $this->money($remainingAmount),
            ],
            'self_service' => $state,
            'payment_session_summary' => $sessionSummary,
            'payments' => $depositPayments,
        ];
    }

    /**
     * @param Collection<int,ReservationDepositPaymentSession> $sessions
     * @return array<string,mixed>
     */
    private function buildPaymentSessionSummary(Collection $sessions): array
    {
        if ($sessions->isEmpty()) {
            return [
                'total_sessions' => 0,
                'open_session_count' => 0,
                'applied_session_count' => 0,
                'has_open_session' => false,
                'latest_session' => null,
            ];
        }

        $latest = $sessions
            ->sortByDesc(fn (ReservationDepositPaymentSession $session): int => (int) ($session->deposit_payment_session_id ?? 0))
            ->first();

        $openSessionCount = $sessions->filter(function (ReservationDepositPaymentSession $session): bool {
            $status = (string) ($session->session_status?->value ?? $session->session_status ?? '');

            return in_array($status, ['Created', 'Pending'], true);
        })->count();

        $appliedSessionCount = $sessions->filter(function (ReservationDepositPaymentSession $session): bool {
            return (string) ($session->settlement_status?->value ?? $session->settlement_status ?? '') === 'Applied';
        })->count();

        return [
            'total_sessions' => $sessions->count(),
            'open_session_count' => $openSessionCount,
            'applied_session_count' => $appliedSessionCount,
            'has_open_session' => $openSessionCount > 0,
            'latest_session' => $latest instanceof ReservationDepositPaymentSession
                ? [
                    'deposit_payment_session_id' => (int) $latest->deposit_payment_session_id,
                    'session_status' => (string) ($latest->session_status?->value ?? $latest->session_status),
                    'settlement_status' => (string) ($latest->settlement_status?->value ?? $latest->settlement_status),
                    'provider_code' => (string) ($latest->provider_code ?? ''),
                    'payment_method' => $latest->payment_method !== null ? (string) $latest->payment_method : null,
                    'amount' => $latest->amount !== null ? $this->money((float) $latest->amount) : null,
                    'currency' => $latest->currency !== null ? (string) $latest->currency : null,
                    'provider_expires_at' => $this->iso($latest->provider_expires_at),
                    'confirmed_at' => $this->iso($latest->confirmed_at),
                    'failed_at' => $this->iso($latest->failed_at),
                    'cancelled_at' => $this->iso($latest->cancelled_at),
                    'expired_at' => $this->iso($latest->expired_at),
                    'linked_payment_id' => $latest->linked_payment_id !== null ? (int) $latest->linked_payment_id : null,
                    'row_version' => (int) ($latest->row_version ?? 1),
                ]
                : null,
        ];
    }

    /**
     * @param iterable<int,Payment>|null $payments
     * @return Collection<int,Payment>
     */
    private function normalizePayments(?iterable $payments): Collection
    {
        if ($payments instanceof Collection) {
            return $payments->filter(fn (mixed $payment): bool => $payment instanceof Payment)->values();
        }

        return collect($payments ?? [])->filter(fn (mixed $payment): bool => $payment instanceof Payment)->values();
    }

    /**
     * @param iterable<int,ReservationDepositPaymentSession>|null $paymentSessions
     * @return Collection<int,ReservationDepositPaymentSession>
     */
    private function normalizeSessions(?iterable $paymentSessions): Collection
    {
        if ($paymentSessions instanceof Collection) {
            return $paymentSessions->filter(fn (mixed $session): bool => $session instanceof ReservationDepositPaymentSession)->values();
        }

        return collect($paymentSessions ?? [])->filter(fn (mixed $session): bool => $session instanceof ReservationDepositPaymentSession)->values();
    }

    private function isDepositRelatedPayment(Payment $payment): bool
    {
        $paymentType = (string) ($payment->payment_type ?? '');
        if ($paymentType === 'Deposit') {
            return true;
        }

        if ($paymentType !== 'Refund') {
            return false;
        }

        return PaymentSummary::resolveRefundTargetPaymentType($payment) === 'Deposit';
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }
}
