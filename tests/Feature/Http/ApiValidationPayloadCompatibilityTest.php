<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ApiValidationPayloadCompatibilityTest extends TestCase
{
    public function test_api_validation_payload_exposes_top_level_errors_for_phpunit_assertions(): void
    {
        Route::post('/api/__testing__/validation-payload', static function () {
            throw ValidationException::withMessages([
                'session_id' => ['session_id không hợp lệ.'],
            ]);
        });

        $response = $this->postJson('/api/__testing__/validation-payload');

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.session_id.0', 'session_id không hợp lệ.')
            ->assertJsonPath('details.errors.session_id.0', 'session_id không hợp lệ.');
    }
}
