<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\UseCases\Refunds\ReservationRefundWorkflow;
use App\Modules\Payments\Http\Requests\Staff\RefundAndCancelReservationRequest;
use App\Modules\Payments\Http\Requests\Staff\RefundPreviewRequest;
use App\Modules\Payments\Http\Requests\Staff\RefundReservationRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationRefundController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly ReservationRefundWorkflow $refundWorkflow,
    ) {}

    public function preview(int $reservation_id, RefundPreviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $staffUserId = $this->resolveStaffActorUserId($request);
        $preview = $this->refundWorkflow->previewRefund(
            reservationId: $reservation_id,
            refundScope: (string) ($validated['refund_scope'] ?? 'all'),
            refundAmount: array_key_exists('refund_amount', $validated) ? (float) $validated['refund_amount'] : null,
            currency: (string) ($validated['currency'] ?? 'VND'),
            cancelAfterPayment: array_key_exists('cancel_after_payment', $validated)
                ? (bool) $validated['cancel_after_payment']
                : null,
            staffUserId: $staffUserId,
        );

        return response()->json([
            'data' => [
                'reservation' => new ReservationResource($preview['reservation']),
                'refund' => $preview['refund'],
            ],
            'meta' => [
                'action' => 'refund_preview',
            ],
        ]);
    }

    public function refund(int $reservation_id, RefundReservationRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $result = $this->refundWorkflow->refundReservation(
            reservationId: $reservation_id,
            paymentMethod: (string) $request->input('payment_method'),
            refundScope: (string) ($request->input('refund_scope') ?? 'all'),
            refundAmount: $request->filled('refund_amount') ? (float) $request->input('refund_amount') : null,
            currency: (string) ($request->input('currency') ?? 'VND'),
            transactionCode: (string) ($request->input('transaction_code') ?? ''),
            paymentProvider: (string) ($request->input('payment_provider') ?? ''),
            notes: (string) ($request->input('notes') ?? ''),
            reason: $request->filled('reason') ? (string) $request->input('reason') : null,
            expectedRowVersion: (int) $request->input('row_version'),
            staffUserId: $staffUserId,
            idempotencyKey: $this->resolveIdempotencyKey($request),
        );

        return response()->json([
            'data' => [
                'reservation' => new ReservationResource($result['reservation']),
                'refund' => $result['refund'],
            ],
        ]);
    }

    public function refundAndCancel(int $reservation_id, RefundAndCancelReservationRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $result = $this->refundWorkflow->refundAndCancelReservation(
            reservationId: $reservation_id,
            paymentMethod: (string) $request->input('payment_method'),
            refundScope: (string) ($request->input('refund_scope') ?? 'all'),
            refundAmount: $request->filled('refund_amount') ? (float) $request->input('refund_amount') : null,
            currency: (string) ($request->input('currency') ?? 'VND'),
            transactionCode: (string) ($request->input('transaction_code') ?? ''),
            paymentProvider: (string) ($request->input('payment_provider') ?? ''),
            notes: (string) ($request->input('notes') ?? ''),
            reason: $request->filled('reason') ? (string) $request->input('reason') : null,
            cancelReason: $request->filled('cancel_reason') ? (string) $request->input('cancel_reason') : null,
            expectedRowVersion: (int) $request->input('row_version'),
            staffUserId: $staffUserId,
            idempotencyKey: $this->resolveIdempotencyKey($request),
        );

        return response()->json([
            'data' => [
                'reservation' => new ReservationResource($result['reservation']),
                'refund' => $result['refund'],
            ],
        ]);
    }

    private function resolveIdempotencyKey(Request $request): string
    {
        return (string) ($request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $request->input('idempotency_key')
            ?? '');
    }
}
