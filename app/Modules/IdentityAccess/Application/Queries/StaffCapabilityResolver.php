<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Queries;

use Illuminate\Http\Request;

class StaffCapabilityResolver
{
    /**
     * @return array{
     *   role_id:int,
     *   role_name:string,
     *   capabilities:list<string>,
     *   source:string,
     *   known_capabilities:list<string>
     * }
     */
    public function resolveForRequest(Request $request): array
    {
        return $this->resolveForActor(
            (int) $request->attributes->get('staff_actor_role_id', 0),
            (string) $request->attributes->get('staff_actor_role_name', '')
        );
    }

    /**
     * @return array{
     *   role_id:int,
     *   role_name:string,
     *   capabilities:list<string>,
     *   source:string,
     *   known_capabilities:list<string>
     * }
     */
    public function resolveForActor(int $roleId = 0, string $roleName = ''): array
    {
        $knownCapabilities = $this->normalizeCapabilities(config('staff_capabilities.known_capabilities', []));
        $capabilities = [];
        $source = 'deny_by_default';

        $roleIdCapabilities = (array) config('staff_capabilities.role_id_capabilities', []);
        if ($roleId > 0 && array_key_exists($roleId, $roleIdCapabilities)) {
            $capabilities = $this->normalizeCapabilities($roleIdCapabilities[$roleId]);
            $source = 'role_id_capabilities';
        } elseif ($roleId > 0 && array_key_exists((string) $roleId, $roleIdCapabilities)) {
            $capabilities = $this->normalizeCapabilities($roleIdCapabilities[(string) $roleId]);
            $source = 'role_id_capabilities';
        } else {
            $normalizedRoleName = mb_strtolower(trim($roleName));
            $roleCapabilities = (array) config('staff_capabilities.role_capabilities', []);
            foreach ($roleCapabilities as $configuredRoleName => $configuredCapabilities) {
                if (mb_strtolower(trim((string) $configuredRoleName)) !== $normalizedRoleName) {
                    continue;
                }

                $capabilities = $this->normalizeCapabilities($configuredCapabilities);
                $source = 'role_capabilities';
                break;
            }
        }

        return [
            'role_id' => max(0, $roleId),
            'role_name' => trim($roleName),
            'capabilities' => $capabilities,
            'source' => $source,
            'known_capabilities' => $knownCapabilities,
        ];
    }

    public function isKnownCapability(string $capability): bool
    {
        $capability = trim($capability);
        if ($capability === '') {
            return false;
        }

        return in_array($capability, $this->normalizeCapabilities(config('staff_capabilities.known_capabilities', [])), true);
    }

    /**
     * @return list<string>
     */
    private function normalizeCapabilities(mixed $values): array
    {
        $aliases = $this->capabilityAliases();
        $normalized = [];

        foreach ((array) $values as $value) {
            $capability = trim((string) $value);
            if ($capability === '') {
                continue;
            }

            $normalized = array_merge($normalized, $this->expandCapability($capability, $aliases));
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, list<string>>  $aliases
     * @return list<string>
     */
    private function expandCapability(string $capability, array $aliases): array
    {
        $expanded = [];
        $queue = [$capability];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (! is_string($current)) {
                continue;
            }

            $current = trim($current);
            if ($current === '' || in_array($current, $expanded, true)) {
                continue;
            }

            $expanded[] = $current;

            foreach ($aliases[$current] ?? [] as $alias) {
                if (! in_array($alias, $expanded, true)) {
                    $queue[] = $alias;
                }
            }
        }

        return $expanded;
    }

    /**
     * @return array<string, list<string>>
     */
    private function capabilityAliases(): array
    {
        $configured = (array) config('staff_capabilities.capability_aliases', []);
        $aliases = [];

        foreach ($configured as $legacy => $canonical) {
            $legacyCapability = trim((string) $legacy);
            if ($legacyCapability === '') {
                continue;
            }

            $canonicalCapabilities = [];
            foreach ((array) $canonical as $candidate) {
                $canonicalCapability = trim((string) $candidate);
                if ($canonicalCapability === '') {
                    continue;
                }

                $canonicalCapabilities[] = $canonicalCapability;
            }

            $canonicalCapabilities = array_values(array_unique($canonicalCapabilities));
            if ($canonicalCapabilities === []) {
                continue;
            }

            sort($canonicalCapabilities);
            $aliases[$legacyCapability] = $canonicalCapabilities;
        }

        ksort($aliases);

        return $aliases;
    }
}
