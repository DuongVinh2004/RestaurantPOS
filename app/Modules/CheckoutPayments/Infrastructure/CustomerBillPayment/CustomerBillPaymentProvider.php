<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Infrastructure\CustomerBillPayment;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\CheckoutPayments\Domain\Models\ReservationBillPaymentSession;

interface CustomerBillPaymentProvider
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
    public function refreshSession(Reservation $reservation, ReservationBillPaymentSession $session, array $payload): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function confirmSession(Reservation $reservation, ReservationBillPaymentSession $session, array $payload): array;
}
