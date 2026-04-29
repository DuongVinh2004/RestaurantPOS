<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class IdempotencyMiddlewareFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        config()->set('booking.idempotency_ttl_hours', 1);
        config()->set('booking.idempotency_required_scopes', ['test.idem']);
        config()->set('booking.idempotency_pending_seconds', 120);

        app('cache')->forgetDriver('redis');
        Cache::store('redis')->flush();

        if (! Route::getRoutes()->getByName('testing.idem.echo')) {
            Route::post('/__testing__/idem/{reservation}', function (Request $request, int $reservation) {
                return response()->json([
                    'reservation_id' => $reservation,
                    'amount' => (float) $request->input('amount'),
                    'session_id' => (string) $request->input('session_id', ''),
                    'nonce' => Str::uuid()->toString(),
                ], 201);
            })->middleware(IdempotencyMiddleware::class.':test.idem')->name('testing.idem.echo');
        }

        config()->set('booking.idempotency_route_aliases', [
            '__testing__/idem-alias/{reservation}/legacy' => '__testing__/idem-alias/{reservation}/canonical',
        ]);

        if (! Route::getRoutes()->getByName('testing.idem.alias.canonical')) {
            Route::post('/__testing__/idem-alias/{reservation}/canonical', function (Request $request, int $reservation) {
                return response()->json([
                    'reservation_id' => $reservation,
                    'amount' => (float) $request->input('amount'),
                    'nonce' => Str::uuid()->toString(),
                ], 201);
            })->middleware(IdempotencyMiddleware::class.':test.idem')->name('testing.idem.alias.canonical');
        }

        if (! Route::getRoutes()->getByName('testing.idem.alias.legacy')) {
            Route::post('/__testing__/idem-alias/{reservation}/legacy', function (Request $request, int $reservation) {
                return response()->json([
                    'reservation_id' => $reservation,
                    'amount' => (float) $request->input('amount'),
                    'nonce' => Str::uuid()->toString(),
                ], 201);
            })->middleware(IdempotencyMiddleware::class.':test.idem')->name('testing.idem.alias.legacy');
        }

        if (! Route::getRoutes()->getByName('testing.idem.no-content')) {
            Route::post('/__testing__/idem-no-content/{reservation}', function () {
                return response()->noContent();
            })->middleware(IdempotencyMiddleware::class.':test.idem')->name('testing.idem.no-content');
        }

    }

    #[Group('booking-smoke')]
    public function test_missing_idempotency_key_returns_422(): void
    {
        $response = $this->postJson('/__testing__/idem/10', [
            'amount' => 100000,
            'session_id' => 'sess-a',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'idempotency_key_required')
            ->assertJsonPath('error_code', 'idempotency_key_required')
            ->assertJsonPath('category_code', 'validation_error')
            ->assertJsonPath('state_reason', 'missing_idempotency_key')
            ->assertJsonPath('next_actions.0', 'provide_idempotency_key')
            ->assertJsonPath('warnings.0', 'legacy_error_alias_deprecated')
            ->assertJsonPath('deprecated_aliases.0', 'error');
    }

    #[Group('booking-smoke')]
    public function test_same_key_same_payload_replays_cached_response(): void
    {
        $headers = ['Idempotency-Key' => 'idem-feature-replay-1'];
        $payload = [
            'amount' => 150000,
            'session_id' => 'sess-a',
        ];

        $first = $this->withHeaders($headers)->postJson('/__testing__/idem/22', $payload);
        $second = $this->withHeaders($headers)->postJson('/__testing__/idem/22', $payload);

        $first->assertCreated();
        $second->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame('false', $first->headers->get('Idempotency-Replayed'));
        $this->assertSame($first->json('nonce'), $second->json('nonce'));
        $this->assertSame($first->json('reservation_id'), $second->json('reservation_id'));
        $this->assertSame($first->json('amount'), $second->json('amount'));
    }

    #[Group('booking-smoke')]
    public function test_x_idempotency_key_compatibility_header_still_replays_cached_response(): void
    {
        $headers = ['X-Idempotency-Key' => 'idem-feature-x-header-replay-1'];
        $payload = [
            'amount' => 175000,
            'session_id' => 'sess-x',
        ];

        $first = $this->withHeaders($headers)->postJson('/__testing__/idem/23', $payload);
        $second = $this->withHeaders($headers)->postJson('/__testing__/idem/23', $payload);

        $first->assertCreated()->assertHeader('Idempotency-Replayed', 'false');
        $second->assertCreated()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json('nonce'), $second->json('nonce'));
    }

    #[Group('booking-smoke')]
    public function test_body_idempotency_key_compatibility_still_replays_cached_response(): void
    {
        $payload = [
            'amount' => 185000,
            'session_id' => 'sess-body',
            'idempotency_key' => 'idem-feature-body-replay-1',
        ];

        $first = $this->postJson('/__testing__/idem/24', $payload);
        $second = $this->postJson('/__testing__/idem/24', $payload);

        $first->assertCreated()->assertHeader('Idempotency-Replayed', 'false');
        $second->assertCreated()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json('nonce'), $second->json('nonce'));
    }

    #[Group('booking-smoke')]
    public function test_same_key_with_different_payload_returns_conflict(): void
    {
        $headers = ['Idempotency-Key' => 'idem-feature-conflict-1'];

        $this->withHeaders($headers)->postJson('/__testing__/idem/33', [
            'amount' => 100000,
            'session_id' => 'sess-a',
        ])->assertCreated();

        $this->withHeaders($headers)->postJson('/__testing__/idem/33', [
            'amount' => 200000,
            'session_id' => 'sess-a',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'idempotency_conflict')
            ->assertJsonPath('error_code', 'idempotency_conflict')
            ->assertJsonPath('category_code', 'idempotency_conflict')
            ->assertJsonPath('conflict_type', 'idempotency_payload_mismatch')
            ->assertJsonPath('replay_state', 'payload_mismatch')
            ->assertJsonPath('state_reason', 'key_reused_for_different_payload')
            ->assertJsonPath('next_actions.0', 'retry_with_new_idempotency_key');
    }

    #[Group('booking-smoke')]
    public function test_same_key_is_isolated_by_route_parameters(): void
    {
        $headers = ['Idempotency-Key' => 'idem-feature-route-scope-1'];
        $payload = [
            'amount' => 99000,
            'session_id' => 'sess-a',
        ];

        $first = $this->withHeaders($headers)->postJson('/__testing__/idem/41', $payload);
        $second = $this->withHeaders($headers)->postJson('/__testing__/idem/42', $payload);

        $first->assertCreated()->assertHeader('Idempotency-Replayed', 'false');
        $second->assertCreated()->assertHeader('Idempotency-Replayed', 'false');

        $this->assertNotSame($first->json('nonce'), $second->json('nonce'));
        $this->assertSame(41, $first->json('reservation_id'));
        $this->assertSame(42, $second->json('reservation_id'));
    }

    #[Group('booking-smoke')]
    public function test_successful_204_responses_are_cached_and_replayed(): void
    {
        $headers = ['Idempotency-Key' => 'idem-feature-no-content-1'];

        $first = $this->withHeaders($headers)->post('/__testing__/idem-no-content/88', []);
        $second = $this->withHeaders($headers)->post('/__testing__/idem-no-content/88', []);

        $first->assertNoContent()->assertHeader('Idempotency-Replayed', 'false');
        $second->assertNoContent()->assertHeader('Idempotency-Replayed', 'true');
    }

    #[Group('booking-smoke')]
    public function test_same_key_replays_across_route_aliases_that_share_a_canonical_path(): void
    {
        $headers = ['Idempotency-Key' => 'idem-feature-alias-replay-1'];
        $payload = ['amount' => 123000];

        $first = $this->withHeaders($headers)->postJson('/__testing__/idem-alias/77/canonical', $payload);
        $second = $this->withHeaders($headers)->postJson('/__testing__/idem-alias/77/legacy', $payload);

        $first->assertCreated()->assertHeader('Idempotency-Replayed', 'false');
        $second->assertCreated()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json('nonce'), $second->json('nonce'));
    }
}
