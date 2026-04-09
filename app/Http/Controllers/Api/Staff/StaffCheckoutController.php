<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Concerns\AppliesDeprecatedRouteHeaders;
use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CheckoutOrderRequest;
use App\Http\Requests\Staff\CloseOrderRequest;
use App\Http\Requests\Staff\PayOrderRequest;
use App\Http\Requests\Staff\RefundAndCancelReservationRequest;
use App\Http\Requests\Staff\RefundPreviewRequest;
use App\Http\Requests\Staff\RefundReservationRequest;
use App\Http\Resources\ReservationOrderResource;
use App\Http\Resources\ReservationResource;
use App\Services\Staff\StaffCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffCheckoutController extends Controller
{
    use AppliesDeprecatedRouteHeaders;
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffCheckoutService $checkoutService,
    ) {}

    public function store(int $order_id, CheckoutOrderRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $result = $this->checkoutService->checkout(
            orderId: $order_id,
            paymentMethod: (string) $request->input('payment_method'),
            paidAmount: (float) $request->input('paid_amount'),
            currency: (string) ($request->input('currency') ?? 'VND'),
            transactionCode: (string) ($request->input('transaction_code') ?? ''),
            paymentProvider: (string) ($request->input('payment_provider') ?? ''),
            notes: (string) ($request->input('notes') ?? ''),
            discountAmount: $request->filled('discount_amount') ? (float) $request->input('discount_amount') : null,
            expectedRowVersion: (int) $request->input('row_version'),
            staffUserId: $staffUserId,
            idempotencyKey: $this->resolveIdempotencyKey($request),
        );

        return response()->json([
            'data' => $result,
        ]);
    }

    public function checkout(int $order_id, CheckoutOrderRequest $request): JsonResponse
    {
        return $this->deprecatedAliasResponse(
            $this->store($order_id, $request),
            $request,
            '/api/v1/staff/orders/{order_id}/checkout',
            '/api/v1/staff/orders/{order_id}/settlement/finalize',
        );
    }

    public function close(int $order_id, CloseOrderRequest $request): JsonResponse
    {
        return $this->lockBill($order_id, $request, true);
    }

    public function billSnapshot(int $order_id, CloseOrderRequest $request): JsonResponse
    {
        return $this->lockBill($order_id, $request, false);
    }

    public function settlementPreview(int $order_id, Request $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $preview = $this->checkoutService->previewSettlement(
            orderId: $order_id,
            fallbackCurrency: (string) ($request->query('currency') ?? 'VND'),
            staffUserId: $staffUserId,
        );

        return response()->json([
            'data' => $preview,
            'meta' => [
                'action' => 'settlement_preview',
            ],
        ]);
    }

    public function finalizeSettlement(int $order_id, CheckoutOrderRequest $request): JsonResponse
    {
        return $this->store($order_id, $request);
    }

    public function refundPreview(int $reservation_id, RefundPreviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $staffUserId = $this->resolveStaffActorUserId($request);
        $preview = $this->checkoutService->previewRefund(
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

    public function lockBill(int $order_id, CloseOrderRequest $request, bool $legacyAlias = false): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $order = $this->checkoutService->lockBill(
            orderId: $order_id,
            discountAmount: $request->filled('discount_amount') ? (float) $request->input('discount_amount') : null,
            notes: (string) ($request->input('notes') ?? ''),
            expectedRowVersion: (int) $request->input('row_version'),
            staffUserId: $staffUserId,
        );

        return response()->json([
            'data' => new ReservationOrderResource($order->load('items.item')),
            'meta' => [
                'action' => 'lock_bill',
                'legacy_route_alias' => $legacyAlias ? 'close' : null,
                'legacy_route_deprecated' => $legacyAlias,
                'semantics' => 'Captures and locks the current bill for payment. This does not complete payment or mark the reservation as finished.',
            ],
        ]);
    }

    public function pay(int $order_id, PayOrderRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $order = $this->checkoutService->payOrder(
            orderId: $order_id,
            paymentMethod: (string) $request->input('payment_method'),
            paidAmount: (float) $request->input('paid_amount'),
            currency: (string) ($request->input('currency') ?? 'VND'),
            transactionCode: (string) ($request->input('transaction_code') ?? ''),
            paymentProvider: (string) ($request->input('payment_provider') ?? ''),
            notes: (string) ($request->input('notes') ?? ''),
            expectedRowVersion: (int) $request->input('row_version'),
            staffUserId: $staffUserId,
            idempotencyKey: $this->resolveIdempotencyKey($request),
        );

        return response()->json([
            'data' => new ReservationOrderResource($order->load('items.item')),
        ]);
    }

    public function refund(int $reservation_id, RefundReservationRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $result = $this->checkoutService->refundReservation(
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

        $result = $this->checkoutService->refundAndCancelReservation(
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


    private function resolveIdempotencyKey($request): string
    {
        return (string) ($request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $request->input('idempotency_key')
            ?? '');
    }

    private function deprecatedAliasResponse(JsonResponse $response, Request $request, string $aliasPath, string $canonicalPath): JsonResponse
    {
        return $this->markDeprecatedRouteAliasForRequest($request, $response, $aliasPath, $canonicalPath);
    }
}
