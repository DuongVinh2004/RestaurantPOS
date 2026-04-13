<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiErrorResponse
{
    /**
     * @var list<string>
     */
    private const OPTIONAL_STRING_KEYS = [
        'conflict_type',
        'replay_state',
        'state_reason',
        'required_capability',
        'staff_role_name',
    ];

    /**
     * @var list<string>
     */
    private const OPTIONAL_STRING_LIST_KEYS = [
        'warnings',
        'next_actions',
        'deprecated_aliases',
    ];

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $extra
     */
    public static function json(
        Request $request,
        int $status,
        string $code,
        string $message,
        array $details = [],
        bool $legacyErrorAlias = false,
        array $extra = [],
    ): JsonResponse {
        $requestId = trim((string) ($request->attributes->get('request_id') ?? ''));
        $categoryCode = trim((string) ($extra['category_code'] ?? $code));
        $categoryCode = $categoryCode !== '' ? $categoryCode : $code;
        unset($extra['category_code']);

        $payload = [
            'error_code' => $code,
            'category_code' => $categoryCode,
            'message' => $message,
            'request_id' => $requestId !== '' ? $requestId : null,
        ];

        if ($legacyErrorAlias) {
            $payload['error'] = $code;
        }

        if (isset($details['errors']) && is_array($details['errors'])) {
            $payload['errors'] = $details['errors'];
        }

        if ($details !== []) {
            $payload['details'] = $details;
        }

        $payload = array_replace($payload, $extra);
        $payload = self::applyLegacyAliasMetadata($payload, $legacyErrorAlias);
        $payload = self::normalizeOptionalMetadata($payload);

        return response()->json($payload, $status)->withHeaders([
            'X-Request-Id' => $requestId,
        ]);
    }

    /**
     * @param  array<string, array<int, string>|string>  $errors
     * @param  array<string, mixed>  $extra
     */
    public static function validation(
        Request $request,
        array $errors,
        string $message = 'Validation error.',
        array $extra = [],
    ): JsonResponse {
        return self::json(
            $request,
            422,
            'validation_error',
            $message,
            ['errors' => $errors],
            extra: ['category_code' => ApiErrorCategory::VALIDATION_ERROR] + $extra,
        );
    }

    /**
     * @param  array<string, array<int, string>|string>  $errors
     * @param  array<string, mixed>  $extra
     */
    public static function domainInvariantViolation(
        Request $request,
        array $errors,
        string $message = 'The requested action violates a business rule.',
        array $extra = [],
    ): JsonResponse {
        return self::json(
            $request,
            422,
            'validation_error',
            $message,
            ['errors' => $errors],
            extra: ['category_code' => ApiErrorCategory::DOMAIN_INVARIANT_VIOLATION] + $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function authenticationRequired(
        Request $request,
        string $message = 'Authentication is required.',
        array $extra = [],
    ): JsonResponse {
        $normalizedMessage = trim($message);
        if ($normalizedMessage === '' || $normalizedMessage === 'Unauthorized.') {
            $normalizedMessage = 'Authentication is required.';
        }

        return self::json(
            $request,
            401,
            'unauthorized',
            $normalizedMessage,
            extra: ['category_code' => ApiErrorCategory::AUTHENTICATION_REQUIRED] + $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function forbiddenCapability(
        Request $request,
        string $message = 'Forbidden.',
        ?string $requiredCapability = null,
        ?string $staffRoleName = null,
        array $extra = [],
    ): JsonResponse {
        return self::json(
            $request,
            403,
            'forbidden',
            $message,
            extra: [
                'category_code' => ApiErrorCategory::FORBIDDEN_CAPABILITY,
                'required_capability' => $requiredCapability,
                'staff_role_name' => $staffRoleName,
            ] + $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function ownerScopeDenied(
        Request $request,
        string $message = 'The authenticated actor is outside the required owner scope.',
        array $extra = [],
    ): JsonResponse {
        return self::json(
            $request,
            403,
            'forbidden',
            $message,
            extra: ['category_code' => ApiErrorCategory::OWNER_SCOPE_DENIED] + $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function policyDenied(
        Request $request,
        string $message = 'Access to this API operation is denied by policy.',
        array $extra = [],
    ): JsonResponse {
        return self::json(
            $request,
            403,
            'forbidden',
            $message,
            extra: ['category_code' => ApiErrorCategory::POLICY_DENIED] + $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function notFound(
        Request $request,
        string $message = 'Resource not found.',
        array $extra = [],
    ): JsonResponse {
        return self::json(
            $request,
            404,
            'not_found',
            $message,
            extra: ['category_code' => ApiErrorCategory::NOT_FOUND] + $extra,
        );
    }

    /**
     * @param  array<string, array<int, string>|string>  $errors
     * @param  array<string, mixed>  $extra
     */
    public static function staleWrite(
        Request $request,
        array $errors = [],
        string $message = 'The resource was modified by another writer. Reload data and try again.',
        array $extra = [],
    ): JsonResponse {
        $details = $errors !== [] ? ['errors' => $errors] : [];

        return self::json(
            $request,
            409,
            'stale_row_version',
            $message,
            $details,
            extra: [
                'category_code' => ApiErrorCategory::STALE_WRITE,
                'conflict_type' => 'stale_write',
                'state_reason' => $extra['state_reason'] ?? 'row_version_mismatch',
                'next_actions' => $extra['next_actions'] ?? [
                    'reload_resource',
                    'retry_with_latest_row_version',
                ],
            ] + $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $extra
     */
    public static function resourceConflict(
        Request $request,
        string $message = 'The record changed or conflicts with the current state.',
        array $details = [],
        array $extra = [],
        bool $legacyErrorAlias = false,
    ): JsonResponse {
        return self::json(
            $request,
            409,
            'conflict',
            $message,
            $details,
            legacyErrorAlias: $legacyErrorAlias,
            extra: ['category_code' => ApiErrorCategory::RESOURCE_CONFLICT] + $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function idempotencyConflict(
        Request $request,
        string $message,
        array $extra = [],
        bool $legacyErrorAlias = true,
    ): JsonResponse {
        $errorCode = trim((string) ($extra['error_code'] ?? 'idempotency_conflict'));
        unset($extra['error_code']);

        return self::json(
            $request,
            409,
            $errorCode !== '' ? $errorCode : 'idempotency_conflict',
            $message,
            legacyErrorAlias: $legacyErrorAlias,
            extra: ['category_code' => ApiErrorCategory::IDEMPOTENCY_CONFLICT] + $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function internalError(
        Request $request,
        string $message = 'Internal server error.',
        array $extra = [],
    ): JsonResponse {
        return self::json(
            $request,
            500,
            'internal_error',
            $message,
            extra: ['category_code' => ApiErrorCategory::INTERNAL_ERROR] + $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function applyLegacyAliasMetadata(array $payload, bool $legacyErrorAlias): array
    {
        if (! $legacyErrorAlias) {
            return $payload;
        }

        $payload['warnings'] = self::mergeStringLists($payload['warnings'] ?? [], [
            'legacy_error_alias_deprecated',
        ]);
        $payload['deprecated_aliases'] = self::mergeStringLists($payload['deprecated_aliases'] ?? [], [
            'error',
        ]);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function normalizeOptionalMetadata(array $payload): array
    {
        foreach (self::OPTIONAL_STRING_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = self::nullableString($payload[$key]);
            if ($value === null) {
                unset($payload[$key]);

                continue;
            }

            $payload[$key] = $value;
        }

        foreach (self::OPTIONAL_STRING_LIST_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $values = self::normalizeStringList($payload[$key]);
            if ($values === []) {
                unset($payload[$key]);

                continue;
            }

            $payload[$key] = $values;
        }

        return $payload;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return list<string>
     */
    private static function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            $string = trim((string) $item);
            if ($string === '') {
                continue;
            }

            $normalized[] = $string;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private static function mergeStringLists(mixed $existing, array $items): array
    {
        return self::normalizeStringList(array_merge(
            self::normalizeStringList($existing),
            $items,
        ));
    }
}
