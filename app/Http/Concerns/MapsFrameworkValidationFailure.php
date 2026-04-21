<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Support\ApiErrorCategory;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\ValidationException;

final class MapsFrameworkValidationFailure
{
    /**
     * @return array{
     *   status:int,
     *   error_code:string,
     *   category_code:string,
     *   message:string,
     *   extra:array<string,mixed>
     * }|null
     */
    public static function classify(ValidationException $exception): ?array
    {
        $errors = self::normalizeErrors($exception->errors());

        if ($errors === []) {
            return null;
        }

        if (self::isRowVersionConflict($errors)) {
            return [
                'status' => 409,
                'error_code' => 'stale_row_version',
                'category_code' => ApiErrorCategory::STALE_WRITE,
                'message' => 'The resource was modified by another writer. Reload data and try again.',
                'extra' => [
                    'conflict_type' => 'stale_write',
                    'state_reason' => 'row_version_mismatch',
                    'next_actions' => [
                        'reload_resource',
                        'retry_with_latest_row_version',
                    ],
                ],
            ];
        }

        if (self::isUpdatedAtConflict($errors)) {
            return [
                'status' => 409,
                'error_code' => 'conflict',
                'category_code' => ApiErrorCategory::STALE_WRITE,
                'message' => 'The resource was modified by another writer. Reload data and try again.',
                'extra' => [
                    'conflict_type' => 'stale_write',
                    'state_reason' => 'updated_at_mismatch',
                    'next_actions' => [
                        'reload_resource',
                        'retry_with_latest_state',
                    ],
                ],
            ];
        }

        if (self::isIdempotencyConflict($errors)) {
            return [
                'status' => 409,
                'error_code' => 'idempotency_conflict',
                'category_code' => ApiErrorCategory::IDEMPOTENCY_CONFLICT,
                'message' => 'This idempotency key conflicts with an earlier request.',
                'extra' => [
                    'conflict_type' => 'idempotency_replay',
                    'replay_state' => 'already_used',
                    'state_reason' => 'idempotency_key_already_used',
                    'next_actions' => [
                        'retry_with_new_idempotency_key',
                    ],
                ],
            ];
        }

        if (self::looksLikeDomainInvariantViolation($exception, $errors)) {
            return [
                'status' => 422,
                'error_code' => 'validation_error',
                'category_code' => ApiErrorCategory::DOMAIN_INVARIANT_VIOLATION,
                'message' => 'The requested action violates a business rule.',
                'extra' => [],
            ];
        }

        return [
            'status' => 422,
            'error_code' => 'validation_error',
            'category_code' => ApiErrorCategory::VALIDATION_ERROR,
            'message' => 'Validation error.',
            'extra' => [],
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private static function isRowVersionConflict(array $errors): bool
    {
        foreach (['row_version', 'order_row_version', 'pre_order_row_version', 'reservation_row_version'] as $field) {
            if (! array_key_exists($field, $errors)) {
                continue;
            }

            if (self::messagesLookLikeStaleWrite($errors[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private static function isUpdatedAtConflict(array $errors): bool
    {
        foreach (['expected_updated_at', 'updated_at'] as $field) {
            if (! array_key_exists($field, $errors)) {
                continue;
            }

            if (self::messagesLookLikeStaleWrite($errors[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private static function isIdempotencyConflict(array $errors): bool
    {
        if (! array_key_exists('idempotency_key', $errors)) {
            return false;
        }

        foreach ($errors['idempotency_key'] as $message) {
            $normalized = mb_strtolower(trim($message), 'UTF-8');

            foreach ([
                'already used',
                'already bound to a different',
                'different payment request payload',
                'different refund request payload',
            ] as $fragment) {
                if (str_contains($normalized, $fragment)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $messages
     */
    private static function messagesLookLikeStaleWrite(array $messages): bool
    {
        foreach ($messages as $message) {
            $normalized = mb_strtolower(trim($message), 'UTF-8');

            foreach ([
                'row_version mismatch',
                'modified by another writer',
                'modified by another operation',
                'updated by another writer',
                'latest updated_at value',
                'hãy reload rồi thử lại',
                'reload and retry',
            ] as $fragment) {
                if (str_contains($normalized, $fragment)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private static function looksLikeDomainInvariantViolation(ValidationException $exception, array $errors): bool
    {
        if (self::validatorRepresentsRequestValidation($exception->validator ?? null)) {
            return false;
        }

        foreach ($errors as $field => $messages) {
            $normalizedField = mb_strtolower(trim($field), 'UTF-8');

            foreach ($messages as $message) {
                $normalized = mb_strtolower(trim($message), 'UTF-8');

                foreach ([
                    'only ',
                    'must ',
                    'requires ',
                    'required to ',
                    'exceeds ',
                    'exceeds ',
                    'already ',
                    'cannot ',
                    'does not match',
                    'must match',
                    'not allowed',
                    'not refundable',
                    'no refundable',
                    'must use',
                    'outside the required',
                    'violates a business rule',
                    'does not belong',
                    'branch does not match',
                    'supported',
                ] as $fragment) {
                    if (str_contains($normalized, $fragment)) {
                        return true;
                    }
                }
            }

            if (in_array($normalizedField, [
                'reservation',
                'reservation_id',
                'cashier_shift',
                'notify_window',
                'hold_status',
                'refund_amount',
                'refund_of_payment_id',
                'payment_type',
                'voucher',
                'waiting_list',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    private static function validatorRepresentsRequestValidation(mixed $validator): bool
    {
        if (! $validator instanceof ValidatorContract) {
            return false;
        }

        if (method_exists($validator, 'getRules') && is_array($validator->getRules()) && $validator->getRules() !== []) {
            return true;
        }

        if (method_exists($validator, 'failed') && is_array($validator->failed()) && $validator->failed() !== []) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, array<int, string>|string>  $errors
     * @return array<string, array<int, string>>
     */
    private static function normalizeErrors(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $field => $messages) {
            $normalized[(string) $field] = array_values(array_map(
                static fn (mixed $message): string => (string) $message,
                is_array($messages) ? $messages : [$messages],
            ));
        }

        return $normalized;
    }
}
