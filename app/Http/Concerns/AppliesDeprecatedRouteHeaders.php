<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        $this->recordDeprecatedRouteAliasUsage($legacyAlias, $canonicalRoute);

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

    private function recordDeprecatedRouteAliasUsage(string $legacyAlias, string $canonicalRoute): void
    {
        $aliasKey = null;
        foreach ((array) config('booking.api_alias_deprecations.routes', []) as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $configuredAlias = $this->routePathFromSignature((string) ($definition['deprecated_alias'] ?? ''));
            if ($configuredAlias !== $this->routePathFromSignature($legacyAlias)) {
                continue;
            }

            $aliasKey = (string) ($definition['key'] ?? '');
            break;
        }

        Log::channel('audit')->info(
            (string) config('booking.api_alias_deprecations.audit_log_event', 'api_deprecated_alias_used'),
            [
                'alias_key' => $aliasKey !== '' ? $aliasKey : null,
                'deprecated_alias' => $legacyAlias,
                'canonical_route' => $canonicalRoute,
                'removal_evidence' => 'zero_hits_for_configured_observation_release_cycle',
            ],
        );
    }

    private function routePathFromSignature(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $value);
        if (! is_array($parts)) {
            return '/'.trim($value, '/');
        }

        $path = count($parts) > 1 ? (string) $parts[count($parts) - 1] : $value;

        return '/'.trim($path, '/');
    }
}
