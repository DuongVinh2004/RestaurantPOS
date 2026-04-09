<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use PHPUnit\Framework\Attributes\Group;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class IdempotencyMiddlewareLockingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Group('booking-smoke')]
    public function test_returns_in_progress_when_same_key_is_currently_locked(): void
    {
        config()->set('booking.idempotency_required_scopes', ['test.lock']);

        $cacheRepo = Mockery::mock(Repository::class);
        $lock = Mockery::mock(Lock::class);

        Cache::shouldReceive('store')
            ->once()
            ->with('redis')
            ->andReturn($cacheRepo);

        $cacheRepo->shouldReceive('get')
            ->times(4)
            ->andReturn(null);

        $cacheRepo->shouldReceive('lock')
            ->once()
            ->andReturn($lock);

        $lock->shouldReceive('get')
            ->once()
            ->andReturn(false);

        $lock->shouldReceive('release')
            ->once()
            ->andReturnTrue();

        $request = Request::create('/__testing__/idem-lock/10', 'POST', [
            'amount' => 100000,
        ]);
        $request->headers->set('Idempotency-Key', 'idem-lock-1');

        $middleware = new IdempotencyMiddleware();
        $response = $middleware->handle($request, function () {
            $this->fail('Middleware should not call downstream handler when lock acquisition fails.');
        }, 'test.lock');

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('idempotency_in_progress', $response->getData(true)['error'] ?? null);
    }

    #[Group('booking-smoke')]
    public function test_returns_in_progress_when_pending_marker_exists_even_without_lock_contention(): void
    {
        config()->set('booking.idempotency_required_scopes', ['test.lock']);

        $cacheRepo = Mockery::mock(Repository::class);

        Cache::shouldReceive('store')
            ->once()
            ->with('redis')
            ->andReturn($cacheRepo);

        $cacheRepo->shouldReceive('get')
            ->once()
            ->andReturn(null);

        $cacheRepo->shouldReceive('get')
            ->once()
            ->andReturn([
                'state' => 'pending',
                'payload_hash' => hash('sha256', json_encode([
                    'method' => 'POST',
                    'path' => '__testing__/idem-lock/10',
                    'route' => [],
                    'body' => ['amount' => 100000],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'started_at' => now()->toIso8601String(),
            ]);

        $request = Request::create('/__testing__/idem-lock/10', 'POST', [
            'amount' => 100000,
        ]);
        $request->headers->set('Idempotency-Key', 'idem-lock-pending-1');

        $middleware = new IdempotencyMiddleware();
        $response = $middleware->handle($request, function () {
            $this->fail('Middleware should not call downstream handler when a pending marker exists.');
        }, 'test.lock');

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('idempotency_in_progress', $response->getData(true)['error'] ?? null);
    }
}
