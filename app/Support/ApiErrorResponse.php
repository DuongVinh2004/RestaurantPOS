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

        $payload = [
            'error_code' => $code,
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
