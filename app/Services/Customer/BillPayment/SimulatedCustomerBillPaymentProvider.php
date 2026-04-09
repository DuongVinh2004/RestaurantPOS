<?php

declare(strict_types=1);

namespace App\Services\Customer\BillPayment;

use App\Enums\PaymentSessionScope;
use App\Models\Reservation;
use App\Models\ReservationBillPaymentSession;
use App\Services\PaymentIntegration\SimulatedPaymentProviderAdapter;

class SimulatedCustomerBillPaymentProvider implements CustomerBillPaymentProvider
{
    public function __construct(
        private readonly SimulatedPaymentProviderAdapter $adapter,
    ) {}

    public function code(): string
    {
        return 'simulated';
    }

    public function createSession(Reservation $reservation, int $customerUserId, array $payload): array
    {
        return $this->adapter->createSession(PaymentSessionScope::Bill, $reservation, $customerUserId, $payload);
    }

    public function refreshSession(Reservation $reservation, ReservationBillPaymentSession $session, array $payload): array
    {
        return $this->adapter->refreshSession(PaymentSessionScope::Bill, $reservation, $session, $payload);
    }

    public function confirmSession(Reservation $reservation, ReservationBillPaymentSession $session, array $payload): array
    {
        return $this->adapter->confirmSession(PaymentSessionScope::Bill, $reservation, $session, $payload);
    }
}
