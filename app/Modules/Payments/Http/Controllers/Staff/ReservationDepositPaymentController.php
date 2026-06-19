<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Payments\Application\UseCases\Capture\StaffReservationDepositService;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Http\Requests\Staff\PayReservationDepositRequest;
use App\Modules\Payments\Infrastructure\Internal\PaymentProviderPayloadSanitizer;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReservationDepositPaymentController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffReservationDepositService $depositService,
    ) {}

    public function preview(int $reservation_id, Request $request): JsonResponse
    {
        $result = $this->depositService->previewDeposit(
            reservationId: $reservation_id,
            fallbackCurrency: (string) ($request->query('currency') ?? 'VND'),
        );

        return response()->json([
            'data' => [
                'reservation' => new ReservationResource($result['reservation']),
                'deposit' => $this->transformDepositPayload($result['deposit']),
            ],
            'meta' => [
                'action' => 'deposit_preview',
            ],
        ]);
    }

    public function pay(int $reservation_id, PayReservationDepositRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $result = $this->depositService->payDeposit(
            reservationId: $reservation_id,
            amount: (float) $request->input('amount'),
            paymentMethod: (string) $request->input('payment_method'),
            currency: (string) ($request->input('currency') ?? 'VND'),
            transactionCode: (string) ($request->input('transaction_code') ?? ''),
            paymentProvider: (string) ($request->input('payment_provider') ?? ''),
            notes: (string) ($request->input('notes') ?? ''),
            expectedRowVersion: (int) $request->input('row_version'),
            staffUserId: $staffUserId,
            idempotencyKey: $this->resolveIdempotencyKey($request),
        );

        return response()->json([
            'data' => [
                'reservation' => new ReservationResource($result['reservation']),
                'payment' => $this->transformPayment($result['payment']),
                'deposit' => $this->transformDepositPayload($result['deposit']),
            ],
            'meta' => [
                'action' => 'deposit_pay',
            ],
        ]);
    }

    /**
     * @param  array<string,mixed>  $deposit
     * @return array<string,mixed>
     */
    private function transformDepositPayload(array $deposit): array
    {
        $payments = $deposit['payments'] ?? collect();
        if ($payments instanceof Collection) {
            $payments = $payments->map(fn (Payment $payment): array => $this->transformPayment($payment))->values()->all();
        }

        return [
            'status' => $deposit['status'] ?? null,
            'required_amount' => $deposit['required_amount'] ?? '0.00',
            'paid_amount' => $deposit['paid_amount'] ?? '0.00',
            'remaining_amount' => $deposit['remaining_amount'] ?? ($deposit['outstanding_amount'] ?? '0.00'),
            'outstanding_amount' => $deposit['outstanding_amount'] ?? '0.00',
            'currency' => $deposit['currency'] ?? null,
            'currencies' => array_values($deposit['currencies'] ?? []),
            'has_mixed_currencies' => (bool) ($deposit['has_mixed_currencies'] ?? false),
            'status_flags' => $deposit['status_flags'] ?? [],
            'can_accept_payment' => (bool) ($deposit['can_accept_payment'] ?? false),
            'payment_summary' => $deposit['payment_summary'] ?? [],
            'payment_session_summary' => $deposit['payment_session_summary'] ?? [],
            'self_service' => $deposit['self_service'] ?? null,
            'payments' => $payments,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function transformPayment(?Payment $payment): ?array
    {
        if (! $payment) {
            return null;
        }

        return [
            'payment_id' => (int) $payment->payment_id,
            'refund_of_payment_id' => $payment->refund_of_payment_id !== null ? (int) $payment->refund_of_payment_id : null,
            'amount' => number_format((float) ($payment->amount ?? 0.0), 0, '.', ''),
            'currency' => (string) ($payment->currency ?? 'VND'),
            'payment_method' => $payment->payment_method,
            'payment_provider' => $payment->payment_provider,
            'payment_type' => (string) ($payment->payment_type ?? ''),
            'status' => (string) ($payment->status?->value ?? $payment->status),
            'transaction_code' => $payment->transaction_code,
            'created_by' => $payment->created_by !== null ? (int) $payment->created_by : null,
            'notes' => $payment->notes,
            'paid_at' => $payment->paid_at?->utc()->toIso8601String(),
            'created_at' => $payment->created_at?->utc()->toIso8601String(),
            'updated_at' => $payment->updated_at?->utc()->toIso8601String(),
            'refund_target_payment_type' => $payment->payment_type === 'Refund'
                ? PaymentSummary::resolveRefundTargetPaymentType($payment)
                : null,
            'provider_response_json' => PaymentProviderPayloadSanitizer::sanitizePaymentResponseForPresentation($payment->provider_response_json),
        ];
    }

    private function resolveIdempotencyKey(Request $request): string
    {
        return (string) ($request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $request->input('idempotency_key')
            ?? '');
    }
}
