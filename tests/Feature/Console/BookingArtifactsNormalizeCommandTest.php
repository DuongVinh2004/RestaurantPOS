<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\Release\Services\ReleaseArtifactNormalizerService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingArtifactsNormalizeCommandTest extends TestCase
{
    public function test_booking_artifacts_normalize_supports_json_output(): void
    {
        $this->app->instance(ReleaseArtifactNormalizerService::class, new class extends ReleaseArtifactNormalizerService {
            public function normalize(): array
            {
                return [
                    'ok' => true,
                    'changed' => true,
                    'issues' => [],
                    'artifacts' => [
                        'schema_dump' => [
                            'path' => 'database/schema/mysql-schema.sql',
                            'exists' => true,
                            'changed' => true,
                            'normalizations' => ['stripped_create_definers'],
                        ],
                    ],
                    'meta' => [
                        'generated_at_utc' => '2026-03-15T12:00:00Z',
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('booking:artifacts-normalize', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"changed": true', $output);
        $this->assertStringContainsString('"schema_dump"', $output);
    }
}
