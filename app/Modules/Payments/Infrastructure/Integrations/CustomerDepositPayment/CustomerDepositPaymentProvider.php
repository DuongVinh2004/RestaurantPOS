<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Integrations\CustomerDepositPayment;

use App\Modules\Payments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\Reservations\Domain\Models\Reservation;

interface CustomerDepositPaymentProvider
{
    public function code(): string;

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function createSession(Reservation $reservation, int $customerUserId, array $payload): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function refreshSession(Reservation $reservation, ReservationDepositPaymentSession $session, array $payload): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function confirmSession(Reservation $reservation, ReservationDepositPaymentSession $session, array $payload): array;
}
