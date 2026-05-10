<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Workflows;

use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tap trung cac rule session-based access cho customer:
 * session nao duoc dung hold nao,
 * va session nao duoc di tu hold/link vao reservation nao.
 */
class ReservationSessionAccessWorkflow
{
    public function authenticatedCustomerCanUseHold(string $holdId, string $sessionId, int $userId): bool
    {
        // Rule ngan gon: hold phai thuoc session hien tai, va neu hold da gan user thi user do phai khop.
        if ($userId <= 0) {
            return false;
        }

        $hold = $this->findOwnedHoldForReservationCreationAuthorization($holdId, $sessionId);
        if (! $hold) {
            return false;
        }

        if ($hold->user_id === null) {
            return true;
        }

        return (int) $hold->user_id === $userId;
    }

    public function extractSessionIdFromRequest(Request $request, ?array $validatedPayload = null): string
    {
        // Session id co the di qua nhieu kenh; uu tien payload da validate roi moi den input/query/header.
        $candidates = [];

        if (is_array($validatedPayload)) {
            $candidates[] = $validatedPayload['session_id'] ?? null;
        }

        $candidates[] = $request->input('session_id');
        $candidates[] = $request->query('session_id');
        $candidates[] = $request->header('X-Session-Id');

        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function resolveUserIdFromOwnedHold(string $holdId, string $sessionId): ?int
    {
        // Dung cho flow nang cap anonymous hold thanh hold cua customer sau khi dang nhap.
        $hold = $this->findOwnedHoldForReservationCreationAuthorization($holdId, $sessionId);

        if (! $hold || $hold->user_id === null) {
            return null;
        }

        return (int) $hold->user_id;
    }

    private function findOwnedHoldForReservationCreationAuthorization(string $holdId, string $sessionId): ?object
    {
        // Truy van toi gian chi lay thong tin "hold nay co thuoc session/user nay khong".
        return DB::table('table_holds')
            ->where('hold_id', $holdId)
            ->where('session_id', $sessionId)
            ->orderByDesc('created_at')
            ->first(['hold_id', 'user_id']);
    }

    public function canAccessReservationBySession(Reservation $reservation, string $sessionId): bool
    {
        // Buoc 1: exact-link access la duong chuan, uu tien vi hold da lien ket truc tiep voi reservation.
        if ($sessionId === '') {
            return false;
        }

        $reservationId = (int) $reservation->reservation_id;
        $reservationUserId = $reservation->user_id !== null ? (int) $reservation->user_id : null;

        $linkedHoldQuery = DB::table('table_holds')
            ->where('session_id', $sessionId)
            ->where('confirmed_reservation_id', $reservationId);

        $this->applyReservationUserMatch($linkedHoldQuery, $reservationUserId);

        // Exact-link hop le trong cua so thoi gian can reservation de tranh session cu truy cap mai mai.
        if ($this->isWithinExactLinkAccessWindow($reservation) && $linkedHoldQuery->exists()) {
            return true;
        }

        // Buoc 2: legacy fallback chi bat khi config cho phep, de bao toan compatibility voi flow cu.
        if (! $this->isLegacyFallbackEnabled()) {
            return false;
        }

        [$windowStart, $windowEnd] = $this->resolveLegacyAccessWindow();
        $now = Carbon::now('UTC');

        // Lay bo table cua reservation de ve sau so tung hold candidate theo dung tap ban da dat.
        $reservationTableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($reservationTableIds === []) {
            return false;
        }

        // Tim hold candidate cua cung session, cung slot, va van con hop le trong cua so fallback.
        $candidateHoldQuery = DB::table('table_holds')
            ->where('session_id', $sessionId);

        $this->applyReservationUserMatch($candidateHoldQuery, $reservationUserId);

        $candidateHoldIds = $candidateHoldQuery
            ->where(function ($query) use ($now) {
                $query->where(function ($subQuery) use ($now) {
                    $subQuery->whereIn('hold_status', ['Holding', 'Pending'])
                        ->where('expire_at', '>', $now)
                        ->whereNull('confirmed_reservation_id');
                })->orWhere(function ($subQuery) {
                    $subQuery->where('hold_status', 'Confirmed')
                        ->whereNull('confirmed_reservation_id');
                });
            })
            ->where('start_time', '=', $reservation->start_time)
            ->where('end_time', '=', $reservation->end_time)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->orderByDesc('created_at')
            ->pluck('hold_id')
            ->map(static fn ($id) => (string) $id)
            ->all();

        if ($candidateHoldIds === []) {
            return false;
        }

        foreach ($candidateHoldIds as $holdId) {
            // Match cuoi cung duoc xac nhan bang tap table, tranh nham giua hai hold cung khung gio.
            $holdTableIds = DB::table('table_hold_details')
                ->where('hold_id', $holdId)
                ->orderBy('table_id')
                ->pluck('table_id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            if ($holdTableIds === $reservationTableIds) {
                return true;
            }
        }

        return false;
    }

    private function isWithinExactLinkAccessWindow(Reservation $reservation): bool
    {
        // Exact-link van co cua so truy cap ro rang de giam rui ro token/session cu.
        [$windowStart, $windowEnd] = $this->resolveExactLinkAccessWindow($reservation);
        $now = Carbon::now('UTC');

        return ! $now->lt($windowStart) && ! $now->gt($windowEnd);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveExactLinkAccessWindow(Reservation $reservation): array
    {
        $windowHours = max(1, (int) config('booking.customer_session_exact_link_access_hours', 24));

        $windowStart = Carbon::parse((string) $reservation->start_time)->utc()->subHours($windowHours);
        $windowEnd = Carbon::parse((string) $reservation->end_time)->utc()->addHours($windowHours);

        return [$windowStart, $windowEnd];
    }

    private function applyReservationUserMatch($query, ?int $reservationUserId): void
    {
        if ($reservationUserId === null) {
            return;
        }

        // Reservation co user thi chi chap nhan hold vo danh hoac hold cua dung user do.
        $query->where(function ($innerQuery) use ($reservationUserId): void {
            $innerQuery->whereNull('user_id')
                ->orWhere('user_id', $reservationUserId);
        });
    }

    private function isLegacyFallbackEnabled(): bool
    {
        return (int) config('booking.customer_session_legacy_access_hours', 0) > 0;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveLegacyAccessWindow(): array
    {
        $windowHours = max(1, (int) config('booking.customer_session_legacy_access_hours', 0));
        $now = Carbon::now('UTC');

        $windowStart = $now->copy()->subHours($windowHours);
        $windowEnd = $now;

        return [$windowStart, $windowEnd];
    }
}
