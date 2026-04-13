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

        $holdStatus = $holdAlias.'.hold_status';
        $expireAt = $holdAlias.'.expire_at';

        // Confirmed holds are a linkage artifact after reservation creation.
        // Runtime conflict checks must follow the reservation state and table assignment
        // instead of the original hold row, otherwise stale confirmed holds can keep
        // blocking tables after move/reschedule/cancel flows.
        return $query->where(function (Builder $subQuery) use ($holdStatus, $expireAt, $now) {
            $subQuery
                ->whereIn($holdStatus, [
                    TableHoldStatus::Holding->value,
                    TableHoldStatus::Pending->value,
                ])
                ->where($expireAt, '>', $now);
        });
    }
}
