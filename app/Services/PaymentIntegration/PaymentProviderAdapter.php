<?php

declare(strict_types=1);

namespace App\Services\PaymentIntegration;

use App\Enums\PaymentSessionScope;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Model;

interface PaymentProviderAdapter
{
    public function code(): string;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createSession(PaymentSessionScope $scope, Reservation $reservation, int $customerUserId, array $payload): array;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function refreshSession(PaymentSessionScope $scope, Reservation $reservation, Model $session, array $payload): array;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function confirmSession(PaymentSessionScope $scope, Reservation $reservation, Model $session, array $payload): array;

    /**
     * @param array<string,string> $headers
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool;

    /**
     * @param array<string,string> $headers
     * @return array<string,mixed>
     */
    public function parseWebhook(string $rawBody, array $headers): array;

    public function supportsWebhookEventType(string $eventType): bool;
}
