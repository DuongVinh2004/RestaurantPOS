<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

final class ApiValidationPayloadCompatibilityTest extends TestCase
{
    #[Group('booking-smoke')]
    public function test_api_validation_payload_exposes_top_level_errors_for_phpunit_assertions(): void
    {
        Route::post('/api/__testing__/validation-payload', static function () {
            throw ValidationException::withMessages([
                'session_id' => [self::encodeMojibake('session_id không hợp lệ.')],
            ]);
        })->middleware('reqid');

        $response = $this->postJson('/api/__testing__/validation-payload');

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('category_code', 'validation_error')
            ->assertJsonPath('errors.session_id.0', 'session_id không hợp lệ.')
            ->assertJsonPath('details.errors.session_id.0', 'session_id không hợp lệ.');
    }

    private static function encodeMojibake(string $value): string
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
