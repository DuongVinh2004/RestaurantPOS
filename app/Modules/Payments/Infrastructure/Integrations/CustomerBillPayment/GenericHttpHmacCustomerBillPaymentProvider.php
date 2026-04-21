<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Integrations\CustomerBillPayment;

use App\Enums\PaymentSessionScope;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Payments\Domain\Models\ReservationBillPaymentSession;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\GenericHttpHmacPaymentProviderAdapter;

class GenericHttpHmacCustomerBillPaymentProvider implements CustomerBillPaymentProvider
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
