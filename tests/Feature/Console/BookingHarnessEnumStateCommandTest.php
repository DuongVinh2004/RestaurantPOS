<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BookingHarnessEnumStateCommandTest extends TestCase
{
    private string $root = 'storage/framework/testing/harness_enum_state';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->root));
        parent::tearDown();
    }

    public function test_booking_harness_enum_state_exports_json_and_typescript_contracts(): void
    {
        $exitCode = Artisan::call('booking:harness:enum-state', [
            '--json' => true,
            '--output-root' => $this->root,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $jsonPath = base_path((string) data_get($payload, 'artifacts.enum_state_json'));
        $typescriptPath = base_path((string) data_get($payload, 'artifacts.enum_state_typescript'));

        $this->assertSame(0, $exitCode);
        $this->assertTrue((bool) ($payload['ok'] ?? false));
        $this->assertFileExists($jsonPath);
        $this->assertFileExists($typescriptPath);

        /** @var array<string,mixed> $json */
        $json = json_decode((string) File::get($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $typescript = (string) File::get($typescriptPath);

        $this->assertSame('Reserved', data_get($json, 'enums.ReservationStatus.semantic_aliases.checked_in'));
        $this->assertContains('Confirmed', (array) data_get($json, 'enums.ReservationStatus.state_hints.active_db_values', []));
        $this->assertStringContainsString('reservationStatusValues', $typescript);
        $this->assertStringContainsString('restaurantPosEnumStateMap', $typescript);
    }
}
