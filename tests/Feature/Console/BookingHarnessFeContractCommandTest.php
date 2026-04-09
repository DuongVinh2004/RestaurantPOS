<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BookingHarnessFeContractCommandTest extends TestCase
{
    private string $root = 'storage/framework/testing/harness_fe_contract';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->root));
        parent::tearDown();
    }

    public function test_booking_harness_fe_contract_generates_sdk_mutation_and_enum_artifacts(): void
    {
        $exitCode = Artisan::call('booking:harness:fe-contract', [
            '--json' => true,
            '--output-root' => $this->root,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $collectionPath = str_replace('\\', '/', (string) data_get($payload, 'artifacts.collection'));

        $this->assertSame(0, $exitCode);
        $this->assertTrue((bool) ($payload['ok'] ?? false));
        $this->assertStringStartsWith($this->root.'/', $collectionPath);
        $this->assertFileExists(base_path((string) data_get($payload, 'artifacts.sdk_typescript')));
        $this->assertFileExists(base_path((string) data_get($payload, 'artifacts.mutation_contract')));
        $this->assertFileExists(base_path((string) data_get($payload, 'artifacts.enum_state_json')));
        $this->assertFileExists(base_path((string) data_get($payload, 'artifacts.enum_state_typescript')));
        $this->assertGreaterThan(0, (int) data_get($payload, 'summary.enum_group_count', 0));
        $this->assertSame('storage/app/booking_release/openapi-v1.json', data_get($payload, 'official_sources.frozen_openapi'));
        $this->assertSame(data_get($payload, 'artifacts.sdk_typescript'), data_get($payload, 'official_sources.sdk_typescript'));
        $this->assertSame(data_get($payload, 'artifacts.enum_state_typescript'), data_get($payload, 'official_sources.sdk_enums'));
    }
}
