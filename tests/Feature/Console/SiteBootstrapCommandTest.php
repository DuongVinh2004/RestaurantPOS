<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Database\Seeders\SystemReferenceDataSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsAuditTrailTables;
use Tests\TestCase;

class SiteBootstrapCommandTest extends TestCase
{
    use BuildsAuditTrailTables;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent CI environment variable injection from polluting validation tests
        putenv('BOOTSTRAP_ADMIN_USERNAME');
        putenv('BOOTSTRAP_STAFF_USERNAME');
        unset($_ENV['BOOTSTRAP_ADMIN_USERNAME'], $_ENV['BOOTSTRAP_STAFF_USERNAME']);
        unset($_SERVER['BOOTSTRAP_ADMIN_USERNAME'], $_SERVER['BOOTSTRAP_STAFF_USERNAME']);

        // Ensure config is also cleared since it might have been loaded during app boot
        config()->set('booking.bootstrap.admin_username', null);
        config()->set('booking.bootstrap.staff_username', null);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('booking.multi_branch.default_branch_code', 'MAIN');
        config()->set('booking.multi_branch.default_branch_name', 'Chi nhánh chính');
        config()->set('booking.multi_branch.default_branch_timezone', 'Asia/Ho_Chi_Minh');
        config()->set('booking.multi_branch.default_branch_currency', 'VND');
        config()->set('booking.finance_tax_invoice_profile', [
            'tax_code' => 'VAT10',
            'tax_name' => 'VAT 10%',
            'tax_rate_percentage' => 10,
            'prices_include_tax' => true,
            'invoice_prefix' => 'INV',
            'seller_name' => 'RestaurantPOS',
            'seller_tax_id' => null,
            'seller_address' => null,
        ]);
        config()->set('staff_auth.allowed_role_ids', [1, 2]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createBootstrapTables();
        $this->seed(SystemReferenceDataSeeder::class);
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_creates_first_site_operational_data_idempotently(): void
    {
        $firstExitCode = Artisan::call('booking:bootstrap-site', ['--admin-username' => 'testadmin', '--staff-username' => 'teststaff', '--json' => true]);
        $this->assertSame(0, $firstExitCode);

        $first = $this->decodeArtisanOutput();
        $this->assertTrue($first['ok']);
        $this->assertSame('MAIN', $first['data']['branch']['branch_code']);
        $this->assertSame(16, (int) $first['data']['tables']['count']);
        $this->assertSame(0, (int) $first['data']['menu']['category_count']);
        $this->assertSame(0, (int) $first['data']['menu']['item_count']);
        $this->assertSame('created', $first['data']['finance']['action']);
        $this->assertSame('issued', $first['data']['staff_api_key']['action']);
        $this->assertNull($first['data']['staff_api_key']['plaintext_key'] ?? null);
        $this->assertFalse((bool) ($first['data']['staff_api_key']['secret_revealed'] ?? true));
        $this->assertStringStartsWith('spk_', (string) $first['data']['staff_api_key']['plaintext_key_masked']);

        $this->assertSame(8, (int) DB::table('roles')->count());
        $this->assertSame(1, (int) DB::table('branches')->count());
        $this->assertSame('Chi nhánh chính', (string) DB::table('branches')->value('branch_name'));
        $this->assertSame(3, (int) DB::table('table_templates')->count());
        $this->assertSame(['MS-2P', 'MS-4P', 'MS-6P'], DB::table('table_templates')->orderBy('seats')->pluck('template_code')->all());
        $this->assertSame(16, (int) DB::table('restaurant_tables')->count());
        $this->assertSame(['Garden Corner', 'Main Hall', 'Private Room', 'Window Zone'], DB::table('restaurant_tables')->distinct()->orderBy('zone')->pluck('zone')->all());
        $this->assertSame(0, (int) DB::table('menu_categories')->count());
        $this->assertSame(0, (int) DB::table('menu_items')->count());
        $this->assertSame(0, (int) DB::table('menu_item_prices')->count());
        $this->assertSame(1, (int) DB::table('settings')->count());
        $this->assertSame(2, (int) DB::table('users')->count());
        $this->assertSame(1, (int) DB::table('staff_api_keys')->count());
        $this->assertSame(1, (int) DB::table('audit_logs')->where('action', 'identity.staff_api_key.issued')->count());
        $this->assertSame(1, (int) DB::table('audit_log_subjects')->where('subject_type', 'staff_api_key')->count());

        $secondExitCode = Artisan::call('booking:bootstrap-site', ['--json' => true]);
        $this->assertSame(0, $secondExitCode);

        $second = $this->decodeArtisanOutput();
        $this->assertSame('existing', $second['data']['finance']['action']);
        $this->assertSame('existing', $second['data']['staff_api_key']['action']);
        $this->assertNull($second['data']['staff_api_key']['plaintext_key']);
        $this->assertNull($second['data']['staff_api_key']['plaintext_key_masked']);
        $this->assertSame('testadmin', $second['data']['users']['admin']['username']);
        $this->assertSame('teststaff', $second['data']['users']['staff']['username']);

        $this->assertSame(1, (int) DB::table('branches')->count());
        $this->assertSame(16, (int) DB::table('restaurant_tables')->count());
        $this->assertSame(0, (int) DB::table('menu_items')->count());
        $this->assertSame(1, (int) DB::table('staff_api_keys')->count());
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_fails_if_fresh_db_and_no_credentials_provided(): void
    {
        $exitCode = Artisan::call('booking:bootstrap-site', ['--json' => true]);
        $this->assertSame(1, $exitCode);

        $output = $this->decodeArtisanOutput();
        $this->assertArrayNotHasKey('ok', $output);
        $this->assertSame('validation_error', $output['error']);
        $this->assertArrayHasKey('admin_username', $output['errors']);
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_passes_if_credentials_provided_via_config(): void
    {
        config()->set('booking.bootstrap.admin_username', 'configadmin');
        config()->set('booking.bootstrap.staff_username', 'configstaff');

        $exitCode = Artisan::call('booking:bootstrap-site', ['--json' => true]);
        $this->assertSame(0, $exitCode);

        $output = $this->decodeArtisanOutput();
        $this->assertTrue($output['ok']);
        $this->assertSame('configadmin', $output['data']['users']['admin']['username']);
        $this->assertSame('configstaff', $output['data']['users']['staff']['username']);
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_uses_requested_branch_code_on_empty_database_without_creating_a_shadow_default_branch(): void
    {
        $exitCode = Artisan::call('booking:bootstrap-site', [
            '--branch-code' => 'SITE01',
            '--branch-name' => 'Site 01',
            '--admin-username' => 'testadmin',
            '--staff-username' => 'teststaff',
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $payload = $this->decodeArtisanOutput();

        $this->assertSame('SITE01', $payload['data']['branch']['branch_code']);
        $this->assertSame(1, (int) DB::table('branches')->count());
        $this->assertSame('SITE01', (string) DB::table('branches')->value('branch_code'));
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_leaves_existing_menu_alone(): void
    {
        DB::table('menu_categories')->insert([
            ['name' => 'Do uong', 'description' => 'Do uong khoi tao', 'sort_order' => 10, 'is_deleted' => false],
            ['name' => 'Mon chinh', 'description' => 'Mon chinh khoi tao', 'sort_order' => 20, 'is_deleted' => false],
        ]);

        $exitCode = Artisan::call('booking:bootstrap-site', ['--admin-username' => 'testadmin', '--staff-username' => 'teststaff', '--json' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(2, (int) DB::table('menu_categories')->count());
        $this->assertSame(2, (int) DB::table('menu_categories')->whereIn('name', ['Do uong', 'Mon chinh'])->count());
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_renames_legacy_table_templates(): void
    {
        DB::table('table_templates')->insert([
            ['template_code' => 'BOOT-2P', 'seats' => 2, 'description' => 'Legacy 2 seats'],
            ['template_code' => 'BOOT-4P', 'seats' => 4, 'description' => 'Legacy 4 seats'],
            ['template_code' => 'BOOT-6P', 'seats' => 6, 'description' => 'Legacy 6 seats'],
        ]);

        $exitCode = Artisan::call('booking:bootstrap-site', ['--admin-username' => 'testadmin', '--staff-username' => 'teststaff', '--json' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(3, (int) DB::table('table_templates')->count());
        $this->assertSame(['MS-2P', 'MS-4P', 'MS-6P'], DB::table('table_templates')->orderBy('seats')->pluck('template_code')->all());
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_reveals_bootstrap_staff_key_only_when_explicitly_requested(): void
    {
        $exitCode = Artisan::call('booking:bootstrap-site', [
            '--show-secret-once' => true,
            '--admin-username' => 'testadmin',
            '--staff-username' => 'teststaff',
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $payload = $this->decodeArtisanOutput();
        $this->assertTrue($payload['ok']);
        $this->assertTrue((bool) ($payload['data']['staff_api_key']['secret_revealed'] ?? false));
        $this->assertStringStartsWith('spk_', (string) $payload['data']['staff_api_key']['plaintext_key']);
        $this->assertStringStartsWith('spk_', (string) $payload['data']['staff_api_key']['plaintext_key_masked']);
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_returns_structured_validation_errors_for_invalid_input(): void
    {
        $exitCode = Artisan::call('booking:bootstrap-site', [
            '--staff-username' => '',
            '--json' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $payload = $this->decodeArtisanOutput();

        $this->assertSame('validation_error', $payload['error'] ?? null);
        $this->assertIsArray($payload['errors']['admin_username'] ?? null);
        $this->assertNotEmpty($payload['errors']['admin_username'] ?? []);
    }

    private function createBootstrapTables(): void
    {
        Schema::dropIfExists('staff_api_keys');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('menu_item_prices');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('table_templates');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('role_id');
            $table->string('role_name')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('user_id');
            $table->string('username')->nullable()->unique();
            $table->string('password_hash')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable()->unique();
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('current_tier_id')->nullable();
            $table->string('language_pref', 5)->default('vn');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->increments('branch_id');
            $table->string('branch_code')->unique();
            $table->string('branch_name');
            $table->string('description')->nullable();
            $table->string('timezone');
            $table->string('currency', 10);
            $table->json('business_hours')->nullable();
            $table->json('closure_windows')->nullable();
            $table->json('booking_policy')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        Schema::create('table_templates', function (Blueprint $table): void {
            $table->increments('template_id');
            $table->string('template_code')->nullable()->unique();
            $table->unsignedInteger('seats')->default(4);
            $table->string('description')->nullable();
        });

        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->increments('table_id');
            $table->string('table_code')->unique();
            $table->unsignedInteger('branch_id');
            $table->unsignedInteger('template_id')->nullable();
            $table->string('zone')->nullable();
            $table->integer('pos_x')->nullable();
            $table->integer('pos_y')->nullable();
            $table->string('status')->default('Available');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->boolean('is_deleted')->default(false);
            $table->unsignedBigInteger('row_version')->default(1);
            $table->decimal('price', 14, 2)->nullable();
        });

        Schema::create('menu_categories', function (Blueprint $table): void {
            $table->increments('category_id');
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_deleted')->default(false);
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->increments('item_id');
            $table->unsignedInteger('category_id')->nullable();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('img_url')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_preorder_enabled')->default(false);
            $table->unsignedInteger('preorder_quota_per_day')->nullable();
            $table->unsignedInteger('preorder_cutoff_minutes')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_item_prices', function (Blueprint $table): void {
            $table->increments('price_id');
            $table->unsignedInteger('item_id');
            $table->decimal('price', 14, 2);
            $table->string('currency', 10)->default('VND');
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('setting_key')->primary();
            $table->json('value_json');
            $table->unsignedInteger('updated_by')->nullable();
            $table->dateTime('updated_at');
        });

        Schema::create('staff_api_keys', function (Blueprint $table): void {
            $table->increments('staff_api_key_id');
            $table->unsignedInteger('user_id');
            $table->string('label', 100);
            $table->char('key_hash', 64)->unique();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
        });

        $this->ensureAuditTrailTables();
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
