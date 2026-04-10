<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\Uat\UatScenarioPackService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class UatScenarioPackConsoleCommandTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('staff_auth.allowed_role_ids', [1, 2]);
        $this->manifestPath = storage_path('framework/testing/uat-scenario-pack-test.json');

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

    #[Group('booking-ops')]
    public function test_bootstrap_and_reset_commands_manage_canonical_uat_pack_data_and_manifest(): void
    {
        $bootstrapExit = Artisan::call('booking:uat-pack:bootstrap', [
            '--base-url' => 'http://127.0.0.1:8000',
            '--manifest-path' => $this->manifestPath,
            '--json' => true,
        ]);

        self::assertSame(0, $bootstrapExit);
        $bootstrap = $this->decodeArtisanOutput();

        self::assertTrue((bool) $bootstrap['ok']);
        self::assertSame('UATDEMO', (string) data_get($bootstrap, 'data.summary.branch.branch_code'));
        self::assertSame('restaurantpos-uat-demo', (string) data_get($bootstrap, 'data.manifest.pack.name'));
        self::assertNotEmpty((string) data_get($bootstrap, 'data.manifest.auth.admin.api_key'));
        self::assertSame('UatDemo!123', (string) data_get($bootstrap, 'data.manifest.auth.customer_primary.password'));
        self::assertCount(9, (array) data_get($bootstrap, 'data.summary.supported_scenarios', []));
        self::assertTrue(File::exists($this->manifestPath));

        /** @var array<string,mixed> $manifest */
        $manifest = json_decode((string) File::get($this->manifestPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('UATDEMO', (string) data_get($manifest, 'branch.branch_code'));
        self::assertSame('Asia/Ho_Chi_Minh', (string) data_get($manifest, 'branch.timezone'));
        self::assertSame('UAT-VOUCHER-50', (string) data_get($manifest, 'benefits.voucher.voucher_code'));
        self::assertSame('Open', (string) data_get($manifest, 'conversation.status'));
        self::assertSame('customer.bill_self_payment', (string) data_get($manifest, 'feature_flags.0.feature_key'));

        self::assertSame(1, (int) DB::table('branches')->where('branch_code', 'UATDEMO')->count());
        self::assertSame(4, (int) DB::table('users')->whereIn('username', [
            'uat.admin',
            'uat.staff',
            'uat.customer.primary',
            'uat.customer.secondary',
        ])->count());
        self::assertSame(5, (int) DB::table('reservations')->whereIn('reservation_code', [
            'UAT-DEP-001',
            'UAT-DINE-001',
            'UAT-BEN-001',
            'UAT-RF-001',
            'UAT-RFC-001',
        ])->count());
        self::assertSame(5, (int) DB::table('feature_flags')
            ->where('branch_id', (int) data_get($manifest, 'branch.branch_id'))
            ->count());

        $resetExit = Artisan::call('booking:uat-pack:reset', [
            '--manifest-path' => $this->manifestPath,
            '--json' => true,
        ]);

        self::assertSame(0, $resetExit);
        $reset = $this->decodeArtisanOutput();

        self::assertTrue((bool) $reset['ok']);
        self::assertTrue((bool) data_get($reset, 'data.manifest_deleted'));
        self::assertFalse(File::exists($this->manifestPath));
        self::assertSame(0, (int) DB::table('branches')->where('branch_code', 'UATDEMO')->count());
        self::assertSame(0, (int) DB::table('users')->whereIn('username', [
            'uat.admin',
            'uat.staff',
            'uat.customer.primary',
            'uat.customer.secondary',
        ])->count());
    }

    #[Group('booking-ops')]
    public function test_bootstrap_command_returns_structured_validation_errors_when_bootstrap_validation_fails(): void
    {
        $this->app->instance(UatScenarioPackService::class, new class extends UatScenarioPackService
        {
            public function __construct() {}

            public function bootstrap(?string $baseUrl = null, ?string $manifestPath = null): array
            {
                throw ValidationException::withMessages([
                    'base_url' => ['The base_url must use https.'],
                ]);
            }
        });

        $exitCode = Artisan::call('booking:uat-pack:bootstrap', [
            '--base-url' => 'http://127.0.0.1:8000',
            '--json' => true,
        ]);

        self::assertSame(1, $exitCode);

        $payload = $this->decodeArtisanOutput();

        self::assertSame('validation_error', $payload['error'] ?? null);
        self::assertSame(['The base_url must use https.'], $payload['errors']['base_url'] ?? null);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeArtisanOutput(): array
    {
        /** @var array<string,mixed> $payload */
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}
