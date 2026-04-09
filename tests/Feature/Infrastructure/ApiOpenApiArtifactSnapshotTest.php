<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Services\ApiContract\OpenApiSpecService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ApiOpenApiArtifactSnapshotTest extends TestCase
{
    public function test_generated_openapi_artifact_matches_the_frozen_release_artifact(): void
    {
        $relativePath = (string) config('booking_release.api_contract.openapi_path', 'storage/app/booking_release/openapi-v1.json');
        $absolutePath = base_path($relativePath);

        $this->assertFileExists($absolutePath);

        $frozen = json_decode((string) File::get($absolutePath), true, 512, JSON_THROW_ON_ERROR);
        $live = json_decode(
            json_encode(
                app(OpenApiSpecService::class)->build(),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame($frozen, $live);
    }
}
