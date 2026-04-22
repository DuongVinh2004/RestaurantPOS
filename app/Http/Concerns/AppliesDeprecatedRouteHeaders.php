<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait AppliesDeprecatedRouteHeaders
{
    protected function markDeprecatedRouteAlias(
        JsonResponse $response,
        string $legacyAlias,
        string $canonicalRoute,
        ?string $message = null,
    ): JsonResponse {
        $warningMessage = $message
            ?? sprintf('Deprecated API alias "%s" is in use. Use "%s" instead.', $legacyAlias, $canonicalRoute);

        return $response->withHeaders([
            'Deprecation' => 'true',
            'Sunset' => 'Wed, 01 Jul 2026 00:00:00 GMT',
            'X-Deprecated-Route-Alias' => $legacyAlias,
            'X-Canonical-Route' => $canonicalRoute,
            'Warning' => sprintf('299 - "%s"', addslashes($warningMessage)),
            'Link' => sprintf('<%s>; rel="successor-version"', $canonicalRoute),
        ]);
    }

    protected function markDeprecatedRouteAliasForRequest(
        Request $request,
        JsonResponse $response,
        string $legacyAlias,
        string $canonicalRouteTemplate,
        ?string $message = null,
    ): JsonResponse {
        return $this->markDeprecatedRouteAlias(
            $response,
            $legacyAlias,
            $this->resolveCanonicalRouteForRequest($request, $canonicalRouteTemplate),
            $message,
        );
    }

    protected function resolveCanonicalRouteForRequest(Request $request, string $canonicalRouteTemplate): string
    {
        $route = $request->route();
        if (! $route) {
            return $canonicalRouteTemplate;
        }

        $resolved = $canonicalRouteTemplate;
        foreach ((array) $route->parameters() as $key => $value) {
            if (is_object($value)) {
                if (method_exists($value, 'getRouteKey')) {
                    $value = $value->getRouteKey();
                } elseif (isset($value->id)) {
                    $value = $value->id;
                } else {
                    $value = (string) $value;
                }
            }

            if (is_scalar($value)) {
                $resolved = str_replace('{'.(string) $key.'}', (string) $value, $resolved);
            }
        }

        return $resolved;
    }
}
