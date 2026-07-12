<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PaymentIntegration;

use App\Enums\PaymentSessionScope;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\VNPayPaymentProviderAdapter;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class VNPayPaymentProviderAdapterTest extends TestCase
{
    private VNPayPaymentProviderAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = app(VNPayPaymentProviderAdapter::class);
    }

    public function test_code_returns_correct_value(): void
    {
        self::assertSame('vnpay', $this->adapter->code());
    }

    public function test_create_session_throws_exception_when_disabled(): void
    {
        config()->set('booking.payment_providers.providers.vnpay.enabled', false);

        $reservation = new Reservation;
        $reservation->reservation_id = 1;

        $this->expectException(ValidationException::class);
        $this->adapter->createSession(PaymentSessionScope::Deposit, $reservation, 1, ['amount' => 10000]);
    }

    public function test_create_session_creates_session_correctly(): void
    {
        config()->set('booking.payment_providers.providers.vnpay.enabled', true);
        config()->set('booking.payment_providers.providers.vnpay.tmn_code', 'TMN123');
        config()->set('booking.payment_providers.providers.vnpay.ipn_url', 'https://pos.test/vnpay-ipn');

        $reservation = new Reservation;
        $reservation->reservation_id = 123;

        $result = $this->adapter->createSession(
            PaymentSessionScope::Deposit,
            $reservation,
            44,
            ['amount' => 50000]
        );

        self::assertSame('vnpay', $result['provider_code']);
        self::assertStringStartsWith('vnp-', $result['provider_session_code']);
        self::assertSame('Pending', $result['session_status']);
        self::assertSame(50000, $result['provider_payload']['amount']);
        self::assertSame('TMN123', $result['provider_payload']['tmn_code']);
        self::assertStringContainsString($result['provider_session_code'], $result['payment_url']);
    }

    public function test_verify_webhook_signature_fails_closed_when_secret_empty(): void
    {
        config()->set('booking.payment_providers.providers.vnpay.hash_secret', '');

        $rawBody = 'vnp_TxnRef=123&vnp_Amount=10000&vnp_SecureHash=hash';
        self::assertFalse($this->adapter->verifyWebhookSignature($rawBody, []));
    }

    public function test_verify_webhook_signature_fails_when_hash_missing(): void
    {
        config()->set('booking.payment_providers.providers.vnpay.hash_secret', 'secret');

        $rawBody = 'vnp_TxnRef=123&vnp_Amount=10000';
        self::assertFalse($this->adapter->verifyWebhookSignature($rawBody, []));
    }

    public function test_verify_webhook_signature_succeeds_with_valid_vnpay_params_query_string(): void
    {
        $secret = 'vnpay_hash_secret_key';
        config()->set('booking.payment_providers.providers.vnpay.hash_secret', $secret);

        // VNPay query params
        $vnpParams = [
            'vnp_Amount' => '5000000',
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => '20260531120000',
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Deposit payment for reservation',
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => 'https://pos.test/return',
            'vnp_TmnCode' => 'TMN123',
            'vnp_TxnRef' => 'vnp-session-abc-123',
            'vnp_Version' => '2.1.0',
        ];

        // Sort parameters by key
        ksort($vnpParams);

        // VNPay spec says hashData must be urlencoded
        // Build hash from urlencoded key=value pairs
        $hashDataParts = [];
        foreach ($vnpParams as $key => $value) {
            $hashDataParts[] = urlencode($key).'='.urlencode((string) $value);
        }
        $hashData = implode('&', $hashDataParts);

        // VNPay uses HMAC-SHA512 over raw hashData
        $secureHash = hash_hmac('sha512', $hashData, $secret);

        // Add signature to parameters
        $vnpParams['vnp_SecureHash'] = $secureHash;

        // Construct raw query string payload (VNPay sends URL-encoded IPN body)
        $rawQueryParts = [];
        foreach ($vnpParams as $key => $value) {
            $rawQueryParts[] = urlencode($key).'='.urlencode($value);
        }
        $rawBody = implode('&', $rawQueryParts);

        // Verify
        self::assertTrue($this->adapter->verifyWebhookSignature($rawBody, []));

        // Test tampered value
        $tamperedBody = str_replace('5000000', '9000000', $rawBody);
        self::assertFalse($this->adapter->verifyWebhookSignature($tamperedBody, []));
    }

    public function test_verify_webhook_signature_succeeds_with_valid_vnpay_params_json(): void
    {
        $secret = 'vnpay_hash_secret_key';
        config()->set('booking.payment_providers.providers.vnpay.hash_secret', $secret);

        $vnpParams = [
            'vnp_Amount' => '5000000',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionNo' => '12345678',
            'vnp_TxnRef' => 'vnp-session-abc-123',
        ];

        ksort($vnpParams);

        ksort($vnpParams);

        // FIX: VNPay spec says hashData must be raw (no URL encoding)
        $hashDataParts = [];
        foreach ($vnpParams as $key => $value) {
            $hashDataParts[] = $key.'='.$value;
        }
        $hashData = implode('&', $hashDataParts);
        $secureHash = hash_hmac('sha512', $hashData, $secret);

        $vnpParams['vnp_SecureHash'] = $secureHash;

        $rawBody = json_encode($vnpParams, JSON_THROW_ON_ERROR);

        self::assertTrue($this->adapter->verifyWebhookSignature($rawBody, []));
    }

    public function test_parse_webhook_resolves_succeeded_status(): void
    {
        $payload = [
            'vnp_Amount' => '5000000',
            'vnp_ResponseCode' => '00',
            'vnp_TransactionNo' => '12345678',
            'vnp_TxnRef' => 'vnp-session-abc-123',
            'vnp_OrderInfo' => 'Deposit payment for reservation',
            'vnp_SecureHash' => 'hash123',
        ];

        $result = $this->adapter->parseWebhook(json_encode($payload, JSON_THROW_ON_ERROR), []);

        self::assertSame('vnpay', $result['provider_code']);
        self::assertSame('12345678', $result['provider_event_code']);
        self::assertSame('vnp-session-abc-123', $result['provider_session_code']);
        self::assertSame('deposit', $result['payment_scope']);
        self::assertSame('Succeeded', $result['session_status']);
        self::assertNull($result['failure_code']);
        self::assertSame('hash123', $result['request_signature']);
    }

    public function test_parse_webhook_resolves_failed_status(): void
    {
        $payload = [
            'vnp_Amount' => '5000000',
            'vnp_ResponseCode' => '24', // VNPay code for User Cancelled
            'vnp_TransactionNo' => '12345678',
            'vnp_TxnRef' => 'vnp-session-abc-123',
            'vnp_OrderInfo' => 'Bill payment for reservation',
        ];

        $result = $this->adapter->parseWebhook(json_encode($payload, JSON_THROW_ON_ERROR), []);

        self::assertSame('Failed', $result['session_status']);
        self::assertSame('24', $result['failure_code']);
        self::assertSame('bill', $result['payment_scope']);
        self::assertStringContainsString('24', $result['failure_message']);
    }

    public function test_parse_webhook_throws_exception_when_txn_ref_missing(): void
    {
        $payload = [
            'vnp_Amount' => '5000000',
            'vnp_ResponseCode' => '00',
        ];

        $this->expectException(ValidationException::class);
        $this->adapter->parseWebhook(json_encode($payload, JSON_THROW_ON_ERROR), []);
    }

    public function test_supports_webhook_event_type(): void
    {
        self::assertTrue($this->adapter->supportsWebhookEventType('payment.session.updated'));
        self::assertTrue($this->adapter->supportsWebhookEventType('payment.session.succeeded'));
        self::assertFalse($this->adapter->supportsWebhookEventType('payment.session.completed'));
    }

    public function test_refresh_and_confirm_normalize_payloads(): void
    {
        $session = new class extends Model
        {
            protected $guarded = [];
        };
        $session->provider_session_code = 'vnp-session-123';
        $session->provider_payment_code = null;
        $session->provider_payload_json = ['amount' => 50000];

        $reservation = new Reservation;
        $reservation->reservation_id = 99;

        // Test refresh (Pending default)
        $refreshResult = $this->adapter->refreshSession(
            PaymentSessionScope::Deposit,
            $reservation,
            $session,
            ['session_status' => 'Pending']
        );

        self::assertSame('vnpay', $refreshResult['provider_code']);
        self::assertSame('Pending', $refreshResult['session_status']);
        self::assertSame('vnp-session-123', $refreshResult['provider_session_code']);
        self::assertSame('refresh', $refreshResult['provider_payload']['mutated_by']);

        // Test confirm (Succeeded default)
        $confirmResult = $this->adapter->confirmSession(
            PaymentSessionScope::Deposit,
            $reservation,
            $session,
            []
        );

        self::assertSame('Succeeded', $confirmResult['session_status']);
        self::assertSame('vnp-mock-payment-code', $confirmResult['provider_payment_code']);
        self::assertSame('confirm', $confirmResult['provider_payload']['mutated_by']);
    }
}
