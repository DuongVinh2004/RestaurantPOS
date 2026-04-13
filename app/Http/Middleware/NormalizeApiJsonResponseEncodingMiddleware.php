<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiPayloadEncodingNormalizer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class NormalizeApiJsonResponseEncodingMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if (! $request->is('api/*') || ! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);
        $normalized = ApiPayloadEncodingNormalizer::normalize($payload);

        if ($normalized['replacement_count'] < 1) {
            return $response;
        }

        $response->setData($normalized['value']);

        Log::warning('api_json_response_encoding_repaired', [
            'request_id' => $request->attributes->get('request_id'),
            'path' => '/'.$request->path(),
            'replacement_count' => $normalized['replacement_count'],
        ]);

        return $response;
    }
}
