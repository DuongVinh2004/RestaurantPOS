<?php

declare(strict_types=1);

namespace App\Services\Customer\DepositPayment;

use App\Enums\PaymentSessionScope;
use App\Models\Reservation;
use App\Models\ReservationDepositPaymentSession;
use App\Services\PaymentIntegration\SimulatedPaymentProviderAdapter;

class SimulatedCustomerDepositPaymentProvider implements CustomerDepositPaymentProvider
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
        return $this->adapter->createSession(PaymentSessionScope::Deposit, $reservation, $customerUserId, $payload);
    }

    public function refreshSession(Reservation $reservation, ReservationDepositPaymentSession $session, array $payload): array
    {
        return $this->adapter->refreshSession(PaymentSessionScope::Deposit, $reservation, $session, $payload);
    }

    public function confirmSession(Reservation $reservation, ReservationDepositPaymentSession $session, array $payload): array
    {
        return $this->adapter->confirmSession(PaymentSessionScope::Deposit, $reservation, $session, $payload);
    }
}
