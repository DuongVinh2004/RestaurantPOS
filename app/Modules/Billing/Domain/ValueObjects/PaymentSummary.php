<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\ValueObjects;

use App\Modules\Payments\Domain\Models\Payment;
use App\SharedKernel\Money\Money;
use BackedEnum;

final class PaymentSummary
{
    /**
     * @param  iterable<mixed>  $payments
     * @return array<string,float>
     */
    public static function fromPayments(iterable $payments): array
    {
        $minor = [
            'deposit_captured_amount' => 0,
            'deposit_refunded_amount' => 0,
            'deposit_raw_net_amount' => 0,
            'deposit_net_amount' => 0,
            'deposit_over_refunded_amount' => 0,
            'final_captured_amount' => 0,
            'final_refunded_amount' => 0,
            'final_raw_net_amount' => 0,
            'final_net_amount' => 0,
            'final_over_refunded_amount' => 0,
            'captured_amount' => 0,
            'refunded_amount' => 0,
            'raw_net_paid_amount' => 0,
            'net_paid_amount' => 0,
            'over_refunded_amount' => 0,
        ];

        $normalizedPayments = [];
        $paymentTypeById = [];

        foreach ($payments as $payment) {
            $normalizedPayments[] = $payment;

            $paymentId = self::normalizeIdentifier(self::readValue($payment, 'payment_id'));
            $paymentType = self::normalizeScalar(self::readValue($payment, 'payment_type'));

            if ($paymentId !== null && in_array($paymentType, ['Deposit', 'Final'], true)) {
                $paymentTypeById[$paymentId] = $paymentType;
            }
        }

        foreach ($normalizedPayments as $payment) {
            $paymentType = self::normalizeScalar(self::readValue($payment, 'payment_type'));
            $status = self::normalizeScalar(self::readValue($payment, 'status'));
            $amountMinor = Money::minorUnits(self::readValue($payment, 'amount') ?? 0, true);

            if ($amountMinor <= 0 || $paymentType === null || $status === null) {
                continue;
            }

            if ($paymentType === 'Deposit' && self::isCapturedStatus($status)) {
                $minor['deposit_captured_amount'] += $amountMinor;

                continue;
            }

            if ($paymentType === 'Final' && self::isCapturedStatus($status)) {
                $minor['final_captured_amount'] += $amountMinor;

                continue;
            }

            if ($paymentType === 'Refund' && self::isRefundedStatus($status)) {
                $target = self::resolveRefundTargetPaymentType($payment, $paymentTypeById);
                if ($target === 'Deposit') {
                    $minor['deposit_refunded_amount'] += $amountMinor;

                    continue;
                }
                if ($target === 'Final') {
                    $minor['final_refunded_amount'] += $amountMinor;

                    continue;
                }
            }
        }

        $minor['deposit_raw_net_amount'] = $minor['deposit_captured_amount'] - $minor['deposit_refunded_amount'];
        $minor['final_raw_net_amount'] = $minor['final_captured_amount'] - $minor['final_refunded_amount'];
        $minor['deposit_over_refunded_amount'] = max(0, -1 * $minor['deposit_raw_net_amount']);
        $minor['final_over_refunded_amount'] = max(0, -1 * $minor['final_raw_net_amount']);
        $minor['deposit_net_amount'] = max(0, $minor['deposit_raw_net_amount']);
        $minor['final_net_amount'] = max(0, $minor['final_raw_net_amount']);
        $minor['captured_amount'] = $minor['deposit_captured_amount'] + $minor['final_captured_amount'];
        $minor['refunded_amount'] = $minor['deposit_refunded_amount'] + $minor['final_refunded_amount'];
        $minor['raw_net_paid_amount'] = $minor['deposit_raw_net_amount'] + $minor['final_raw_net_amount'];
        $minor['net_paid_amount'] = $minor['deposit_net_amount'] + $minor['final_net_amount'];
        $minor['over_refunded_amount'] = $minor['deposit_over_refunded_amount'] + $minor['final_over_refunded_amount'];

        $summary = [];
        foreach ($minor as $key => $amountMinor) {
            $summary[$key] = Money::minorToFloat($amountMinor);
        }

        $summary['has_over_refund'] = $minor['over_refunded_amount'] > 0 ? 1.0 : 0.0;

        return $summary;
    }

    /**
     * @param  array<string,float|int|bool>  $summary
     */
    public static function hasOverRefund(array $summary): bool
    {
        return Money::isPositive($summary['over_refunded_amount'] ?? 0)
            || (bool) ($summary['has_over_refund'] ?? false);
    }

    /**
     * @param  iterable<mixed>  $payments
     * @return array{currency:?string,currencies:array<int,string>,has_mixed_currencies:bool}
     */
    public static function summarizeCurrencies(iterable $payments, ?string $fallbackCurrency = null): array
    {
        $currencies = self::collectCurrencies($payments);
        if ($currencies !== []) {
            return [
                'currency' => count($currencies) === 1 ? $currencies[0] : null,
                'currencies' => $currencies,
                'has_mixed_currencies' => count($currencies) > 1,
            ];
        }

        $fallbackCurrency = self::normalizeScalar($fallbackCurrency);

        return [
            'currency' => $fallbackCurrency,
            'currencies' => $fallbackCurrency !== null ? [$fallbackCurrency] : [],
            'has_mixed_currencies' => false,
        ];
    }

    /**
     * @param  iterable<mixed>  $payments
     * @return array<int,string>
     */
    public static function collectCurrencies(iterable $payments): array
    {
        $currencies = [];

        foreach ($payments as $payment) {
            $currency = self::normalizeScalar(self::readValue($payment, 'currency'));
            if ($currency === null) {
                continue;
            }

            $currencies[$currency] = true;
        }

        $values = array_keys($currencies);
        sort($values);

        return $values;
    }

    /**
     * @param  array<int,string>|null  $paymentTypeById
     */
    public static function resolveRefundTargetPaymentType(mixed $payment, ?array $paymentTypeById = null): ?string
    {
        $relationTargetType = self::resolveRefundTargetTypeFromRelation($payment);
        if ($relationTargetType !== null) {
            return $relationTargetType;
        }

        $mappedTargetType = self::resolveRefundTargetTypeFromSourceMap($payment, $paymentTypeById ?? []);
        if ($mappedTargetType !== null) {
            return $mappedTargetType;
        }

        $payload = self::readValue($payment, 'provider_response_json');

        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            }
        }

        if (! is_array($payload)) {
            return null;
        }

        $target = $payload['refund_target_payment_type'] ?? null;
        if (! is_string($target)) {
            return null;
        }

        $target = ucfirst(strtolower(trim($target)));

        return in_array($target, ['Deposit', 'Final'], true) ? $target : null;
    }

    private static function resolveRefundTargetTypeFromRelation(mixed $payment): ?string
    {
        $related = null;

        if (is_object($payment)) {
            if (method_exists($payment, 'relationLoaded') && $payment->relationLoaded('refundOfPayment')) {
                $related = $payment->getRelation('refundOfPayment');
            } elseif (property_exists($payment, 'refundOfPayment') || isset($payment->refundOfPayment)) {
                $related = $payment->refundOfPayment;
            }
        } elseif (is_array($payment)) {
            $related = $payment['refundOfPayment'] ?? null;
        }

        if (! is_object($related) && ! is_array($related)) {
            return null;
        }

        $target = self::normalizeScalar(self::readValue($related, 'payment_type'));

        return in_array($target, ['Deposit', 'Final'], true) ? $target : null;
    }

    /**
     * @param  array<int,string>  $paymentTypeById
     */
    private static function resolveRefundTargetTypeFromSourceMap(mixed $payment, array $paymentTypeById): ?string
    {
        if ($paymentTypeById === []) {
            return null;
        }

        $sourcePaymentId = self::normalizeIdentifier(self::readValue($payment, 'refund_of_payment_id'));
        if ($sourcePaymentId === null) {
            return null;
        }

        $target = $paymentTypeById[$sourcePaymentId] ?? null;

        return in_array($target, ['Deposit', 'Final'], true) ? $target : null;
    }

    public static function isCapturedStatus(?string $status): bool
    {
        return in_array((string) $status, ['Success', 'Partial'], true);
    }

    public static function isRefundedStatus(?string $status): bool
    {
        return (string) $status === 'Refunded';
    }

    private static function readValue(mixed $payment, string $key): mixed
    {
        if (is_array($payment)) {
            return $payment[$key] ?? null;
        }

        if (is_object($payment)) {
            if (method_exists($payment, 'getAttribute')) {
                return $payment->getAttribute($key);
            }

            return $payment->{$key} ?? null;
        }

        return null;
    }

    private static function normalizeScalar(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return trim((string) $value->value);
        }

        if (is_string($value) || is_numeric($value)) {
            $normalized = trim((string) $value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    private static function normalizeIdentifier(mixed $value): ?int
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || ! preg_match('/^[0-9]+$/', $normalized)) {
            return null;
        }

        $identifier = (int) $normalized;

        return $identifier > 0 ? $identifier : null;
    }
}
