<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Infrastructure\CustomerDepositPayment;

use App\Enums\PaymentSessionScope;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\CheckoutPayments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\CheckoutPayments\Infrastructure\PaymentProviders\SimulatedPaymentProviderAdapter;

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
