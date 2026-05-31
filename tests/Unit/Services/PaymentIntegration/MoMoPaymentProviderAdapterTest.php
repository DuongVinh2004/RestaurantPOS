<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PaymentIntegration;

use App\Enums\PaymentSessionScope;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\MoMoPaymentProviderAdapter;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class MoMoPaymentProviderAdapterTest extends TestCase
{
    private MoMoPaymentProviderAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = app(MoMoPaymentProviderAdapter::class);
    }

    public function test_code_returns_correct_value(): void
    {
        self::assertSame('momo', $this->adapter->code());
    }

    public function test_create_session_throws_exception_when_disabled(): void
    {
        config()->set('booking.payment_providers.providers.momo.enabled', false);

        $reservation = new Reservation;
        $reservation->reservation_id = 1;

        $this->expectException(ValidationException::class);
        $this->adapter->createSession(PaymentSessionScope::Deposit, $reservation, 1, ['amount' => 10000]);
    }

    public function test_create_session_creates_session_correctly(): void
    {
        config()->set('booking.payment_providers.providers.momo.enabled', true);
        config()->set('booking.payment_providers.providers.momo.partner_code', 'MOMO123');
        config()->set('booking.payment_providers.providers.momo.ipn_url', 'https://pos.test/momo-ipn');

        $reservation = new Reservation;
        $reservation->reservation_id = 123;

        $result = $this->adapter->createSession(
            PaymentSessionScope::Deposit,
            $reservation,
            44,
            ['amount' => 50000]
        );

        self::assertSame('momo', $result['provider_code']);
        self::assertStringStartsWith('momo-', $result['provider_session_code']);
        self::assertSame('Pending', $result['session_status']);
        self::assertSame(50000, $result['provider_payload']['amount']);
        self::assertSame('MOMO123', $result['provider_payload']['partner_code']);
        self::assertStringContainsString($result['provider_session_code'], $result['payment_url']);
    }

    public function test_verify_webhook_signature_fails_closed_when_keys_missing(): void
    {
        config()->set('booking.payment_providers.providers.momo.secret_key', '');
        config()->set('booking.payment_providers.providers.momo.access_key', 'access');

        $rawBody = json_encode([
            'partnerCode' => 'momo',
            'orderId' => '123',
            'signature' => 'sig',
        ]);
        self::assertFalse($this->adapter->verifyWebhookSignature($rawBody, []));

        config()->set('booking.payment_providers.providers.momo.secret_key', 'secret');
        config()->set('booking.payment_providers.providers.momo.access_key', '');
        self::assertFalse($this->adapter->verifyWebhookSignature($rawBody, []));
    }

    public function test_verify_webhook_signature_fails_when_signature_missing(): void
    {
        config()->set('booking.payment_providers.providers.momo.secret_key', 'secret');
        config()->set('booking.payment_providers.providers.momo.access_key', 'access');

        $rawBody = json_encode([
            'partnerCode' => 'momo',
            'orderId' => '123',
        ]);
        self::assertFalse($this->adapter->verifyWebhookSignature($rawBody, []));
    }

    public function test_verify_webhook_signature_succeeds_with_valid_momo_params(): void
    {
        $secretKey = 'momo_secret_key';
        $accessKey = 'momo_access_key';
        config()->set('booking.payment_providers.providers.momo.secret_key', $secretKey);
        config()->set('booking.payment_providers.providers.momo.access_key', $accessKey);

        $params = [
            'partnerCode' => 'MOMO123',
            'orderId' => 'momo-session-abc-123',
            'requestId' => 'request-456',
            'amount' => '150000',
            'orderInfo' => 'Deposit payment for reservation',
            'orderType' => 'momo_wallet',
            'transId' => '99887766',
            'resultCode' => '0',
            'message' => 'Successful',
            'payType' => 'qr',
            'responseTime' => '2026-05-31T12:00:00Z',
            'extraData' => 'deposit',
        ];

        $canonicalString = "accessKey={$accessKey}"
            ."&amount={$params['amount']}"
            ."&extraData={$params['extraData']}"
            ."&message={$params['message']}"
            ."&orderId={$params['orderId']}"
            ."&orderInfo={$params['orderInfo']}"
            ."&orderType={$params['orderType']}"
            ."&partnerCode={$params['partnerCode']}"
            ."&requestId={$params['requestId']}"
            ."&responseTime={$params['responseTime']}"
            ."&resultCode={$params['resultCode']}"
            ."&transId={$params['transId']}"
            ."&payType={$params['payType']}";

        $expected = hash_hmac('sha256', $canonicalString, $secretKey);

        $params['signature'] = $expected;

        $rawBody = json_encode($params, JSON_THROW_ON_ERROR);

        self::assertTrue($this->adapter->verifyWebhookSignature($rawBody, []));

        // Test tampered value
        $tamperedBody = str_replace('150000', '250000', $rawBody);
        self::assertFalse($this->adapter->verifyWebhookSignature($tamperedBody, []));
    }

    public function test_parse_webhook_resolves_succeeded_status(): void
    {
        $payload = [
            'partnerCode' => 'MOMO123',
            'orderId' => 'momo-session-abc-123',
            'requestId' => 'request-456',
            'amount' => '150000',
            'orderInfo' => 'Deposit payment for reservation',
            'transId' => '99887766',
            'resultCode' => '0',
            'message' => 'Successful',
            'extraData' => 'deposit',
            'signature' => 'sig123',
        ];

        $result = $this->adapter->parseWebhook(json_encode($payload, JSON_THROW_ON_ERROR), []);

        self::assertSame('momo', $result['provider_code']);
        self::assertSame('request-456', $result['provider_event_code']);
        self::assertSame('momo-session-abc-123', $result['provider_session_code']);
        self::assertSame('99887766', $result['provider_payment_code']);
        self::assertSame('deposit', $result['payment_scope']);
        self::assertSame('Succeeded', $result['session_status']);
        self::assertNull($result['failure_code']);
        self::assertSame('sig123', $result['request_signature']);
    }

    public function test_parse_webhook_resolves_failed_status(): void
    {
        $payload = [
            'partnerCode' => 'MOMO123',
            'orderId' => 'momo-session-abc-123',
            'requestId' => 'request-456',
            'amount' => '150000',
            'orderInfo' => 'Bill payment for reservation',
            'transId' => '99887766',
            'resultCode' => '49', // user rejected transaction
            'message' => 'User rejected',
            'extraData' => 'bill',
        ];

        $result = $this->adapter->parseWebhook(json_encode($payload, JSON_THROW_ON_ERROR), []);

        self::assertSame('Failed', $result['session_status']);
        self::assertSame('49', $result['failure_code']);
        self::assertSame('bill', $result['payment_scope']);
        self::assertSame('User rejected', $result['failure_message']);
    }

    public function test_parse_webhook_throws_exception_when_order_id_missing(): void
    {
        $payload = [
            'partnerCode' => 'MOMO123',
            'resultCode' => '0',
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
        $session->provider_session_code = 'momo-session-123';
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

        self::assertSame('momo', $refreshResult['provider_code']);
        self::assertSame('Pending', $refreshResult['session_status']);
        self::assertSame('momo-session-123', $refreshResult['provider_session_code']);
        self::assertSame('refresh', $refreshResult['provider_payload']['mutated_by']);

        // Test confirm (Succeeded default)
        $confirmResult = $this->adapter->confirmSession(
            PaymentSessionScope::Deposit,
            $reservation,
            $session,
            []
        );

        self::assertSame('Succeeded', $confirmResult['session_status']);
        self::assertSame('momo-mock-payment-code', $confirmResult['provider_payment_code']);
        self::assertSame('confirm', $confirmResult['provider_payload']['mutated_by']);
    }
}
