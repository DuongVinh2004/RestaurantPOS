<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

final class ApiDependencyOutageEnvelopeTest extends TestCase
{
    public function test_redis_required_middleware_returns_standard_api_error_envelope_when_redis_is_unavailable(): void
    {
        Route::get('/api/__testing__/redis-required', static fn () => response()->json(['data' => true]))
            ->middleware(['reqid', 'require.redis']);

        config()->set('booking.require_redis_for_booking_api', true);

        Cache::shouldReceive('store')
            ->once()
            ->with('redis')
            ->andThrow(new RuntimeException('redis secret connection string should not leak'));

        $response = $this->withHeaders([
            'X-Request-Id' => 'req-redis-unavailable',
        ])->getJson('/api/__testing__/redis-required');

        $response->assertStatus(503)
            ->assertHeader('X-Request-Id', 'req-redis-unavailable')
            ->assertJsonPath('error_code', 'redis_required')
            ->assertJsonPath('category_code', 'dependency_unavailable')
            ->assertJsonPath('request_id', 'req-redis-unavailable')
            ->assertJsonPath('state_reason', 'redis_unavailable')
            ->assertJsonPath('details.dependency', 'redis')
            ->assertJsonPath('next_actions.0', 'retry_after_dependency_recovery');

        self::assertStringNotContainsString('secret connection string', $response->getContent());
        self::assertStringNotContainsString('RuntimeException', $response->getContent());
    }
}
