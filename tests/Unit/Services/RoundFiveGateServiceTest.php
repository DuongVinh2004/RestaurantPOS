<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RoundFiveGateService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RoundFiveGateServiceTest extends TestCase
{
    public function test_definition_reads_canonical_round5_suite(): void
    {
        $service = new RoundFiveGateService();

        $definition = $service->definition();

        $this->assertSame('round5', $definition['suite']);
        $this->assertSame('tests/fixtures/round5_gate_suite.json', $definition['definition_path']);
        $this->assertSame('storage/app/booking_release/round5_gate_snapshot.json', $definition['snapshot_path']);
        $this->assertNotEmpty($definition['definition_sha256']);
        $paths = array_column($definition['tests'], 'path');

        $this->assertContains('tests/Feature/Staff/StaffCheckoutFinancialOutboxAndCoverageTest.php', $paths);
        $this->assertContains('tests/Feature/Payments/PaymentProviderWebhookFlowTest.php', $paths);
        $this->assertContains('tests/Feature/Reservation/CustomerReservationDepositPaymentSessionFlowTest.php', $paths);
        $this->assertContains('tests/Feature/Reservation/CustomerReservationOrderBillSelfPaymentFlowTest.php', $paths);
        $this->assertContains('tests/Unit/Services/PaymentIntegration/GenericHttpHmacPaymentProviderAdapterTest.php', $paths);
    }


    public function test_build_artisan_test_command_uses_supported_arguments_only(): void
    {
        $service = new class extends RoundFiveGateService {
            /**
             * @return list<string>
             */
            public function exposeBuildArtisanTestCommand(string $path): array
            {
                return $this->buildArtisanTestCommand($path);
            }
        };

        $command = $service->exposeBuildArtisanTestCommand('tests/Feature/Staff/StaffCheckoutFinancialIntegrationMatrixTest.php');

        $this->assertSame(PHP_BINARY ?: 'php', $command[0]);
        $this->assertSame(base_path('artisan'), $command[1]);
        $this->assertSame('test', $command[2]);
        $this->assertSame('tests/Feature/Staff/StaffCheckoutFinancialIntegrationMatrixTest.php', $command[3]);
        $this->assertNotContains('--without-tty', $command);
    }

    public function test_run_can_write_snapshot_without_shelling_out_when_single_test_runner_is_overridden(): void
    {
        $snapshotPath = 'storage/framework/testing/round5_gate/test_snapshot.json';
        config()->set('booking_release.round5_gate.snapshot_path', $snapshotPath);

        $service = new class extends RoundFiveGateService {
            protected function runSingleTest(array $test): array
            {
                return array_merge($test, [
                    'ok' => ! str_contains((string) $test['key'], 'reservation_status'),
                    'exit_code' => str_contains((string) $test['key'], 'reservation_status') ? 1 : 0,
                    'duration_ms' => 15,
                    'output_tail' => 'synthetic',
                ]);
            }
        };

        $snapshot = $service->run(write: true);
        $absolutePath = base_path($snapshotPath);

        $this->assertFalse($snapshot['ok']);
        $this->assertSame(count($snapshot['tests']), $snapshot['summary']['total']);
        $this->assertSame(1, $snapshot['summary']['failed']);
        $this->assertTrue(File::exists($absolutePath));

        $stored = json_decode((string) File::get($absolutePath), true);
        $this->assertIsArray($stored);
        $this->assertSame('round5', $stored['suite']);
        $this->assertSame($snapshotPath, $stored['snapshot_path']);

        File::delete($absolutePath);
    }
}
