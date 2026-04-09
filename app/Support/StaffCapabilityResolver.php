<?php

declare(strict_types=1);

namespace App\Support;

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
     * @param mixed $values
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

            $normalized[] = $capability;

            if (array_key_exists($capability, $aliases)) {
                $normalized[] = $aliases[$capability];
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @return array<string,string>
     */
    private function capabilityAliases(): array
    {
        $configured = (array) config('staff_capabilities.capability_aliases', []);
        $aliases = [];

        foreach ($configured as $legacy => $canonical) {
            $legacyCapability = trim((string) $legacy);
            $canonicalCapability = trim((string) $canonical);

            if ($legacyCapability === '' || $canonicalCapability === '') {
                continue;
            }

            $aliases[$legacyCapability] = $canonicalCapability;
        }

        return $aliases;
    }
}
