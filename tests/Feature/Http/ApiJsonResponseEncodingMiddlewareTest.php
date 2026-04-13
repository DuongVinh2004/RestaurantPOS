<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

final class ApiJsonResponseEncodingMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::getRoutes()->getByName('testing.api.encoding-success')) {
            Route::get('/api/__testing__/encoding-success', function (Request $request) {
                return response()->json([
                    'message' => $this->encodeMojibake('Không có'),
                    'nested' => [
                        'title' => $this->encodeMojibake('Sơ đồ bàn'),
                    ],
                    'request_path' => '/'.$request->path(),
                ]);
            })->middleware('reqid')->name('testing.api.encoding-success');
        }
    }

    #[Group('booking-smoke')]
    public function test_it_normalizes_api_json_payloads_and_logs_a_safe_warning(): void
    {
        Log::spy();

        $response = $this->withHeaders([
            'X-Request-Id' => 'req-api-encoding-1',
        ])->getJson('/api/__testing__/encoding-success');

        $response->assertOk()
            ->assertHeader('X-Request-Id', 'req-api-encoding-1')
            ->assertJsonPath('message', 'Không có')
            ->assertJsonPath('nested.title', 'Sơ đồ bàn')
            ->assertJsonPath('request_path', '/api/__testing__/encoding-success');

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('api_json_response_encoding_repaired', \Mockery::on(static function (array $context): bool {
                return $context['request_id'] === 'req-api-encoding-1'
                    && $context['path'] === '/api/__testing__/encoding-success'
                    && is_int($context['replacement_count'])
                    && $context['replacement_count'] > 0;
            }));
    }

    private function encodeMojibake(string $value): string
    {
        $bytes = unpack('C*', $value);
        self::assertIsArray($bytes);

        $mapped = '';

        foreach ($bytes as $byte) {
            $mapped .= mb_chr(match ($byte) {
                0x80 => 0x20AC,
                0x82 => 0x201A,
                0x83 => 0x0192,
                0x84 => 0x201E,
                0x85 => 0x2026,
                0x86 => 0x2020,
                0x87 => 0x2021,
                0x88 => 0x02C6,
                0x89 => 0x2030,
                0x8A => 0x0160,
                0x8B => 0x2039,
                0x8C => 0x0152,
                0x8E => 0x017D,
                0x91 => 0x2018,
                0x92 => 0x2019,
                0x93 => 0x201C,
                0x94 => 0x201D,
                0x95 => 0x2022,
                0x96 => 0x2013,
                0x97 => 0x2014,
                0x98 => 0x02DC,
                0x99 => 0x2122,
                0x9A => 0x0161,
                0x9B => 0x203A,
                0x9C => 0x0153,
                0x9E => 0x017E,
                0x9F => 0x0178,
                default => $byte,
            }, 'UTF-8');
        }

        return $mapped;
    }
}
