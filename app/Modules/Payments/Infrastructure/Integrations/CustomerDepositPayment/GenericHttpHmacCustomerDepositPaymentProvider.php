<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Integrations\CustomerDepositPayment;

use App\Enums\PaymentSessionScope;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Payments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\GenericHttpHmacPaymentProviderAdapter;

class GenericHttpHmacCustomerDepositPaymentProvider implements CustomerDepositPaymentProvider
{
    public function __construct(
        private readonly GenericHttpHmacPaymentProviderAdapter $adapter,
    ) {}

    public function code(): string
    {
        return $this->adapter->code();
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
