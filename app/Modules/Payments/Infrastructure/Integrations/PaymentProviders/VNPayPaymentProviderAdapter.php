<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Integrations\PaymentProviders;

use App\Enums\PaymentSessionScope;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VNPayPaymentProviderAdapter implements PaymentProviderAdapter
{
    public function code(): string
    {
        return 'vnpay';
    }

    public function createSession(PaymentSessionScope $scope, Reservation $reservation, int $customerUserId, array $payload): array
    {
        $config = $this->providerConfig();
        if (! (bool) ($config['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'provider_code' => ['VNPay provider is disabled.'],
            ]);
        }

        $sessionCode = 'vnp-'.time().rand(1000, 9999);
        $amount = (int) ($payload['amount'] ?? 0);
        $tmnCode = trim((string) ($config['tmn_code'] ?? ''));
        $hashSecret = trim((string) ($config['hash_secret'] ?? ''));
        $returnUrl = trim((string) ($config['return_url'] ?? 'http://127.0.0.1:3000'));

        $vnpUrl = $config['mode'] === 'live'
            ? 'https://pay.vnpayment.vn/vpcpay.html'
            : 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';

        $ipnUrl = trim((string) ($config['ipn_url'] ?? ''));

        $vnpParams = [
            'vnp_Version' => '2.1.0',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => $amount * 100, // VNPay amount is multiplied by 100
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => Carbon::now('UTC')->setTimezone('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_ExpireDate' => Carbon::now('UTC')->addMinutes(15)->setTimezone('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => request()->ip() === '::1' ? '127.0.0.1' : (request()->ip() ?? '127.0.0.1'),
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => $scope === PaymentSessionScope::Deposit ? 'DepositPayment' : 'BillPayment',
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_TxnRef' => $sessionCode,
        ];

        // Include IPN URL in params if configured (lets VNPay know where to send async callbacks)
        if ($ipnUrl !== '') {
            $vnpParams['vnp_IpnUrl'] = $ipnUrl;
        }

        ksort($vnpParams);

        // hashData and queryString are identical according to VNPay v2.1.0 specification
        // BOTH must be urlencoded
        $queryParts = [];
        foreach ($vnpParams as $key => $value) {
            $queryParts[] = urlencode($key).'='.urlencode((string) $value);
        }

        $queryString = implode('&', $queryParts);
        $hashData = implode('&', $queryParts);

        if ($hashSecret !== '') {
            $vnpSecureHash = hash_hmac('sha512', $hashData, $hashSecret);
            $vnpUrl .= '?'.$queryString.'&vnp_SecureHash='.$vnpSecureHash;
        } else {
            $vnpUrl .= '?'.$queryString;
        }

        return [
            'provider_code' => $this->code(),
            'provider_session_code' => $sessionCode,
            'provider_payment_code' => null,
            'payment_method' => 'VNPay',
            'session_status' => 'Pending',
            'provider_payload' => [
                'mode' => $config['mode'] ?? 'sandbox',
                'payment_scope' => $scope->value,
                'tmn_code' => $tmnCode,
                'amount' => $amount,
                'payment_url' => $vnpUrl,
            ],
            'provider_expires_at' => Carbon::now('UTC')->addMinutes(15),
            'payment_url' => $vnpUrl,
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
        $secret = trim((string) ($config['hash_secret'] ?? ''));
        if ($secret === '') {
            return false;
        }

        $params = $this->parseParams($rawBody);
        $secureHash = trim((string) ($params['vnp_SecureHash'] ?? ''));
        if ($secureHash === '') {
            return false;
        }

        // Filter and sort parameters starting with vnp_ (excluding vnp_SecureHash)
        $vnpParams = [];
        foreach ($params as $key => $value) {
            if (str_starts_with($key, 'vnp_') && $key !== 'vnp_SecureHash' && $key !== 'vnp_SecureHashType') {
                $valueStr = trim((string) $value);
                if ($valueStr !== '') {
                    $vnpParams[$key] = $valueStr;
                }
            }
        }

        ksort($vnpParams);

        $hashDataParts = [];
        foreach ($vnpParams as $key => $value) {
            $hashDataParts[] = urlencode($key).'='.urlencode((string) $value);
        }
        $hashData = implode('&', $hashDataParts);

        $expected = hash_hmac('sha512', $hashData, $secret);

        return hash_equals(strtolower($expected), strtolower($secureHash));
    }

    /**
     * @param  array<string,string>  $headers
     * @return array<string,mixed>
     */
    public function parseWebhook(string $rawBody, array $headers): array
    {
        $params = $this->parseParams($rawBody);

        $sessionCode = trim((string) ($params['vnp_TxnRef'] ?? ''));
        if ($sessionCode === '') {
            throw ValidationException::withMessages([
                'provider_session_code' => ['Webhook payload must include vnp_TxnRef.'],
            ]);
        }

        $responseCode = trim((string) ($params['vnp_ResponseCode'] ?? ''));
        $status = $responseCode === '00' ? 'Succeeded' : 'Failed';

        $providerEventCode = trim((string) ($params['vnp_TransactionNo'] ?? ''));
        if ($providerEventCode === '') {
            $providerEventCode = 'vnpay-webhook-'.sha1($rawBody);
        }

        $orderInfo = trim((string) ($params['vnp_OrderInfo'] ?? ''));
        $scope = null;
        if (str_contains(strtolower($orderInfo), 'deposit')) {
            $scope = 'deposit';
        } elseif (str_contains(strtolower($orderInfo), 'bill')) {
            $scope = 'bill';
        }

        $providerAmountMinor = isset($params['vnp_Amount']) ? ((int) $params['vnp_Amount']) / 100 : null;

        return [
            'provider_code' => $this->code(),
            'provider_event_code' => $providerEventCode,
            'provider_session_code' => $sessionCode,
            'provider_payment_code' => $providerEventCode !== '' ? $providerEventCode : null,
            'payment_scope' => $scope,
            'event_type' => 'payment.session.updated',
            'session_status' => $status,
            'provider_amount_minor' => $providerAmountMinor,
            'failure_code' => $status === 'Failed' ? $responseCode : null,
            'failure_message' => $status === 'Failed' ? 'VNPay transaction failed with response code '.$responseCode : null,
            'provider_payload' => [
                'mode' => $this->providerConfig()['mode'] ?? 'sandbox',
                'raw' => $params,
            ],
            'provider_expires_at' => null,
            'request_signature' => trim((string) ($params['vnp_SecureHash'] ?? '')),
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
        return (array) config('booking.payment_providers.providers.vnpay', []);
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
            'provider_payment_code' => $session->provider_payment_code ?? ($status === 'Succeeded' ? 'vnp-mock-payment-code' : null),
            'payment_method' => 'VNPay',
            'session_status' => $status,
            'failure_code' => $status === 'Failed' ? 'vnp_failed' : null,
            'failure_message' => $status === 'Failed' ? 'VNPay session failed.' : null,
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
