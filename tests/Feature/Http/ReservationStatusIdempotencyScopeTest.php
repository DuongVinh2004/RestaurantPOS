<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReservationStatusIdempotencyScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        config()->set('booking.idempotency_ttl_hours', 1);
        config()->set('booking.idempotency_pending_seconds', 120);
        config()->set('booking.idempotency_required_scopes', [
            'staff.reservation-status',
        ]);

        app('cache')->forgetDriver('redis');
        Cache::store('redis')->flush();

        if (! Route::getRoutes()->getByName('testing.reservation-status.idem')) {
            Route::patch('/__testing__/reservation-status-idem/{reservation}', function (Request $request, int $reservation) {
                return response()->json([
                    'reservation_id' => $reservation,
                    'status' => (string) $request->input('status'),
                    'row_version' => $request->input('row_version'),
                    'nonce' => Str::uuid()->toString(),
                ]);
            })->middleware(IdempotencyMiddleware::class . ':staff.reservation-status')
              ->name('testing.reservation-status.idem');
        }
    }

    #[Test]
    public function reservation_status_scope_requires_idempotency_key(): void
    {
        $this->patchJson('/__testing__/reservation-status-idem/44', [
            'status' => 'Cancelled',
            'row_version' => 2,
        ])->assertStatus(422)
          ->assertJsonPath('error', 'idempotency_key_required');
    }

    #[Test]
    public function reservation_status_scope_replays_same_key_for_same_payload(): void
    {
        $headers = ['Idempotency-Key' => 'idem-reservation-status-1'];
        $payload = [
            'status' => 'Cancelled',
            'row_version' => 2,
        ];

        $first = $this->withHeaders($headers)->patchJson('/__testing__/reservation-status-idem/44', $payload);
        $second = $this->withHeaders($headers)->patchJson('/__testing__/reservation-status-idem/44', $payload);

        $first->assertOk()->assertHeader('Idempotency-Replayed', 'false');
        $second->assertOk()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json('nonce'), $second->json('nonce'));
        $this->assertSame('Cancelled', $second->json('status'));
        $this->assertSame(2, $second->json('row_version'));
    }
}
