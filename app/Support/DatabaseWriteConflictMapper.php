<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class DatabaseWriteConflictMapper
{
    public static function toValidationException(QueryException $exception): ?ValidationException
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        if (! in_array($sqlState, ['23000', '23505', '45000'], true)) {
            return null;
        }

        $message = self::normalizedMessage($exception);

        return match (true) {
            str_contains($message, 'reservation_tables overlap conflict with another active reservation') => ValidationException::withMessages([
                'table_ids' => ['One or more tables conflict with another active reservation. Reload availability and choose a different table or time.'],
            ]),
            str_contains($message, 'reservation_tables overlap conflict with active table hold') => ValidationException::withMessages([
                'table_ids' => ['One or more tables are already covered by another active hold. Reload availability and try again.'],
            ]),
            str_contains($message, 'table_hold_details overlap conflict with active reservation') => ValidationException::withMessages([
                'table_ids' => ['One or more tables conflict with an active reservation in the same time window. Choose a different table or time.'],
            ]),
            str_contains($message, 'table_hold_details overlap conflict with another active hold') => ValidationException::withMessages([
                'table_ids' => ['One or more tables are already held by another session. Reload availability and try again.'],
            ]),
            str_contains($message, 'uq_table_holds__active_session_hold_key') || str_contains($message, 'active_session_hold_key') => ValidationException::withMessages([
                'session_id' => ['This session already has another active hold. Refresh or cancel the existing hold before creating a new one.'],
            ]),
            str_contains($message, 'uq_waiting_list__active_owner_waiting_key') || str_contains($message, 'active_owner_waiting_key') => ValidationException::withMessages([
                'waiting_list' => ['This customer already has another active waiting-list entry. Reload data or close the existing entry before creating a new one.'],
            ]),
            str_contains($message, 'user_vouchers used-state invariant violated') => ValidationException::withMessages([
                'voucher' => ['The voucher state changed or is no longer valid. Reload data and try again.'],
            ]),
            str_contains($message, 'uq_agent_assignments__active_conversation_id') || str_contains($message, 'active_conversation_id') => ValidationException::withMessages([
                'conversation_id' => ['This conversation already has another active agent assignment. Reload data and try again.'],
            ]),
            str_contains($message, 'uq_bank_accounts__default_user_id') || str_contains($message, 'default_user_id') => ValidationException::withMessages([
                'is_default' => ['This user already has another default bank account. Reload data and try again.'],
            ]),
            str_contains($message, 'chk_reservations__money_nonneg') => ValidationException::withMessages([
                'reservation' => ['Reservation money fields must not be negative.'],
            ]),
            str_contains($message, 'chk_reservations__reserved_requires_checked_in_at') => ValidationException::withMessages([
                'status' => ['Reservations in Reserved status must have checked_in_at.'],
            ]),
            str_contains($message, 'menu_item_prices overlap conflict for same item') => ValidationException::withMessages([
                'effective_from' => ['The effective price window overlaps another price for this menu item.'],
            ]),
            str_contains($message, 'uq_reservations__active_applied_user_voucher_id') || str_contains($message, 'active_applied_user_voucher_id') => ValidationException::withMessages([
                'voucher' => ['This voucher is already applied to another active reservation. Reload data and try again.'],
            ]),
            self::isPaymentProviderTransactionConflictMessage($message) => ValidationException::withMessages([
                'transaction_code' => ['Mã giao dịch này đã tồn tại cho payment provider hiện tại. Vui lòng kiểm tra lại đối soát hoặc dùng mã khác.'],
            ]),
            self::isPaymentIdempotencyConflictMessage($message) => ValidationException::withMessages([
                'idempotency_key' => ['idempotency key already used.'],
            ]),
            str_contains($message, 'uq_staff_api_keys__key_hash') || str_contains($message, 'staff_api_keys__key_hash') => ValidationException::withMessages([
                'api_key' => ['This staff API key already exists. Use a different key or revoke the old key before creating a new one.'],
            ]),
            str_contains($message, 'payments refund rows must reference source payment') => ValidationException::withMessages([
                'refund_of_payment_id' => ['Refund rows must reference a valid source payment.'],
            ]),
            str_contains($message, 'payments only refund rows may reference refund_of_payment_id') => ValidationException::withMessages([
                'payment_type' => ['Only refund payments may reference a source payment.'],
            ]),
            str_contains($message, 'payments refund lineage must target a non-refund payment') => ValidationException::withMessages([
                'refund_of_payment_id' => ['Refunds must reference a non-refund source payment.'],
            ]),
            str_contains($message, 'payments refund lineage must stay inside reservation') => ValidationException::withMessages([
                'refund_of_payment_id' => ['Refunds may not reference a payment from another reservation.'],
            ]),
            str_contains($message, 'payments refund currency must match source payment') => ValidationException::withMessages([
                'currency' => ['Refund currency must match the source payment currency.'],
            ]),
            str_contains($message, 'payments refund exceeds source payment amount') => ValidationException::withMessages([
                'refund_amount' => ['Total refunded amount exceeds the source payment amount. Reload data and try again.'],
            ]),
            self::isIngredientStockMovementReferenceConflictMessage($message) => ValidationException::withMessages([
                'reference_id' => ['This stock movement reference is already recorded. Reload lineage data and retry against the existing movement instead of creating a duplicate.'],
            ]),
            default => null,
        };
    }

    public static function isPaymentProviderTransactionConflict(QueryException $exception): bool
    {
        return self::isPaymentProviderTransactionConflictMessage(self::normalizedMessage($exception));
    }

    public static function isPaymentIdempotencyConflict(QueryException $exception): bool
    {
        return self::isPaymentIdempotencyConflictMessage(self::normalizedMessage($exception));
    }

    private static function normalizedMessage(QueryException $exception): string
    {
        $driverMessage = (string) ($exception->errorInfo[2] ?? '');
        $message = $driverMessage !== '' ? $driverMessage : $exception->getMessage();

        return strtolower($message);
    }

    private static function isPaymentProviderTransactionConflictMessage(string $message): bool
    {
        return self::containsAny($message, [
            'uq_payments__payment_provider__transaction_code',
            'payment_provider__transaction_code',
            'unique constraint failed: payments.payment_provider, payments.transaction_code',
            'unique constraint failed: payments.transaction_code',
        ]);
    }

    private static function isPaymentIdempotencyConflictMessage(string $message): bool
    {
        return self::containsAny($message, [
            'uq_payments__idempotency_key',
            'uq_payments_idempotency_key',
            'payments_idempotency_key_unique',
            'unique constraint failed: payments.idempotency_key',
        ]);
    }

    private static function isIngredientStockMovementReferenceConflictMessage(string $message): bool
    {
        return self::containsAny($message, [
            'uq_ingredient_stock_movements__reference',
            'unique constraint failed: ingredient_stock_movements.reference_type, ingredient_stock_movements.reference_id',
        ]);
    }

    /**
     * @param array<int,string> $needles
     */
    private static function containsAny(string $message, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
