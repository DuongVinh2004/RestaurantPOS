<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Domain\Models\NotificationOutbox;
use App\Modules\Notifications\Infrastructure\NotificationDeliveryResult;

/**
 * Fault-injection/observability seam around the external delivery side effect.
 *
 * Production uses the no-op implementation. Focused crash drills replace this
 * service so they can terminate the worker at exact boundary points.
 */
class NotificationDeliveryBoundaryHook
{
    /** @param array<string,mixed> $dispatchPayload */
    public function beforeProviderSideEffect(NotificationOutbox $message, array $dispatchPayload): void
    {
    }

    public function afterProviderAcceptance(NotificationOutbox $message, NotificationDeliveryResult $result): void
    {
    }
}
