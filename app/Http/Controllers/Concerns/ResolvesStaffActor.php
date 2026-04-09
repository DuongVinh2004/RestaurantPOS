<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

trait ResolvesStaffActor
{
    protected function resolveStaffActorUserId(Request $request): int
    {
        $resolved = $request->attributes->get('staff_actor_user_id');
        $resolvedId = $resolved !== null ? (int) $resolved : null;
        $payloadValue = $request->input('staff_user_id');
        $payloadId = $payloadValue !== null && $payloadValue != '' ? (int) $payloadValue : null;

        if ($resolvedId === null || $resolvedId <= 0) {
            throw new UnauthorizedHttpException('Staff-Api-Key', 'Authenticated staff actor is required.');
        }

        if ($payloadId !== null && $payloadId !== $resolvedId) {
            throw ValidationException::withMessages([
                'staff_user_id' => ['staff_user_id must match the authenticated staff actor.'],
            ]);
        }

        return $resolvedId;
    }
}
