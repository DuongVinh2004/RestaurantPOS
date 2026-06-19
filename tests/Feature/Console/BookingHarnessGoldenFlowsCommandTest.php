<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Database\Seeders\SystemReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class BookingHarnessGoldenFlowsCommandTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->seed(SystemReferenceDataSeeder::class);
        config()->set('staff_auth.allowed_role_ids', [1, 2]);
        $this->manifestPath = storage_path('framework/testing/harness-golden-flows.json');

        if (File::exists($this->manifestPath)) {
            File::delete($this->manifestPath);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->manifestPath)) {
            File::delete($this->manifestPath);
        }

        parent::tearDown();
    }

    public function test_booking_harness_golden_flows_can_resolve_scenario_context_without_manifest(): void
    {
        $exitCode = Artisan::call('booking:harness:golden-flows', [
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $scenarioKeys = collect((array) ($payload['scenarios'] ?? []))
            ->pluck('key')
            ->all();

        $this->assertSame(0, $exitCode);
        $this->assertTrue((bool) ($payload['ok'] ?? false));
        $this->assertFalse((bool) ($payload['manifest_available'] ?? false));
        $this->assertCount(5, (array) ($payload['scenarios'] ?? []));
        $this->assertContains('customer_reservation_journey', $scenarioKeys);
        $this->assertContains('deposit_self_pay', $scenarioKeys);
    }
}
