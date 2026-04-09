<?php

declare(strict_types=1);

namespace App\Services\Customer\BillPayment;

use App\Models\Reservation;
use App\Models\ReservationBillPaymentSession;

interface CustomerBillPaymentProvider
{
    public function code(): string;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createSession(Reservation $reservation, int $customerUserId, array $payload): array;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function refreshSession(Reservation $reservation, ReservationBillPaymentSession $session, array $payload): array;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function confirmSession(Reservation $reservation, ReservationBillPaymentSession $session, array $payload): array;
}
