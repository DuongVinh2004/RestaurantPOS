<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class ApiValidationErrorClassifier
{
    /**
     * @return array{
     *   status:int,
     *   error_code:string,
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

        return null;
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
