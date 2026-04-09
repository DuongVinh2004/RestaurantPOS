<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TableHoldStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

final class HoldConflictScope
{
    public static function apply(Builder $query, string $holdAlias = 'th', ?Carbon $now = null): Builder
    {
        $now ??= Carbon::now('UTC');

        $holdStatus = $holdAlias . '.hold_status';
        $expireAt = $holdAlias . '.expire_at';

        return $query->where(function (Builder $subQuery) use ($holdStatus, $expireAt, $now) {
            $subQuery->where(function (Builder $activePending) use ($holdStatus, $expireAt, $now) {
                $activePending
                    ->whereIn($holdStatus, [
                        TableHoldStatus::Holding->value,
                        TableHoldStatus::Pending->value,
                    ])
                    ->where($expireAt, '>', $now);
            })->orWhere($holdStatus, TableHoldStatus::Confirmed->value);
        });
    }
}
