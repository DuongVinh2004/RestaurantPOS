<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiErrorResponse
{
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

        return response()->json($payload, $status)->withHeaders([
            'X-Request-Id' => $requestId,
        ]);
    }
}
