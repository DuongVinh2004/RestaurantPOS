<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\NotificationOutboxService;
use ReflectionMethod;
use Tests\TestCase;

class NotificationOutboxServiceIdempotencyKeyTest extends TestCase
{
    public function test_refund_outbox_idempotency_key_fits_mysql_column_limit_and_is_stable(): void
    {
        $service = app(NotificationOutboxService::class);
        $method = new ReflectionMethod($service, 'buildRefundIdempotencyKey');
        $method->setAccessible(true);

        $first = $method->invoke($service, 44, [321, 654], 20000.0, 'final');
        $second = $method->invoke($service, 44, [321, 654], 20000.0, 'final');
        $different = $method->invoke($service, 44, [321, 654], 30000.0, 'final');

        $this->assertIsString($first);
        $this->assertLessThanOrEqual(64, strlen($first));
        $this->assertSame($first, $second);
        $this->assertNotSame($first, $different);
        $this->assertStringStartsWith('reservation:44:rf:', $first);
    }
}
