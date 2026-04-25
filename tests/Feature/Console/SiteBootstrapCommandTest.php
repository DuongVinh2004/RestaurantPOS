<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class SiteBootstrapCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('booking.multi_branch.default_branch_code', 'MAIN');
        config()->set('booking.multi_branch.default_branch_name', 'Chi nhanh chinh');
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
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_creates_first_site_operational_data_idempotently(): void
    {
        $firstExitCode = Artisan::call('booking:bootstrap-site', ['--json' => true]);
        $this->assertSame(0, $firstExitCode);

        $first = $this->decodeArtisanOutput();
        $this->assertTrue($first['ok']);
        $this->assertSame('MAIN', $first['data']['branch']['branch_code']);
        $this->assertSame(8, (int) $first['data']['tables']['count']);
        $this->assertSame(2, (int) $first['data']['menu']['category_count']);
        $this->assertSame(3, (int) $first['data']['menu']['item_count']);
        $this->assertSame('created', $first['data']['finance']['action']);
        $this->assertSame('issued', $first['data']['staff_api_key']['action']);
        $this->assertNull($first['data']['staff_api_key']['plaintext_key'] ?? null);
        $this->assertFalse((bool) ($first['data']['staff_api_key']['secret_revealed'] ?? true));
        $this->assertStringStartsWith('spk_', (string) $first['data']['staff_api_key']['plaintext_key_masked']);

        $this->assertSame(8, (int) DB::table('roles')->count());
        $this->assertSame(1, (int) DB::table('branches')->count());
        $this->assertSame(3, (int) DB::table('table_templates')->count());
        $this->assertSame(8, (int) DB::table('restaurant_tables')->count());
        $this->assertSame(2, (int) DB::table('menu_categories')->count());
        $this->assertSame(3, (int) DB::table('menu_items')->count());
        $this->assertSame(3, (int) DB::table('menu_item_prices')->count());
        $this->assertSame(1, (int) DB::table('settings')->count());
        $this->assertSame(2, (int) DB::table('users')->count());
        $this->assertSame(1, (int) DB::table('staff_api_keys')->count());

        $secondExitCode = Artisan::call('booking:bootstrap-site', ['--json' => true]);
        $this->assertSame(0, $secondExitCode);

        $second = $this->decodeArtisanOutput();
        $this->assertSame('existing', $second['data']['finance']['action']);
        $this->assertSame('existing', $second['data']['staff_api_key']['action']);
        $this->assertNull($second['data']['staff_api_key']['plaintext_key']);
        $this->assertNull($second['data']['staff_api_key']['plaintext_key_masked']);

        $this->assertSame(1, (int) DB::table('branches')->count());
        $this->assertSame(8, (int) DB::table('restaurant_tables')->count());
        $this->assertSame(3, (int) DB::table('menu_items')->count());
        $this->assertSame(1, (int) DB::table('staff_api_keys')->count());
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_uses_requested_branch_code_on_empty_database_without_creating_a_shadow_default_branch(): void
    {
        $exitCode = Artisan::call('booking:bootstrap-site', [
            '--branch-code' => 'SITE01',
            '--branch-name' => 'Site 01',
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $payload = $this->decodeArtisanOutput();

        $this->assertSame('SITE01', $payload['data']['branch']['branch_code']);
        $this->assertSame(1, (int) DB::table('branches')->count());
        $this->assertSame('SITE01', (string) DB::table('branches')->value('branch_code'));
    }

    #[Group('booking-ops')]
    public function test_bootstrap_site_command_reveals_bootstrap_staff_key_only_when_explicitly_requested(): void
    {
        $exitCode = Artisan::call('booking:bootstrap-site', [
            '--show-secret-once' => true,
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
        $this->assertIsArray($payload['errors']['username'] ?? null);
        $this->assertNotEmpty($payload['errors']['username'] ?? []);
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
