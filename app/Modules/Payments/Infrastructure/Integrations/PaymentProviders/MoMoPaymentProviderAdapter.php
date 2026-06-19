<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Integrations\PaymentProviders;

use App\Enums\PaymentSessionScope;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MoMoPaymentProviderAdapter implements PaymentProviderAdapter
{
    public function code(): string
    {
        return 'momo';
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function createSession(PaymentSessionScope $scope, Reservation $reservation, int $customerUserId, array $payload): array
    {
        $config = $this->providerConfig();
        if (! (bool) ($config['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'provider_code' => ['MoMo provider is disabled.'],
            ]);
        }

        $sessionCode = 'momo-'.Str::uuid()->toString();
        $amount = (int) ($payload['amount'] ?? 0);

        return [
            'provider_code' => $this->code(),
            'provider_session_code' => $sessionCode,
            'provider_payment_code' => null,
            'payment_method' => 'MoMo',
            'session_status' => 'Pending',
            'provider_payload' => [
                'mode' => $config['mode'] ?? 'sandbox',
                'payment_scope' => $scope->value,
                'partner_code' => $config['partner_code'] ?? '',
                'amount' => $amount,
                'payment_url' => ($config['ipn_url'] ?? 'http://localhost').'/checkout/'.$sessionCode,
            ],
            'provider_expires_at' => Carbon::now('UTC')->addMinutes(15),
            'payment_url' => ($config['ipn_url'] ?? 'http://localhost').'/checkout/'.$sessionCode,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function refreshSession(PaymentSessionScope $scope, Reservation $reservation, Model $session, array $payload): array
    {
        return $this->normalizeRefreshOrConfirmPayload($scope, $session, $payload, false);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function confirmSession(PaymentSessionScope $scope, Reservation $reservation, Model $session, array $payload): array
    {
        return $this->normalizeRefreshOrConfirmPayload($scope, $session, $payload, true);
    }

    /**
     * @param  array<string,string>  $headers
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $config = $this->providerConfig();
        $secretKey = trim((string) ($config['secret_key'] ?? ''));
        $accessKey = trim((string) ($config['access_key'] ?? ''));
        if ($secretKey === '' || $accessKey === '') {
            return false;
        }

        $params = $this->parseParams($rawBody);
        $providedSignature = trim((string) ($params['signature'] ?? ''));
        if ($providedSignature === '') {
            return false;
        }

        // Canonical parameters according to MoMo IPN specifications
        $partnerCode = (string) ($params['partnerCode'] ?? '');
        $orderId = (string) ($params['orderId'] ?? '');
        $requestId = (string) ($params['requestId'] ?? '');
        $amount = (string) ($params['amount'] ?? '');
        $orderInfo = (string) ($params['orderInfo'] ?? '');
        $orderType = (string) ($params['orderType'] ?? '');
        $transId = (string) ($params['transId'] ?? '');
        $resultCode = (string) ($params['resultCode'] ?? '');
        $message = (string) ($params['message'] ?? '');
        $payType = (string) ($params['payType'] ?? '');
        $responseTime = (string) ($params['responseTime'] ?? '');
        $extraData = (string) ($params['extraData'] ?? '');

        $canonicalString = "accessKey={$accessKey}"
            ."&amount={$amount}"
            ."&extraData={$extraData}"
            ."&message={$message}"
            ."&orderId={$orderId}"
            ."&orderInfo={$orderInfo}"
            ."&orderType={$orderType}"
            ."&partnerCode={$partnerCode}"
            ."&requestId={$requestId}"
            ."&responseTime={$responseTime}"
            ."&resultCode={$resultCode}"
            ."&transId={$transId}"
            ."&payType={$payType}";

        $expected = hash_hmac('sha256', $canonicalString, $secretKey);

        return hash_equals(strtolower($expected), strtolower($providedSignature));
    }

    /**
     * @param  array<string,string>  $headers
     * @return array<string,mixed>
     */
    public function parseWebhook(string $rawBody, array $headers): array
    {
        $params = $this->parseParams($rawBody);

        $sessionCode = trim((string) ($params['orderId'] ?? ''));
        if ($sessionCode === '') {
            throw ValidationException::withMessages([
                'provider_session_code' => ['Webhook payload must include orderId.'],
            ]);
        }

        $resultCode = (int) ($params['resultCode'] ?? -1);
        $status = $resultCode === 0 ? 'Succeeded' : 'Failed';

        $providerEventCode = trim((string) ($params['requestId'] ?? ''));
        if ($providerEventCode === '') {
            $providerEventCode = 'momo-webhook-'.sha1($rawBody);
        }

        $extraData = trim((string) ($params['extraData'] ?? ''));
        $scope = null;
        if (str_contains(strtolower($extraData), 'deposit') || str_contains(strtolower($params['orderInfo'] ?? ''), 'deposit')) {
            $scope = 'deposit';
        } elseif (str_contains(strtolower($extraData), 'bill') || str_contains(strtolower($params['orderInfo'] ?? ''), 'bill')) {
            $scope = 'bill';
        }

        $providerAmountMinor = isset($params['amount']) ? (int) $params['amount'] : null;

        return [
            'provider_code' => $this->code(),
            'provider_event_code' => $providerEventCode,
            'provider_session_code' => $sessionCode,
            'provider_payment_code' => trim((string) ($params['transId'] ?? '')) ?: null,
            'payment_scope' => $scope,
            'event_type' => 'payment.session.updated',
            'session_status' => $status,
            'provider_amount_minor' => $providerAmountMinor,
            'failure_code' => $status === 'Failed' ? (string) $resultCode : null,
            'failure_message' => $status === 'Failed' ? trim((string) ($params['message'] ?? 'MoMo transaction failed')) : null,
            'provider_payload' => [
                'mode' => $this->providerConfig()['mode'] ?? 'sandbox',
                'raw' => $params,
            ],
            'provider_expires_at' => null,
            'request_signature' => trim((string) ($params['signature'] ?? '')),
            'occurred_at' => Carbon::now('UTC'),
        ];
    }

    public function supportsWebhookEventType(string $eventType): bool
    {
        return in_array(trim($eventType), [
            'payment.session.updated',
            'payment.session.succeeded',
            'payment.session.failed',
        ], true);
    }

    /**
     * @return array<string,mixed>
     */
    private function parseParams(string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $params = [];
        parse_str($rawBody, $params);

        return $params;
    }

    /**
     * @return array<string,mixed>
     */
    private function providerConfig(): array
    {
        return (array) config('booking.payment_providers.providers.momo', []);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalizeRefreshOrConfirmPayload(PaymentSessionScope $scope, Model $session, array $payload, bool $isConfirm): array
    {
        $status = trim((string) ($payload['session_status'] ?? ($isConfirm ? 'Succeeded' : 'Pending')));

        return [
            'provider_code' => $this->code(),
            'provider_session_code' => (string) $session->provider_session_code,
            'provider_payment_code' => $session->provider_payment_code ?? ($status === 'Succeeded' ? 'momo-mock-payment-code' : null),
            'payment_method' => 'MoMo',
            'session_status' => $status,
            'failure_code' => $status === 'Failed' ? 'momo_failed' : null,
            'failure_message' => $status === 'Failed' ? 'MoMo session failed.' : null,
            'provider_payload' => array_merge((array) ($session->provider_payload_json ?? []), [
                'payment_scope' => $scope->value,
                'mutated_by' => $isConfirm ? 'confirm' : 'refresh',
            ]),
            'provider_expires_at' => null,
            'event_type' => 'payment.session.updated',
            'payment_scope' => $scope->value,
        ];
    }
}
