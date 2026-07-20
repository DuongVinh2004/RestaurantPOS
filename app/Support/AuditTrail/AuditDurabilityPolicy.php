<?php

declare(strict_types=1);

namespace App\Support\AuditTrail;

use Illuminate\Support\Str;

final class AuditDurabilityPolicy
{
    /**
     * @param  array<string,mixed>|null  $structured
     */
    public function isCritical(string $eventName, ?array $structured): bool
    {
        if (($structured['durability'] ?? null) === 'critical') {
            return true;
        }

        if ($this->matches($eventName, (array) config('audit.critical_event_patterns', []))) {
            return true;
        }

        $action = is_scalar($structured['action'] ?? null) ? trim((string) $structured['action']) : '';

        return $action !== ''
            && $this->matches($action, (array) config('audit.critical_action_patterns', []));
    }

    /**
     * @param  array<mixed>  $patterns
     */
    private function matches(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && Str::is($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
