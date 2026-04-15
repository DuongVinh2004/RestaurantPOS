<?php

namespace App\Modules\Reservations\Application\Services;

use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class ReservationCodeGenerator
{
    public function generate(Carbon $startTimeUtc): string
    {
        $prefix = (string) config('booking.reservation_code_prefix', 'RSV');
        $randLen = max(4, (int) config('booking.reservation_code_random_len', 6));
        $attempts = max(5, (int) config('booking.reservation_code_max_attempts', 12));

        $datePart = $startTimeUtc->copy()->utc()->format('ymd'); // YYMMDD

        for ($i = 0; $i < $attempts; $i++) {
            $random = strtoupper(Str::random($randLen));
            $code = "{$prefix}-{$datePart}-{$random}";

            $exists = Reservation::query()
                ->where('reservation_code', $code)
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        throw new RuntimeException('Cannot generate unique reservation_code after retries.');
    }
}
