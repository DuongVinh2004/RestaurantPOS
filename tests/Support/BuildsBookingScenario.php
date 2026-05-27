<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\Cashiering\Application\Workflows\OrderSettlementWorkflow;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Loyalty\Application\UseCases\Points\ReservationLoyaltySummaryReader;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Ordering\Application\UseCases\Orders\StaffTableOrderService;
use App\Modules\Promotions\Application\Workflows\ReservationVoucherWorkflow;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Assert;

trait BuildsBookingScenario
{
    protected function requireBookingSchema(): void
    {
        config()->set('cache.stores.redis', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        app('cache')->forgetDriver('redis');

        $this->ensurePortableBookingSchema();
        $this->ensurePortableDbSensitiveSchemaParity();
        $this->ensureNotificationOutboxSchema();

        $missingTables = [];

        foreach ($this->requiredBookingTables() as $table) {
            if (! Schema::hasTable($table)) {
                $missingTables[] = $table;
            }
        }

        if ($missingTables !== []) {
            $this->failOrSkipBookingSchemaContract(
                sprintf(
                    'Required booking table(s) [%s] are missing. Bootstrap the booking schema contract on the test database first.',
                    implode(', ', $missingTables)
                )
            );
        }
    }

    protected function failOrSkipBookingSchemaContract(string $message): void
    {
        if ($this->shouldFailFastOnMissingBookingSchema()) {
            Assert::fail($message);
        }

        Assert::markTestSkipped($message);
    }

    protected function shouldFailFastOnMissingBookingSchema(): bool
    {
        return (bool) config('booking.testing.fail_fast_on_missing_schema', true);
    }

    /**
     * @return list<string>
     */
    protected function requiredBookingTables(): array
    {
        return [
            'branches',
            'feature_flags',
            'roles',
            'users',
            'staff_api_keys',
            'staff_branch_assignments',
            'customer_privacy_requests',
            'customer_access_sessions',
            'audit_logs',
            'audit_log_subjects',
            'reservations',
            'reservation_orders',
            'reservation_order_items',
            'payments',
            'finance_replay_records',
            'vouchers',
            'user_vouchers',
            'user_points',
            'loyalty_tiers',
            'loyalty_point_transactions',
            'user_tier_history',
            'restaurant_tables',
            'reservation_tables',
            'table_holds',
            'table_hold_details',
            'reservation_deposit_payment_sessions',
            'reservation_bill_payment_sessions',
            'payment_provider_webhook_receipts',
            'waiting_list',
            'conversations',
            'conversation_messages',
            'conversation_files',
            'conversation_events',
            'conversation_analyses',
            'agent_assignments',
            'message_entities',
            'conversation_aggregates',
            'notification_outbox',
            'menu_categories',
            'menu_items',
            'menu_item_prices',
            'table_templates',
            'ingredients',
            'menu_item_recipes',
            'ingredient_stock_movements',
            'suppliers',
            'purchase_orders',
            'purchase_order_lines',
            'purchase_receipts',
            'purchase_receipt_lines',
            'kitchen_stations',
            'kitchen_station_category_routes',
            'kitchen_order_item_tickets',
            'cashier_shifts',
            'billing_invoices',
            'reporting_daily_sales_snapshots',
            'reporting_daily_operation_snapshots',
            'reporting_daily_inventory_movement_snapshots',
            'preorders',
            'preorder_items',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    protected function stripGeneratedColumnsForInsert(string $table, array $payload): array
    {
        foreach ($this->generatedColumnsForTable($table) as $column) {
            unset($payload[$column]);
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    protected function generatedColumnsForTable(string $table): array
    {
        static $cache = [];

        $connection = DB::connection();
        $driver = (string) $connection->getDriverName();
        $database = (string) ($connection->getDatabaseName() ?? '');
        $cacheKey = $driver.'|'.$database.'|'.$table;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if (! Schema::hasTable($table)) {
            return $cache[$cacheKey] = [];
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $quotedTable = str_replace('`', '``', $table);
            $rows = DB::select("SHOW FULL COLUMNS FROM `{$quotedTable}`");
            $generatedColumns = [];

            foreach ($rows as $row) {
                $columnName = $this->metadataField($row, ['Field', 'field']);
                $extra = strtoupper((string) ($this->metadataField($row, ['Extra', 'extra']) ?? ''));

                if ($columnName !== null && str_contains($extra, 'GENERATED')) {
                    $generatedColumns[] = $columnName;
                }
            }

            return $cache[$cacheKey] = array_values(array_unique($generatedColumns));
        }

        if ($driver === 'sqlite') {
            $pragmaTable = str_replace("'", "''", $table);
            $rows = DB::select("PRAGMA table_xinfo('{$pragmaTable}')");
            $generatedColumns = [];

            foreach ($rows as $row) {
                $hidden = (int) ($this->metadataField($row, ['hidden', 'HIDDEN']) ?? 0);
                $columnName = $this->metadataField($row, ['name', 'NAME']);

                if ($hidden >= 2 && $columnName !== null) {
                    $generatedColumns[] = $columnName;
                }
            }

            return $cache[$cacheKey] = array_values(array_unique($generatedColumns));
        }

        return $cache[$cacheKey] = [];
    }

    /**
     * @param  list<string>  $candidates
     */
    protected function metadataField(object $row, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($row->{$candidate}) && $row->{$candidate} !== '') {
                return (string) $row->{$candidate};
            }
        }

        foreach (get_object_vars($row) as $key => $value) {
            foreach ($candidates as $candidate) {
                if (strcasecmp((string) $key, $candidate) === 0 && $value !== null && $value !== '') {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    protected function ensurePortableBookingSchema(): void
    {
        if (! $this->isPortableBookingTestRuntime()) {
            return;
        }

        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table): void {
                $table->increments('branch_id');
                $table->string('branch_code', 50)->unique();
                $table->string('branch_name', 150);
                $table->string('description', 400)->nullable();
                $table->string('timezone', 64)->nullable();
                $table->string('currency', 10)->default('VND');
                $table->json('business_hours')->nullable();
                $table->json('closure_windows')->nullable();
                $table->json('booking_policy')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->unsignedBigInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'business_hours')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->json('business_hours')->nullable();
            });
        }

        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'closure_windows')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->json('closure_windows')->nullable();
            });
        }

        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'booking_policy')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->json('booking_policy')->nullable();
            });
        }

        if (Schema::hasTable('branches') && ! DB::table('branches')->exists()) {
            DB::table('branches')->insert([
                'branch_id' => 1,
                'branch_code' => 'MAIN',
                'branch_name' => 'Main Branch',
                'description' => 'Single-site compatibility default branch.',
                'timezone' => 'UTC',
                'currency' => 'VND',
                'business_hours' => json_encode($this->defaultBranchFixtureBusinessHours(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'closure_windows' => null,
                'booking_policy' => json_encode($this->defaultBranchFixtureBookingPolicy(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_active' => true,
                'is_default' => true,
                'row_version' => 1,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
        }

        if (Schema::hasTable('branches')) {
            DB::table('branches')
                ->where('branch_id', 1)
                ->update([
                    'timezone' => 'UTC',
                    'business_hours' => json_encode($this->defaultBranchFixtureBusinessHours(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'booking_policy' => json_encode($this->defaultBranchFixtureBookingPolicy(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'is_default' => true,
                    'updated_at' => now('UTC'),
                ]);

            DB::table('branches')
                ->where('branch_id', '<>', 1)
                ->update([
                    'is_default' => false,
                ]);
        }

        if (! Schema::hasTable('feature_flags')) {
            Schema::create('feature_flags', function (Blueprint $table): void {
                $table->bigIncrements('feature_flag_id');
                $table->string('feature_key', 120);
                $table->string('environment', 40)->default('*');
                $table->unsignedInteger('branch_id')->default(0);
                $table->boolean('enabled')->default(false);
                $table->string('reason', 500)->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedBigInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['feature_key', 'environment', 'branch_id'], 'uq_feature_flags__feature_key__environment__branch_id');
                $table->index(['environment', 'branch_id'], 'idx_feature_flags__environment__branch_id');
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->increments('role_id');
                $table->string('role_name')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loyalty_tiers')) {
            Schema::create('loyalty_tiers', function (Blueprint $table): void {
                $table->increments('tier_id');
                $table->string('tier_code')->unique();
                $table->string('tier_name');
                $table->integer('min_points')->default(0);
                $table->text('benefits_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('username')->unique();
                $table->string('password_hash')->nullable();
                $table->string('full_name');
                $table->string('email')->nullable()->unique();
                $table->string('phone')->nullable();
                $table->unsignedInteger('role_id')->nullable();
                $table->unsignedInteger('current_tier_id')->nullable();
                $table->string('language_pref', 10)->default('vn');
                $table->boolean('is_deleted')->default(false);
                $table->dateTime('privacy_anonymized_at')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'privacy_anonymized_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dateTime('privacy_anonymized_at')->nullable();
            });
        }

        if (! Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table): void {
                $table->increments('bank_account_id');
                $table->unsignedInteger('user_id');
                $table->string('bank_account_number');
                $table->string('bank_name')->nullable();
                $table->string('account_holder_name')->nullable();
                $table->boolean('is_default')->default(false);
                $table->unsignedInteger('default_user_id')->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('user_auth_tokens')) {
            Schema::create('user_auth_tokens', function (Blueprint $table): void {
                $table->bigIncrements('token_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->string('purpose', 30);
                $table->string('channel', 20);
                $table->string('recipient', 200);
                $table->char('token_hash', 64)->unique();
                $table->char('otp_hash', 64)->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->unsignedInteger('max_attempts')->default(5);
                $table->dateTime('expires_at');
                $table->dateTime('used_at')->nullable();
                $table->binary('created_ip')->nullable();
                $table->string('user_agent')->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('customer_access_sessions')) {
            Schema::create('customer_access_sessions', function (Blueprint $table): void {
                $table->bigIncrements('access_session_id');
                $table->unsignedInteger('user_id');
                $table->string('session_id')->nullable();
                $table->string('guest_name')->nullable();
                $table->string('phone')->nullable();
                $table->char('token_hash', 64)->unique();
                $table->char('token_last_eight', 8)->nullable();
                $table->text('session_meta_json')->nullable();
                $table->dateTime('expires_at');
                $table->dateTime('last_used_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->binary('created_ip')->nullable();
                $table->string('user_agent')->nullable();
                $table->unsignedBigInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (Schema::hasTable('customer_access_sessions') && ! Schema::hasColumn('customer_access_sessions', 'session_id')) {
            Schema::table('customer_access_sessions', function (Blueprint $table): void {
                $table->string('session_id')->nullable();
            });
        }

        if (Schema::hasTable('customer_access_sessions') && ! Schema::hasColumn('customer_access_sessions', 'guest_name')) {
            Schema::table('customer_access_sessions', function (Blueprint $table): void {
                $table->string('guest_name')->nullable();
            });
        }

        if (Schema::hasTable('customer_access_sessions') && ! Schema::hasColumn('customer_access_sessions', 'phone')) {
            Schema::table('customer_access_sessions', function (Blueprint $table): void {
                $table->string('phone')->nullable();
            });
        }

        if (! Schema::hasTable('customer_privacy_requests')) {
            Schema::create('customer_privacy_requests', function (Blueprint $table): void {
                $table->bigIncrements('customer_privacy_request_id');
                $table->unsignedInteger('user_id');
                $table->string('request_type', 30);
                $table->string('status', 30);
                $table->string('requested_by_actor_type', 40)->nullable();
                $table->unsignedInteger('requested_by_user_id')->nullable();
                $table->string('requested_via', 30)->nullable();
                $table->string('reason', 500)->nullable();
                $table->unsignedInteger('reviewed_by')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->string('resolution_notes', 500)->nullable();
                $table->json('result_summary_json')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('user_points')) {
            Schema::create('user_points', function (Blueprint $table): void {
                $table->unsignedInteger('user_id')->primary();
                $table->integer('total_points')->default(0);
                $table->dateTime('last_updated')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('row_version')->default(1);
            });
        }

        if (! Schema::hasTable('user_tier_history')) {
            Schema::create('user_tier_history', function (Blueprint $table): void {
                $table->increments('history_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('from_tier_id')->nullable();
                $table->unsignedInteger('to_tier_id')->nullable();
                $table->string('reason')->nullable();
                $table->dateTime('effective_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('table_templates')) {
            Schema::create('table_templates', function (Blueprint $table): void {
                $table->increments('template_id');
                $table->string('template_code')->unique();
                $table->integer('seats')->default(4);
                $table->string('description')->nullable();
            });
        }

        if (! Schema::hasTable('restaurant_tables')) {
            Schema::create('restaurant_tables', function (Blueprint $table): void {
                $table->increments('table_id');
                $table->unsignedInteger('branch_id')->default(1);
                $table->string('table_code')->unique();
                $table->unsignedInteger('template_id')->nullable();
                $table->string('zone')->nullable();
                $table->integer('pos_x')->nullable();
                $table->integer('pos_y')->nullable();
                $table->string('status', 30)->default('Available');
                $table->string('description')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->unsignedInteger('row_version')->default(1);
                $table->decimal('price', 12, 2)->nullable();
                $table->string('qr_payment_token', 64)->nullable()->unique();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('menu_categories')) {
            Schema::create('menu_categories', function (Blueprint $table): void {
                $table->increments('category_id');
                $table->string('name')->unique();
                $table->string('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_deleted')->default(false);
            });
        }

        if (! Schema::hasTable('menu_items')) {
            Schema::create('menu_items', function (Blueprint $table): void {
                $table->increments('item_id');
                $table->unsignedInteger('category_id')->nullable();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('img_url')->nullable();
                $table->boolean('is_available')->default(true);
                $table->boolean('is_preorder_enabled')->default(false);
                $table->unsignedInteger('preorder_quota_per_day')->nullable();
                $table->unsignedInteger('preorder_cutoff_minutes')->default(0);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (Schema::hasTable('menu_items') && ! Schema::hasColumn('menu_items', 'is_preorder_enabled')) {
            Schema::table('menu_items', function (Blueprint $table): void {
                $table->boolean('is_preorder_enabled')->default(false);
            });
        }

        if (Schema::hasTable('menu_items') && ! Schema::hasColumn('menu_items', 'preorder_quota_per_day')) {
            Schema::table('menu_items', function (Blueprint $table): void {
                $table->unsignedInteger('preorder_quota_per_day')->nullable();
            });
        }

        if (Schema::hasTable('menu_items') && ! Schema::hasColumn('menu_items', 'preorder_cutoff_minutes')) {
            Schema::table('menu_items', function (Blueprint $table): void {
                $table->unsignedInteger('preorder_cutoff_minutes')->default(0);
            });
        }

        if (! Schema::hasTable('menu_item_prices')) {
            Schema::create('menu_item_prices', function (Blueprint $table): void {
                $table->increments('price_id');
                $table->unsignedInteger('item_id');
                $table->decimal('price', 12, 2)->default(0);
                $table->string('currency', 10)->default('VND');
                $table->dateTime('effective_from');
                $table->dateTime('effective_to')->nullable();
            });
        }

        if (! Schema::hasTable('ingredients')) {
            Schema::create('ingredients', function (Blueprint $table): void {
                $table->increments('ingredient_id');
                $table->string('code')->nullable()->unique();
                $table->string('name');
                $table->string('unit_code', 20)->default('unit');
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        } elseif (! Schema::hasColumn('ingredients', 'row_version')) {
            Schema::table('ingredients', function (Blueprint $table): void {
                $table->unsignedBigInteger('row_version')->default(1);
            });
        }

        if (! Schema::hasTable('menu_item_recipes')) {
            Schema::create('menu_item_recipes', function (Blueprint $table): void {
                $table->increments('recipe_line_id');
                $table->unsignedInteger('item_id');
                $table->unsignedInteger('ingredient_id');
                $table->decimal('quantity', 14, 3);
                $table->string('unit_code', 20);
                $table->integer('sort_order')->default(0);
                $table->string('notes', 255)->nullable();
                $table->unsignedBigInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['item_id', 'ingredient_id']);
            });
        } elseif (! Schema::hasColumn('menu_item_recipes', 'row_version')) {
            Schema::table('menu_item_recipes', function (Blueprint $table): void {
                $table->unsignedBigInteger('row_version')->default(1);
            });
        }

        if (! Schema::hasTable('ingredient_stock_movements')) {
            Schema::create('ingredient_stock_movements', function (Blueprint $table): void {
                $table->bigIncrements('movement_id');
                $table->unsignedInteger('branch_id')->default(1);
                $table->unsignedInteger('ingredient_id');
                $table->string('movement_type', 40);
                $table->decimal('quantity_delta', 14, 3);
                $table->string('unit_code', 20);
                $table->string('reference_type', 50)->nullable();
                $table->string('reference_id', 64)->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->unique(['reference_type', 'reference_id'], 'uq_ingredient_stock_movements__reference');
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('setting_key', 100)->primary();
                $table->json('value_json');
                $table->unsignedInteger('updated_by')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('staff_api_keys')) {
            Schema::create('staff_api_keys', function (Blueprint $table): void {
                $table->bigIncrements('staff_api_key_id');
                $table->unsignedInteger('user_id');
                $table->string('label', 100);
                $table->char('key_hash', 64)->unique();
                $table->dateTime('last_used_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('staff_branch_assignments')) {
            Schema::create('staff_branch_assignments', function (Blueprint $table): void {
                $table->bigIncrements('staff_branch_assignment_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('branch_id');
                $table->boolean('is_primary')->default(false);
                $table->dateTime('assigned_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['user_id', 'branch_id'], 'uq_staff_branch_assignments__user_id__branch_id');
                $table->index(['branch_id', 'revoked_at'], 'idx_staff_branch_assignments__branch_id__revoked_at');
                $table->index(['user_id', 'revoked_at', 'is_primary'], 'idx_staff_branch_assignments__user_id__revoked_at__primary');
            });
        }

        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table): void {
                $table->increments('supplier_id');
                $table->string('code', 50)->nullable()->unique();
                $table->string('name', 200);
                $table->string('contact_name', 120)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('notes', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        } elseif (! Schema::hasColumn('suppliers', 'row_version')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->unsignedBigInteger('row_version')->default(1);
            });
        }

        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table): void {
                $table->increments('purchase_order_id');
                $table->unsignedInteger('branch_id')->default(1);
                $table->unsignedInteger('supplier_id');
                $table->string('order_code', 50)->unique();
                $table->string('purchase_order_status', 30)->default('Draft');
                $table->dateTime('ordered_at')->nullable();
                $table->dateTime('expected_at')->nullable();
                $table->dateTime('received_at')->nullable();
                $table->string('supplier_reference', 100)->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedBigInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        } elseif (! Schema::hasColumn('purchase_orders', 'row_version')) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('row_version')->default(1);
            });
        }

        if (! Schema::hasTable('purchase_order_lines')) {
            Schema::create('purchase_order_lines', function (Blueprint $table): void {
                $table->increments('po_line_id');
                $table->unsignedInteger('purchase_order_id');
                $table->unsignedInteger('ingredient_id');
                $table->decimal('ordered_quantity', 14, 3);
                $table->decimal('received_quantity', 14, 3)->default(0);
                $table->string('unit_code', 20);
                $table->decimal('unit_cost', 14, 3)->nullable();
                $table->string('notes', 255)->nullable();
                $table->integer('sort_order')->default(0);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['purchase_order_id', 'ingredient_id']);
            });
        }

        if (! Schema::hasTable('purchase_receipts')) {
            Schema::create('purchase_receipts', function (Blueprint $table): void {
                $table->increments('receipt_id');
                $table->unsignedInteger('branch_id')->default(1);
                $table->unsignedInteger('purchase_order_id');
                $table->string('receipt_code', 50)->unique();
                $table->string('receipt_status', 20)->default('Posted');
                $table->dateTime('received_at')->nullable();
                $table->string('supplier_document_no', 100)->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('purchase_receipt_lines')) {
            Schema::create('purchase_receipt_lines', function (Blueprint $table): void {
                $table->bigIncrements('receipt_line_id');
                $table->unsignedInteger('receipt_id');
                $table->unsignedInteger('purchase_order_line_id');
                $table->unsignedInteger('ingredient_id');
                $table->decimal('received_quantity', 14, 3);
                $table->string('unit_code', 20);
                $table->decimal('unit_cost', 14, 3)->nullable();
                $table->unsignedBigInteger('stock_movement_id')->nullable();
                $table->string('notes', 255)->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('kitchen_stations')) {
            Schema::create('kitchen_stations', function (Blueprint $table): void {
                $table->increments('station_id');
                $table->unsignedInteger('branch_id')->default(1);
                $table->string('code', 50)->unique();
                $table->string('name', 120);
                $table->string('description', 500)->nullable();
                $table->string('output_mode', 20)->default('KDS');
                $table->string('printer_target', 120)->nullable();
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }
        if (Schema::hasTable('kitchen_stations') && ! Schema::hasColumn('kitchen_stations', 'branch_id')) {
            Schema::table('kitchen_stations', function (Blueprint $table): void {
                $table->unsignedInteger('branch_id')->default(1)->after('station_id');
            });
        }

        if (! Schema::hasTable('kitchen_station_category_routes')) {
            Schema::create('kitchen_station_category_routes', function (Blueprint $table): void {
                $table->increments('route_id');
                $table->unsignedInteger('station_id');
                $table->unsignedInteger('branch_id')->default(1);
                $table->unsignedInteger('category_id');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['branch_id', 'category_id']);
            });
        }
        if (Schema::hasTable('kitchen_station_category_routes') && ! Schema::hasColumn('kitchen_station_category_routes', 'branch_id')) {
            Schema::table('kitchen_station_category_routes', function (Blueprint $table): void {
                $table->unsignedInteger('branch_id')->default(1)->after('station_id');
            });
        }

        if (! Schema::hasTable('kitchen_order_item_tickets')) {
            Schema::create('kitchen_order_item_tickets', function (Blueprint $table): void {
                $table->increments('ticket_id');
                $table->unsignedInteger('station_id');
                $table->unsignedInteger('order_id');
                $table->unsignedInteger('reservation_id');
                $table->unsignedInteger('order_item_id');
                $table->unsignedInteger('item_id');
                $table->unsignedInteger('category_id')->nullable();
                $table->unsignedInteger('route_id')->nullable();
                $table->string('route_source', 20)->default('Category');
                $table->string('output_mode', 20)->default('KDS');
                $table->string('printer_target', 120)->nullable();
                $table->string('ticket_status', 20)->default('Queued');
                $table->dateTime('first_dispatched_at')->nullable();
                $table->dateTime('fired_at')->nullable();
                $table->dateTime('ready_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->dateTime('last_recalled_at')->nullable();
                $table->unsignedInteger('dispatch_count')->default(1);
                $table->unsignedInteger('recall_count')->default(0);
                $table->string('ticket_notes', 500)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedBigInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique('order_item_id');
            });
        } elseif (! Schema::hasColumn('kitchen_order_item_tickets', 'row_version')) {
            Schema::table('kitchen_order_item_tickets', function (Blueprint $table): void {
                $table->unsignedBigInteger('row_version')->default(1);
            });
        }

        if (! Schema::hasTable('cashier_shifts')) {
            Schema::create('cashier_shifts', function (Blueprint $table): void {
                $table->increments('cashier_shift_id');
                $table->unsignedInteger('branch_id')->default(1);
                $table->string('shift_code', 50)->unique();
                $table->unsignedInteger('cashier_user_id');
                $table->unsignedInteger('active_cashier_user_id')->nullable()->unique();
                $table->string('status', 20)->default('Open');
                $table->string('currency', 10)->default('VND');
                $table->string('terminal_code', 50)->nullable();
                $table->decimal('opening_float_amount', 14, 2)->default(0);
                $table->decimal('expected_cash_amount', 14, 2)->nullable();
                $table->decimal('actual_cash_amount', 14, 2)->nullable();
                $table->decimal('cash_discrepancy_amount', 14, 2)->nullable();
                $table->dateTime('opened_at');
                $table->dateTime('closed_at')->nullable();
                $table->unsignedInteger('opened_by')->nullable();
                $table->unsignedInteger('closed_by')->nullable();
                $table->string('opening_note', 500)->nullable();
                $table->string('closing_note', 500)->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('billing_invoices')) {
            Schema::create('billing_invoices', function (Blueprint $table): void {
                $table->bigIncrements('billing_invoice_id');
                $table->unsignedInteger('reservation_id')->unique();
                $table->string('invoice_number', 80)->unique();
                $table->string('invoice_status', 20)->default('Issued');
                $table->decimal('subtotal_amount', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('currency', 10)->default('VND');
                $table->string('tax_code', 40)->nullable();
                $table->string('tax_name', 120)->nullable();
                $table->decimal('tax_rate_percentage', 6, 3)->default(0);
                $table->boolean('prices_include_tax')->default(true);
                $table->decimal('taxable_amount', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->string('seller_name', 150);
                $table->string('seller_tax_id', 50)->nullable();
                $table->string('seller_address', 255)->nullable();
                $table->dateTime('issued_at');
                $table->unsignedInteger('issued_by')->nullable();
                $table->dateTime('voided_at')->nullable();
                $table->unsignedInteger('voided_by')->nullable();
                $table->json('metadata_json')->nullable();
                $table->unsignedBigInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('reporting_daily_sales_snapshots')) {
            Schema::create('reporting_daily_sales_snapshots', function (Blueprint $table): void {
                $table->bigIncrements('snapshot_id');
                $table->unsignedInteger('branch_id');
                $table->date('business_date');
                $table->string('currency', 10)->default('VND');
                $table->unsignedInteger('billed_reservation_count')->default(0);
                $table->unsignedInteger('billed_guest_count')->default(0);
                $table->decimal('gross_bill_amount', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('billed_total_amount', 14, 2)->default(0);
                $table->unsignedInteger('invoice_issued_count')->default(0);
                $table->decimal('invoiced_total_amount', 14, 2)->default(0);
                $table->decimal('invoiced_tax_amount', 14, 2)->default(0);
                $table->unsignedInteger('payment_row_count')->default(0);
                $table->unsignedInteger('refund_row_count')->default(0);
                $table->decimal('captured_amount', 14, 2)->default(0);
                $table->decimal('refunded_amount', 14, 2)->default(0);
                $table->decimal('net_paid_amount', 14, 2)->default(0);
                $table->decimal('deposit_net_amount', 14, 2)->default(0);
                $table->decimal('final_net_amount', 14, 2)->default(0);
                $table->unsignedInteger('cashier_shift_closed_count')->default(0);
                $table->decimal('cash_discrepancy_amount', 14, 2)->default(0);
                $table->dateTime('refreshed_at')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['branch_id', 'business_date', 'currency'], 'uq_rpt_daily_sales__branch_date_currency');
            });
        }

        if (! Schema::hasTable('reporting_daily_operation_snapshots')) {
            Schema::create('reporting_daily_operation_snapshots', function (Blueprint $table): void {
                $table->bigIncrements('snapshot_id');
                $table->unsignedInteger('branch_id');
                $table->date('business_date');
                $table->unsignedInteger('scheduled_reservation_count')->default(0);
                $table->unsignedInteger('scheduled_guest_count')->default(0);
                $table->unsignedInteger('scheduled_minutes_total')->default(0);
                $table->unsignedInteger('checked_in_count')->default(0);
                $table->unsignedInteger('completed_count')->default(0);
                $table->unsignedInteger('cancelled_count')->default(0);
                $table->unsignedInteger('no_show_count')->default(0);
                $table->unsignedInteger('turn_count')->default(0);
                $table->unsignedInteger('turn_minutes_total')->default(0);
                $table->unsignedInteger('waiting_list_created_count')->default(0);
                $table->unsignedInteger('waiting_list_notified_count')->default(0);
                $table->unsignedInteger('waiting_list_seated_count')->default(0);
                $table->unsignedInteger('waiting_list_cancelled_count')->default(0);
                $table->unsignedInteger('waiting_list_confirmed_arrival_count')->default(0);
                $table->dateTime('refreshed_at')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['branch_id', 'business_date'], 'uq_rpt_daily_ops__branch_date');
            });
        }

        if (! Schema::hasTable('reporting_daily_inventory_movement_snapshots')) {
            Schema::create('reporting_daily_inventory_movement_snapshots', function (Blueprint $table): void {
                $table->bigIncrements('snapshot_id');
                $table->unsignedInteger('branch_id');
                $table->date('business_date');
                $table->unsignedInteger('ingredient_id');
                $table->string('unit_code', 20);
                $table->unsignedInteger('movement_count')->default(0);
                $table->unsignedInteger('purchase_receipt_movement_count')->default(0);
                $table->decimal('stock_in_quantity', 14, 3)->default(0);
                $table->decimal('stock_out_quantity', 14, 3)->default(0);
                $table->decimal('adjustment_increase_quantity', 14, 3)->default(0);
                $table->decimal('adjustment_decrease_quantity', 14, 3)->default(0);
                $table->decimal('wastage_quantity', 14, 3)->default(0);
                $table->decimal('net_quantity_delta', 14, 3)->default(0);
                $table->dateTime('last_movement_at')->nullable();
                $table->dateTime('refreshed_at')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['branch_id', 'business_date', 'ingredient_id', 'unit_code'], 'uq_rpt_daily_inv_move__branch_date_ing_unit');
            });
        }

        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table): void {
                $table->increments('voucher_id');
                $table->string('code')->unique();
                $table->string('description')->nullable();
                $table->string('discount_type', 20);
                $table->decimal('discount_value', 12, 2)->default(0);
                $table->unsignedInteger('free_item_id')->nullable();
                $table->integer('free_item_qty')->nullable();
                $table->integer('max_usage')->nullable();
                $table->integer('max_usage_per_user')->nullable();
                $table->decimal('min_spend', 12, 2)->default(0);
                $table->dateTime('start_date')->nullable();
                $table->dateTime('expiry_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('reservations')) {
            Schema::create('reservations', function (Blueprint $table): void {
                $table->increments('reservation_id');
                $table->unsignedInteger('branch_id')->default(1);
                $table->unsignedInteger('user_id')->nullable();
                $table->string('guest_name')->nullable();
                $table->string('guest_phone', 50)->nullable();
                $table->string('guest_email')->nullable();
                $table->string('reservation_code')->unique();
                $table->dateTime('reserved_at')->nullable();
                $table->dateTime('start_time');
                $table->dateTime('end_time');
                $table->integer('guest_count')->default(1);
                $table->string('status', 30)->default('Confirmed');
                $table->string('source', 30)->nullable();
                $table->dateTime('checked_in_at')->nullable();
                $table->dateTime('checked_out_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->string('cancel_reason')->nullable();
                $table->unsignedInteger('cancelled_by')->nullable();
                $table->dateTime('no_show_at')->nullable();
                $table->decimal('deposit_required_amount', 12, 2)->default(0);
                $table->decimal('deposit_paid_amount', 12, 2)->default(0);
                $table->string('deposit_status', 30)->default('NotRequired');
                $table->unsignedInteger('applied_user_voucher_id')->nullable();
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('final_bill_amount', 12, 2)->nullable();
                $table->string('bill_currency', 10)->nullable()->default('VND');
                $table->dateTime('billed_at')->nullable();
                $table->string('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasColumn('reservations', 'deposit_requirement_acknowledged_at')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->dateTime('deposit_requirement_acknowledged_at')->nullable();
            });
        }

        if (! Schema::hasColumn('reservations', 'guest_name')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->string('guest_name')->nullable()->after('user_id');
            });
        }

        if (! Schema::hasColumn('reservations', 'guest_phone')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->string('guest_phone', 50)->nullable()->after('guest_name');
            });
        }

        if (! Schema::hasColumn('reservations', 'guest_email')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->string('guest_email')->nullable()->after('guest_phone');
            });
        }

        if (! Schema::hasColumn('reservations', 'deposit_intent_status')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->string('deposit_intent_status', 30)->default('None');
            });
        }

        if (! Schema::hasColumn('reservations', 'deposit_intent_submitted_at')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->dateTime('deposit_intent_submitted_at')->nullable();
            });
        }

        if (! Schema::hasColumn('reservations', 'deposit_intent_revoked_at')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->dateTime('deposit_intent_revoked_at')->nullable();
            });
        }

        if (! Schema::hasTable('reservation_tables')) {
            Schema::create('reservation_tables', function (Blueprint $table): void {
                $table->increments('reservation_table_id');
                $table->unsignedInteger('reservation_id');
                $table->unsignedInteger('table_id');
            });
        }

        if (! Schema::hasTable('reservation_orders')) {
            Schema::create('reservation_orders', function (Blueprint $table): void {
                $table->increments('order_id');
                $table->unsignedInteger('reservation_id');
                $table->string('order_type', 20)->default('OnSpot');
                $table->string('status', 20)->default('Active');
                $table->string('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('reservation_order_items')) {
            Schema::create('reservation_order_items', function (Blueprint $table): void {
                $table->increments('order_item_id');
                $table->unsignedInteger('order_id');
                $table->unsignedInteger('item_id');
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->string('currency', 10)->default('VND');
                $table->decimal('line_total', 12, 2)->default(0);
                $table->string('item_name_snapshot')->nullable();
                $table->string('status', 20)->default('Ordered');
                $table->string('notes')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->increments('payment_id');
                $table->unsignedInteger('reservation_id')->nullable();
                $table->unsignedBigInteger('cashier_shift_id')->nullable();
                $table->unsignedInteger('refund_of_payment_id')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('VND');
                $table->string('payment_method', 30)->nullable();
                $table->string('payment_provider', 50)->nullable();
                $table->string('payment_type', 20)->nullable();
                $table->string('status', 20)->nullable();
                $table->string('transaction_code')->nullable();
                $table->string('idempotency_key')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->string('notes')->nullable();
                $table->text('provider_response_json')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->unsignedInteger('branch_id')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('finance_replay_records')) {
            Schema::create('finance_replay_records', function (Blueprint $table): void {
                $table->bigIncrements('finance_replay_record_id');
                $table->string('scope', 80);
                $table->string('aggregate_type', 80);
                $table->unsignedBigInteger('aggregate_id');
                $table->string('idempotency_key', 120);
                $table->string('request_fingerprint', 64)->nullable();
                $table->string('result_type', 80)->nullable();
                $table->unsignedBigInteger('result_id')->nullable();
                $table->text('context_json')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['scope', 'aggregate_type', 'aggregate_id', 'idempotency_key'], 'uq_finance_replay_records__scope_aggregate_key');
                $table->index(['idempotency_key'], 'idx_finance_replay_records__idempotency_key');
                $table->index(['result_type', 'result_id'], 'idx_finance_replay_records__result');
            });
        }

        if (! Schema::hasTable('user_vouchers')) {
            Schema::create('user_vouchers', function (Blueprint $table): void {
                $table->increments('user_voucher_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('voucher_id');
                $table->dateTime('assigned_date')->nullable();
                $table->boolean('is_used')->default(false);
                $table->dateTime('used_date')->nullable();
                $table->unsignedInteger('used_reservation_id')->nullable();
                $table->decimal('used_amount', 12, 2)->nullable();
                $table->string('lock_token')->nullable();
                $table->dateTime('locked_until')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('table_holds')) {
            Schema::create('table_holds', function (Blueprint $table): void {
                $table->string('hold_id')->primary();
                $table->unsignedInteger('branch_id')->default(1);
                $table->string('session_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('confirmed_reservation_id')->nullable();
                $table->dateTime('start_time');
                $table->dateTime('end_time');
                $table->integer('duration_minutes')->default(0);
                $table->string('hold_status', 30)->default('Holding');
                $table->dateTime('expire_at')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (Schema::hasTable('table_holds') && ! Schema::hasColumn('table_holds', 'branch_id')) {
            Schema::table('table_holds', function (Blueprint $table): void {
                $table->unsignedInteger('branch_id')->default(1);
            });
        }

        if (! Schema::hasTable('table_hold_details')) {
            Schema::create('table_hold_details', function (Blueprint $table): void {
                $table->increments('hold_detail_id');
                $table->string('hold_id');
                $table->unsignedInteger('table_id');
            });
        }

        if (! Schema::hasTable('reservation_deposit_payment_sessions')) {
            Schema::create('reservation_deposit_payment_sessions', function (Blueprint $table): void {
                $table->increments('deposit_payment_session_id');
                $table->unsignedInteger('reservation_id');
                $table->unsignedInteger('customer_user_id');
                $table->unsignedInteger('linked_payment_id')->nullable();
                $table->string('provider_code', 50);
                $table->string('provider_session_code')->unique('uq_resv_deposit_sessions__provider_session');
                $table->string('provider_payment_code')->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('VND');
                $table->string('session_status', 30)->default('Created');
                $table->string('settlement_status', 30)->default('NotApplied');
                $table->string('failure_code')->nullable();
                $table->string('failure_message')->nullable();
                $table->text('provider_payload_json')->nullable();
                $table->string('idempotency_key')->nullable();
                $table->dateTime('provider_expires_at')->nullable();
                $table->dateTime('last_reconciled_at')->nullable();
                $table->dateTime('confirmed_at')->nullable();
                $table->dateTime('failed_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->dateTime('expired_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['reservation_id', 'idempotency_key'], 'uq_resv_deposit_sessions__resv_idem');
            });
        }

        if (! Schema::hasTable('reservation_bill_payment_sessions')) {
            Schema::create('reservation_bill_payment_sessions', function (Blueprint $table): void {
                $table->increments('bill_payment_session_id');
                $table->unsignedInteger('reservation_id');
                $table->unsignedInteger('order_id')->nullable();
                $table->unsignedInteger('customer_user_id');
                $table->unsignedInteger('linked_payment_id')->nullable();
                $table->string('provider_code', 50);
                $table->string('provider_session_code')->unique('uq_resv_bill_sessions__provider_session');
                $table->string('provider_payment_code')->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('VND');
                $table->string('session_status', 30)->default('Created');
                $table->string('settlement_status', 30)->default('NotApplied');
                $table->string('failure_code')->nullable();
                $table->string('failure_message')->nullable();
                $table->text('provider_payload_json')->nullable();
                $table->string('idempotency_key')->nullable();
                $table->dateTime('provider_expires_at')->nullable();
                $table->dateTime('last_reconciled_at')->nullable();
                $table->dateTime('confirmed_at')->nullable();
                $table->dateTime('failed_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->dateTime('expired_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['reservation_id', 'idempotency_key'], 'uq_resv_bill_sessions__resv_idem');
            });
        }

        if (! Schema::hasTable('payment_provider_webhook_receipts')) {
            Schema::create('payment_provider_webhook_receipts', function (Blueprint $table): void {
                $table->increments('payment_provider_webhook_receipt_id');
                $table->string('provider_code', 50);
                $table->string('provider_event_code', 120);
                $table->string('provider_session_code', 120);
                $table->string('payment_scope', 30)->nullable();
                $table->string('event_type', 120)->default('payment.session.updated');
                $table->string('delivery_status', 30)->default('Received');
                $table->string('request_signature')->nullable();
                $table->text('request_headers_json')->nullable();
                $table->text('request_body')->nullable();
                $table->text('provider_payload_json')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->string('failure_message')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['provider_code', 'provider_event_code'], 'uq_payment_webhooks__provider_event');
                $table->index(['provider_code', 'provider_session_code'], 'idx_payment_webhooks__provider_session');
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->bigIncrements('audit_id');
                $table->unsignedInteger('actor_user_id')->nullable();
                $table->string('actor_type', 40)->nullable();
                $table->string('actor_key', 120)->nullable();
                $table->string('entity_type', 50);
                $table->string('entity_id', 64);
                $table->string('action', 50);
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->json('summary_json')->nullable();
                $table->json('meta_json')->nullable();
                $table->string('request_id', 64)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->index(['entity_type', 'entity_id'], 'idx_audit_logs__entity_type__entity_id');
                $table->index(['actor_user_id', 'created_at'], 'idx_audit_logs__actor_user_id__created_at');
                $table->index(['actor_type', 'created_at'], 'idx_audit_logs__actor_type__created_at');
                $table->index(['action', 'created_at'], 'idx_audit_logs__action__created_at');
                $table->index(['request_id'], 'idx_audit_logs__request_id');
                $table->index(['created_at'], 'idx_audit_logs__created_at');
            });
        }

        if (! Schema::hasTable('audit_log_subjects')) {
            Schema::create('audit_log_subjects', function (Blueprint $table): void {
                $table->bigIncrements('audit_subject_id');
                $table->unsignedBigInteger('audit_id');
                $table->string('subject_type', 50);
                $table->string('subject_id', 64);
                $table->string('subject_role', 32)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->index(['subject_type', 'subject_id', 'audit_id'], 'idx_audit_log_subjects__subject_type__subject_id__audit_id');
                $table->index(['audit_id'], 'idx_audit_log_subjects__audit_id');
            });
        }

        if (! Schema::hasTable('waiting_list')) {
            Schema::create('waiting_list', function (Blueprint $table): void {
                $table->increments('waiting_id');
                $table->unsignedInteger('branch_id')->default(1);
                $table->unsignedInteger('user_id')->nullable();
                $table->string('customer_session_id')->nullable();
                $table->string('guest_name')->nullable();
                $table->string('phone')->nullable();
                $table->unsignedInteger('guest_count')->default(1);
                $table->dateTime('requested_at')->nullable();
                $table->string('status', 30)->default('Waiting');
                $table->integer('priority')->default(0);
                $table->dateTime('notified_at')->nullable();
                $table->dateTime('notify_expires_at')->nullable();
                $table->string('customer_response_status', 30)->nullable();
                $table->dateTime('customer_responded_at')->nullable();
                $table->dateTime('customer_confirmed_arrival_at')->nullable();
                $table->unsignedInteger('notified_by')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('seated_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->string('cancel_reason')->nullable();
                $table->string('notes')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->unsignedBigInteger('row_version')->default(1);

                $table->index(['customer_session_id', 'requested_at']);
                $table->index(['status', 'created_at']);
                $table->index(['status', 'priority', 'requested_at']);
                $table->index('notify_expires_at');
                $table->index('notified_by');
                $table->index('updated_by');
            });
        }

        if (Schema::hasTable('waiting_list') && ! Schema::hasColumn('waiting_list', 'customer_response_status')) {
            Schema::table('waiting_list', function (Blueprint $table): void {
                $table->string('customer_response_status', 30)->nullable();
            });
        }

        if (Schema::hasTable('waiting_list') && ! Schema::hasColumn('waiting_list', 'customer_responded_at')) {
            Schema::table('waiting_list', function (Blueprint $table): void {
                $table->dateTime('customer_responded_at')->nullable();
            });
        }

        if (Schema::hasTable('waiting_list') && ! Schema::hasColumn('waiting_list', 'customer_confirmed_arrival_at')) {
            Schema::table('waiting_list', function (Blueprint $table): void {
                $table->dateTime('customer_confirmed_arrival_at')->nullable();
            });
        }

        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table): void {
                $table->uuid('conversation_id')->primary();
                $table->unsignedInteger('branch_id')->default(1);
                $table->unsignedInteger('user_id')->nullable();
                $table->string('customer_session_id', 100)->nullable();
                $table->string('session_id', 100)->nullable();
                $table->string('channel', 50)->default('WebChat');
                $table->string('status', 20)->default('Open');
                $table->string('workflow_state', 40)->default('Open');
                $table->string('workflow_state_reason', 100)->nullable();
                $table->string('intent_detected', 200)->nullable();
                $table->unsignedInteger('linked_reservation_id')->nullable();
                $table->unsignedInteger('linked_waiting_list_id')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('workflow_state_changed_at')->nullable();
                $table->dateTime('first_triaged_at')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->dateTime('closed_at')->nullable();

                $table->index(['user_id', 'status']);
                $table->index(['branch_id', 'status', 'created_at'], 'idx_conversations__branch_id__status__created_at');
                $table->index(['branch_id', 'workflow_state', 'created_at'], 'idx_conversations__branch_id__workflow_state__created_at');
                $table->index(['channel', 'created_at'], 'idx_conversations__channel__created_at');
                $table->index('linked_reservation_id', 'fk_conversations__linked_reservation_id__reservations');
                $table->index('linked_waiting_list_id', 'fk_conversations__linked_waiting_list_id__waiting_list');
            });
        }

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'branch_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->unsignedInteger('branch_id')->default(1);
            });
        }

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'linked_reservation_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->unsignedInteger('linked_reservation_id')->nullable();
            });
        }

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'linked_waiting_list_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->unsignedInteger('linked_waiting_list_id')->nullable();
            });
        }

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'workflow_state')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->string('workflow_state', 40)->default('Open')->after('status');
            });
        }

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'workflow_state_reason')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->string('workflow_state_reason', 100)->nullable()->after('workflow_state');
            });
        }

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'workflow_state_changed_at')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->dateTime('workflow_state_changed_at')->nullable()->after('created_at');
            });
        }

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'first_triaged_at')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->dateTime('first_triaged_at')->nullable()->after('workflow_state_changed_at');
            });
        }

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'resolved_at')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->dateTime('resolved_at')->nullable()->after('first_triaged_at');
            });
        }

        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table): void {
                $table->bigIncrements('message_id');
                $table->uuid('conversation_id');
                $table->string('sender', 20);
                $table->unsignedInteger('sender_id')->nullable();
                $table->longText('message_text');
                $table->string('message_type', 50)->default('text');
                $table->boolean('is_internal_note')->default(false);
                $table->string('attachment_url', 1000)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->boolean('is_processed')->default(false);
                $table->string('processing_status', 50)->nullable();
                $table->decimal('confidence', 5, 4)->nullable();
                $table->unsignedInteger('related_reservation_id')->nullable();
                $table->unsignedInteger('related_order_id')->nullable();

                $table->index(['conversation_id', 'created_at']);
                $table->index(['conversation_id', 'is_internal_note', 'created_at'], 'idx_conv_msgs__conv_note_created_at');
                $table->index('is_processed');
                $table->index('sender_id');
                $table->index('related_reservation_id');
                $table->index('related_order_id');
            });
        }

        if (Schema::hasTable('conversation_messages') && ! Schema::hasColumn('conversation_messages', 'is_internal_note')) {
            Schema::table('conversation_messages', function (Blueprint $table): void {
                $table->boolean('is_internal_note')->default(false);
            });
        }

        if (! Schema::hasTable('conversation_files')) {
            Schema::create('conversation_files', function (Blueprint $table): void {
                $table->bigIncrements('file_id');
                $table->unsignedBigInteger('message_id');
                $table->string('file_url', 1000);
                $table->string('mime_type', 100)->nullable();
                $table->dateTime('created_at')->nullable();

                $table->index('message_id');
            });
        }

        if (! Schema::hasTable('conversation_events')) {
            Schema::create('conversation_events', function (Blueprint $table): void {
                $table->bigIncrements('event_id');
                $table->uuid('conversation_id');
                $table->string('event_type', 100);
                $table->unsignedInteger('event_by_user_id')->nullable();
                $table->json('event_data')->nullable();
                $table->dateTime('created_at')->nullable();

                $table->index('conversation_id');
                $table->index('event_by_user_id');
            });
        }

        if (! Schema::hasTable('conversation_analyses')) {
            Schema::create('conversation_analyses', function (Blueprint $table): void {
                $table->increments('analysis_id');
                $table->uuid('conversation_id');
                $table->string('analyzer_name', 200)->nullable();
                $table->boolean('is_spam')->default(false);
                $table->decimal('quality_score', 5, 4)->nullable();
                $table->json('extracted_info')->nullable();
                $table->dateTime('created_at')->nullable();

                $table->index('conversation_id');
            });
        }

        if (! Schema::hasTable('agent_assignments')) {
            Schema::create('agent_assignments', function (Blueprint $table): void {
                $table->increments('assignment_id');
                $table->uuid('conversation_id');
                $table->unsignedInteger('agent_user_id');
                $table->dateTime('assigned_at')->nullable();
                $table->dateTime('released_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->uuid('active_conversation_id')->nullable()->storedAs('CASE WHEN is_active = 1 THEN conversation_id ELSE NULL END');
                $table->string('notes', 500)->nullable();

                $table->index(['conversation_id', 'is_active']);
                $table->index('agent_user_id');
                $table->unique('active_conversation_id', 'uq_agent_assignments__active_conversation_id');
            });
        }

        if (Schema::hasTable('agent_assignments') && ! Schema::hasColumn('agent_assignments', 'notes')) {
            Schema::table('agent_assignments', function (Blueprint $table): void {
                $table->string('notes', 500)->nullable();
            });
        }

        if (! Schema::hasTable('message_entities')) {
            Schema::create('message_entities', function (Blueprint $table): void {
                $table->bigIncrements('message_entity_id');
                $table->unsignedBigInteger('message_id');
                $table->string('entity_type', 100);
                $table->string('entity_text', 400);
                $table->string('entity_normalized', 400)->nullable();
                $table->json('extra_json')->nullable();
                $table->dateTime('created_at')->nullable();

                $table->index('message_id');
                $table->index('entity_type');
            });
        }

        if (! Schema::hasTable('conversation_aggregates')) {
            Schema::create('conversation_aggregates', function (Blueprint $table): void {
                $table->increments('agg_id');
                $table->date('agg_date');
                $table->unsignedTinyInteger('hour')->nullable();
                $table->string('channel', 50)->nullable();
                $table->unsignedInteger('total_conversations')->default(0);
                $table->unsignedInteger('total_messages')->default(0);
                $table->unsignedInteger('total_spam')->default(0);
                $table->unsignedInteger('orders_extracted')->default(0);
                $table->json('top_items')->nullable();
                $table->dateTime('created_at')->nullable();

                $table->unique(['agg_date', 'hour', 'channel'], 'uq_conversation_aggregates__agg_date__hour__channel');
            });
        }

        if (! Schema::hasTable('loyalty_point_transactions')) {
            Schema::create('loyalty_point_transactions', function (Blueprint $table): void {
                $table->increments('txn_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('reservation_id')->nullable();
                $table->string('txn_type', 20);
                $table->integer('points');
                $table->decimal('amount_basis', 12, 2)->nullable();
                $table->string('currency', 10)->default('VND');
                $table->string('reason')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
            });
        }
    }

    protected function ensureNotificationOutboxSchema(): void
    {
        if (! $this->isPortableBookingTestRuntime()) {
            return;
        }

        if (! Schema::hasTable('notification_outbox')) {
            Schema::create('notification_outbox', function (Blueprint $table): void {
                $table->increments('outbox_id');
                $table->string('channel');
                $table->string('recipient');
                $table->unsignedInteger('recipient_user_id')->nullable();
                $table->string('template_key');
                $table->string('idempotency_key')->nullable()->unique();
                $table->string('dedupe_key')->nullable();
                $table->text('payload_json');
                $table->string('status')->default('Pending');
                $table->string('processing_token')->nullable();
                $table->dateTime('locked_until')->nullable();
                $table->string('locked_by')->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->dateTime('last_attempted_at')->nullable();
                $table->dateTime('next_retry_at')->nullable();
                $table->string('last_error')->nullable();
                $table->unsignedInteger('related_reservation_id')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('sent_at')->nullable();
            });
        }

        if (! Schema::hasColumn('notification_outbox', 'recipient_user_id')) {
            Schema::table('notification_outbox', function (Blueprint $table): void {
                $table->unsignedInteger('recipient_user_id')->nullable();
            });
        }

        if (! Schema::hasColumn('notification_outbox', 'dedupe_key')) {
            Schema::table('notification_outbox', function (Blueprint $table): void {
                $table->string('dedupe_key')->nullable();
            });
        }

        if (! Schema::hasColumn('notification_outbox', 'last_attempted_at')) {
            Schema::table('notification_outbox', function (Blueprint $table): void {
                $table->dateTime('last_attempted_at')->nullable();
            });
        }

        $this->ensureIndexIfMissing('notification_outbox', 'idx_notification_outbox__dedupe_key__created_at', ['dedupe_key', 'created_at']);
        $this->ensureIndexIfMissing('notification_outbox', 'idx_notification_outbox__recipient_user_id__status__created_at', ['recipient_user_id', 'status', 'created_at']);

        if (! Schema::hasTable('notification_delivery_attempts')) {
            Schema::create('notification_delivery_attempts', function (Blueprint $table): void {
                $table->increments('attempt_id');
                $table->unsignedInteger('outbox_id');
                $table->string('channel');
                $table->string('provider_key')->nullable();
                $table->unsignedInteger('attempt_number');
                $table->string('status');
                $table->string('recipient');
                $table->string('provider_message_id')->nullable();
                $table->string('provider_status')->nullable();
                $table->string('error_code')->nullable();
                $table->string('error_message')->nullable();
                $table->text('request_payload_json')->nullable();
                $table->text('response_payload_json')->nullable();
                $table->dateTime('attempted_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }

        $this->ensureIndexIfMissing('notification_delivery_attempts', 'idx_notif_delivery_attempts__status__attempted_at', ['status', 'attempted_at']);
        $this->ensureIndexIfMissing('notification_delivery_attempts', 'idx_notif_delivery_attempts__channel__status__attempted_at', ['channel', 'status', 'attempted_at']);
        $this->ensureIndexIfMissing('notification_delivery_attempts', 'idx_notif_delivery_attempts__provider_key__attempted_at', ['provider_key', 'attempted_at']);

        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table): void {
                $table->increments('notification_preference_id');
                $table->unsignedInteger('user_id');
                $table->string('channel');
                $table->boolean('is_enabled')->default(true);
                $table->unsignedSmallInteger('quiet_hours_start_minute')->nullable();
                $table->unsignedSmallInteger('quiet_hours_end_minute')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['user_id', 'channel'], 'uq_notification_preferences__user_id__channel');
            });
        }

        $this->ensureIndexIfMissing('notification_preferences', 'idx_notification_preferences__channel__is_enabled', ['channel', 'is_enabled']);

        if (! Schema::hasTable('preorders')) {
            Schema::create('preorders', function (Blueprint $table): void {
                $table->increments('preorder_id');
                $table->unsignedInteger('reservation_id')->unique();
                $table->unsignedInteger('customer_user_id')->nullable();
                $table->string('status', 30)->default('draft');
                $table->string('notes', 500)->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('confirmed_at')->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->dateTime('converted_at')->nullable();
                $table->unsignedBigInteger('row_version')->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('preorder_items')) {
            Schema::create('preorder_items', function (Blueprint $table): void {
                $table->increments('preorder_item_id');
                $table->unsignedInteger('preorder_id');
                $table->unsignedInteger('menu_item_id');
                $table->string('item_name_snapshot', 255);
                $table->decimal('unit_price_snapshot', 13, 2)->default(0);
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('line_total_snapshot', 13, 2)->default(0);
                $table->string('currency', 3)->default('VND');
                $table->string('notes', 500)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        $this->ensureIndexIfMissing('notification_preferences', 'idx_notification_preferences__channel__is_enabled', ['channel', 'is_enabled']);
    }

    /**
     * Keep the lightweight sqlite harness aligned with the MySQL source of truth
     * for the DB-sensitive booking flows that rely on BuildsBookingScenario.
     *
     * This is intentionally narrower than a full MySQL clone: we only mirror the
     * columns, indexes, and uniqueness contracts that materially affect payment,
     * reservation, waiting-list, and table-hold integrity assertions.
     */
    protected function ensurePortableDbSensitiveSchemaParity(): void
    {
        if (! $this->isPortableBookingTestRuntime() || DB::getDriverName() !== 'sqlite') {
            return;
        }

        if (Schema::hasTable('reservations') && ! Schema::hasColumn('reservations', 'active_applied_user_voucher_id')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->unsignedInteger('active_applied_user_voucher_id')->nullable();
            });
        }

        if (Schema::hasTable('table_holds') && ! Schema::hasColumn('table_holds', 'branch_id')) {
            Schema::table('table_holds', function (Blueprint $table): void {
                $table->unsignedInteger('branch_id')->default(1);
            });
        }

        if (Schema::hasTable('table_holds') && ! Schema::hasColumn('table_holds', 'active_session_hold_key')) {
            Schema::table('table_holds', function (Blueprint $table): void {
                $table->string('active_session_hold_key')->nullable();
            });
        }

        if (Schema::hasTable('audit_logs') && ! Schema::hasColumn('audit_logs', 'actor_type')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('actor_type', 40)->nullable();
            });
        }

        if (Schema::hasTable('audit_logs') && ! Schema::hasColumn('audit_logs', 'actor_key')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('actor_key', 120)->nullable();
            });
        }

        if (Schema::hasTable('audit_logs') && ! Schema::hasColumn('audit_logs', 'summary_json')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->json('summary_json')->nullable();
            });
        }

        if (Schema::hasTable('audit_logs') && ! Schema::hasColumn('audit_logs', 'meta_json')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->json('meta_json')->nullable();
            });
        }

        if (Schema::hasTable('audit_logs') && ! Schema::hasColumn('audit_logs', 'request_id')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('request_id', 64)->nullable();
            });
        }

        if (Schema::hasTable('audit_log_subjects') && ! Schema::hasColumn('audit_log_subjects', 'subject_role')) {
            Schema::table('audit_log_subjects', function (Blueprint $table): void {
                $table->string('subject_role', 32)->nullable();
            });
        }

        $this->ensureIndexIfMissing('reservations', 'uq_reservations__active_applied_user_voucher_id', ['active_applied_user_voucher_id'], true);
        $this->ensureIndexIfMissing('reservations', 'idx_reservations__branch_id__status__start_time__end_time', ['branch_id', 'status', 'start_time', 'end_time']);
        $this->ensureIndexIfMissing('reservation_tables', 'uq_reservation_tables__reservation_id__table_id', ['reservation_id', 'table_id'], true);
        $this->ensureIndexIfMissing('reservation_tables', 'idx_reservation_tables__table_id__reservation_id', ['table_id', 'reservation_id']);
        $this->ensureIndexIfMissing('table_hold_details', 'uq_table_hold_details__hold_id__table_id', ['hold_id', 'table_id'], true);
        $this->ensureIndexIfMissing('table_hold_details', 'fk_table_hold_details__table_id__restaurant_tables', ['table_id']);
        $this->ensureIndexIfMissing('payments', 'uq_payments__idempotency_key', ['idempotency_key'], true);
        $this->ensureIndexIfMissing('payments', 'uq_payments__payment_provider__transaction_code', ['payment_provider', 'transaction_code'], true);
        $this->ensureIndexIfMissing('payments', 'idx_payments__reservation_id__payment_type__status', ['reservation_id', 'payment_type', 'status']);
        $this->ensureIndexIfMissing('payments', 'idx_payments__branch_id__reservation_id__payment_type__status', ['branch_id', 'reservation_id', 'payment_type', 'status']);
        $this->ensureIndexIfMissing('payments', 'idx_payments__cashier_shift_id', ['cashier_shift_id']);
        $this->ensureIndexIfMissing('payments', 'idx_payments__refund_of_payment_id', ['refund_of_payment_id']);
        $this->ensureIndexIfMissing('finance_replay_records', 'uq_finance_replay_records__scope_aggregate_key', ['scope', 'aggregate_type', 'aggregate_id', 'idempotency_key'], true);
        $this->ensureIndexIfMissing('finance_replay_records', 'idx_finance_replay_records__idempotency_key', ['idempotency_key']);
        $this->ensureIndexIfMissing('finance_replay_records', 'idx_finance_replay_records__result', ['result_type', 'result_id']);
        $this->ensureIndexIfMissing('table_holds', 'idx_table_holds__status__expire_at__start_time', ['hold_status', 'expire_at', 'start_time']);
        $this->ensureIndexIfMissing('table_holds', 'idx_table_holds__branch_id__status__expire_at__start_time', ['branch_id', 'hold_status', 'expire_at', 'start_time']);
        $this->ensureIndexIfMissing('table_holds', 'idx_table_holds__session_id__start_time__created_at', ['session_id', 'start_time', 'created_at']);
        $this->ensureIndexIfMissing('table_holds', 'idx_table_holds__session_id__confirmed_reservation_id', ['session_id', 'confirmed_reservation_id']);
        $this->ensureIndexIfMissing('table_holds', 'idx_table_holds__confirmed_reservation_id', ['confirmed_reservation_id']);
        $this->ensureSqlitePartialUniqueIndexForActiveTableHold();
        $this->ensureIndexIfMissing('reservation_deposit_payment_sessions', 'uq_reservation_deposit_payment_sessions__linked_payment_id', ['linked_payment_id'], true);
        $this->ensureIndexIfMissing('reservation_deposit_payment_sessions', 'idx_reservation_deposit_payment_sessions__reservation_id__session_status', ['reservation_id', 'session_status']);
        $this->ensureIndexIfMissing('reservation_deposit_payment_sessions', 'idx_reservation_deposit_payment_sessions__customer_user_id__created_at', ['customer_user_id', 'created_at']);
        $this->ensureIndexIfMissing('reservation_bill_payment_sessions', 'uq_reservation_bill_payment_sessions__linked_payment_id', ['linked_payment_id'], true);
        $this->ensureIndexIfMissing('reservation_bill_payment_sessions', 'idx_reservation_bill_payment_sessions__reservation_id__session_status', ['reservation_id', 'session_status']);
        $this->ensureIndexIfMissing('reservation_bill_payment_sessions', 'idx_reservation_bill_payment_sessions__customer_user_id__created_at', ['customer_user_id', 'created_at']);
        $this->ensureIndexIfMissing('audit_logs', 'idx_audit_logs__entity_type__entity_id', ['entity_type', 'entity_id']);
        $this->ensureIndexIfMissing('audit_logs', 'idx_audit_logs__actor_user_id__created_at', ['actor_user_id', 'created_at']);
        $this->ensureIndexIfMissing('audit_logs', 'idx_audit_logs__actor_type__created_at', ['actor_type', 'created_at']);
        $this->ensureIndexIfMissing('audit_logs', 'idx_audit_logs__action__created_at', ['action', 'created_at']);
        $this->ensureIndexIfMissing('audit_logs', 'idx_audit_logs__request_id', ['request_id']);
        $this->ensureIndexIfMissing('audit_log_subjects', 'idx_audit_log_subjects__subject_type__subject_id__audit_id', ['subject_type', 'subject_id', 'audit_id']);
        $this->ensureIndexIfMissing('audit_log_subjects', 'idx_audit_log_subjects__audit_id', ['audit_id']);
        $this->ensureIndexIfMissing('customer_privacy_requests', 'idx_customer_privacy_requests__user_id__status__created_at', ['user_id', 'status', 'created_at']);
        $this->ensureIndexIfMissing('customer_privacy_requests', 'idx_customer_privacy_requests__status__created_at', ['status', 'created_at']);
        $this->ensureIndexIfMissing('waiting_list', 'idx_waiting_list__branch_id__status__priority__requested_at', ['branch_id', 'status', 'priority', 'requested_at']);
        $this->ensureSqlitePartialUniqueIndexForActiveWaitingListOwner();
    }

    /**
     * @param  list<string>  $columns
     */
    protected function ensureIndexIfMissing(string $table, string $indexName, array $columns, bool $unique = false): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName, $unique): void {
            if ($unique) {
                $blueprint->unique($columns, $indexName);

                return;
            }

            $blueprint->index($columns, $indexName);
        });
    }

    protected function ensureSqlitePartialUniqueIndexForActiveTableHold(): void
    {
        if (! Schema::hasTable('table_holds') || $this->sqliteIndexExists('table_holds', 'uq_table_holds__active_session_hold_key')) {
            return;
        }

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS "uq_table_holds__active_session_hold_key"
ON "table_holds" ("session_id")
WHERE "hold_status" IN ('Holding', 'Pending')
SQL);
    }

    protected function ensureSqlitePartialUniqueIndexForActiveWaitingListOwner(): void
    {
        if (! Schema::hasTable('waiting_list') || $this->sqliteIndexExists('waiting_list', 'uq_waiting_list__active_owner_waiting_key')) {
            return;
        }

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS "uq_waiting_list__active_owner_waiting_key"
ON "waiting_list" ("user_id")
WHERE "user_id" IS NOT NULL AND "status" IN ('Waiting', 'Notified')
SQL);
    }

    protected function sqliteIndexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'sqlite' || ! Schema::hasTable($table)) {
            return false;
        }

        $rows = DB::select(sprintf("PRAGMA index_list('%s')", str_replace("'", "''", $table)));

        foreach ($rows as $row) {
            if ((string) ($row->name ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }

    protected function isPortableBookingTestRuntime(): bool
    {
        return app()->runningUnitTests() || app()->environment('testing');
    }

    protected function nowUtc(): Carbon
    {
        return Carbon::now('UTC')->startOfSecond();
    }

    protected function ensureRole(string $roleName): int
    {
        DB::table('roles')->updateOrInsert([
            'role_name' => $roleName,
        ], [
            'role_name' => $roleName,
        ]);

        return (int) DB::table('roles')->where('role_name', $roleName)->value('role_id');
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createBranch(array $overrides = []): int
    {
        $this->requireBookingSchema();

        $payload = array_merge([
            'branch_code' => strtoupper(Str::random(6)),
            'branch_name' => 'Branch '.Str::upper(Str::random(4)),
            'description' => null,
            'timezone' => 'UTC',
            'currency' => 'VND',
            'business_hours' => $this->defaultBranchFixtureBusinessHours(),
            'closure_windows' => null,
            'booking_policy' => $this->defaultBranchFixtureBookingPolicy(),
            'is_active' => true,
            'is_default' => false,
            'row_version' => 1,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ], $overrides);

        foreach (['business_hours', 'closure_windows', 'booking_policy'] as $jsonColumn) {
            if (array_key_exists($jsonColumn, $payload) && is_array($payload[$jsonColumn])) {
                $payload[$jsonColumn] = json_encode($payload[$jsonColumn], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return (int) DB::table('branches')->insertGetId($payload);
    }

    /**
     * @return list<array{day_of_week:int,periods:list<array{start_time:string,end_time:string}>}>
     */
    protected function defaultBranchFixtureBusinessHours(): array
    {
        return array_map(
            static fn (int $day): array => [
                'day_of_week' => $day,
                'periods' => [[
                    'start_time' => '00:00',
                    'end_time' => '24:00',
                ]],
            ],
            range(0, 6),
        );
    }

    /**
     * @return array<string,mixed>
     */
    protected function defaultBranchFixtureBookingPolicy(): array
    {
        return [
            'reservation' => [],
            'waiting_list' => [],
            'availability' => [],
        ];
    }

    protected function upsertFeatureFlagOverride(
        string $featureKey,
        bool $enabled,
        ?string $environment = null,
        ?int $branchId = null,
        array $overrides = [],
    ): int {
        $this->requireBookingSchema();

        $now = $this->nowUtc();
        $payload = array_merge([
            'feature_key' => strtolower(trim($featureKey)),
            'environment' => strtolower(trim((string) ($environment ?? config('app.env', 'testing')))) ?: '*',
            'branch_id' => $branchId !== null && $branchId > 0 ? $branchId : 0,
            'enabled' => $enabled,
            'reason' => null,
            'updated_by' => null,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        $existingId = DB::table('feature_flags')
            ->where('feature_key', (string) $payload['feature_key'])
            ->where('environment', (string) $payload['environment'])
            ->where('branch_id', (int) $payload['branch_id'])
            ->value('feature_flag_id');

        if ($existingId !== null) {
            DB::table('feature_flags')
                ->where('feature_flag_id', (int) $existingId)
                ->update([
                    'enabled' => (bool) $payload['enabled'],
                    'reason' => $payload['reason'],
                    'updated_by' => $payload['updated_by'],
                    'row_version' => (int) ($payload['row_version'] ?? 1),
                    'updated_at' => $payload['updated_at'],
                ]);

            if (app()->bound(\App\Platform\FeatureFlags\Services\FeatureFlagService::class)) {
                app(\App\Platform\FeatureFlags\Services\FeatureFlagService::class)->forgetAllResolved();
            }
            return (int) $existingId;
        }

        DB::table('feature_flags')->insert($payload);

        if (app()->bound(\App\Platform\FeatureFlags\Services\FeatureFlagService::class)) {
            app(\App\Platform\FeatureFlags\Services\FeatureFlagService::class)->forgetAllResolved();
        }

        return (int) DB::table('feature_flags')
            ->where('feature_key', (string) $payload['feature_key'])
            ->where('environment', (string) $payload['environment'])
            ->where('branch_id', (int) $payload['branch_id'])
            ->value('feature_flag_id');
    }

    protected function createUser(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $suffix = Str::lower(Str::random(8));
        $roleName = (string) ($overrides['role_name'] ?? 'Customer');
        $roleId = (int) ($overrides['role_id'] ?? $this->ensureRole($roleName));

        $payload = array_merge([
            'username' => 'u_'.$suffix,
            'password_hash' => '$2y$12$abcdefghijklmnopqrstuv',
            'full_name' => 'Test User '.$suffix,
            'email' => 'user.'.$suffix.'@example.test',
            'phone' => '09'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'role_id' => $roleId,
            'current_tier_id' => null,
            'language_pref' => 'vn',
            'is_deleted' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'row_version' => 1,
        ], $overrides);
        unset($payload['role_name']);

        if (mb_strtolower(trim($roleName)) === 'customer' && $roleId > 0) {
            $allowedRoleIds = array_values(array_unique(array_filter(
                array_map('intval', (array) config('customer_auth.allowed_role_ids', [])),
                static fn (int $value): bool => $value > 0
            )));

            if (! in_array($roleId, $allowedRoleIds, true)) {
                $allowedRoleIds[] = $roleId;
                sort($allowedRoleIds);
                config()->set('customer_auth.allowed_role_ids', $allowedRoleIds);
            }
        }

        return (int) DB::table('users')->insertGetId($payload);
    }

    protected function createLoyaltyTier(int $minPoints = 0, string $tierCode = 'BRONZE', string $tierName = 'Bronze'): int
    {
        DB::table('loyalty_tiers')->updateOrInsert([
            'tier_code' => $tierCode,
        ], [
            'tier_name' => $tierName,
            'min_points' => $minPoints,
            'benefits_json' => null,
            'is_active' => 1,
            'created_at' => $this->nowUtc(),
            'updated_at' => $this->nowUtc(),
        ]);

        return (int) DB::table('loyalty_tiers')->where('tier_code', $tierCode)->value('tier_id');
    }

    protected function ensureUserPoints(int $userId, int $totalPoints = 0, ?int $updatedBy = null): void
    {
        DB::table('user_points')->updateOrInsert([
            'user_id' => $userId,
        ], [
            'total_points' => max(0, $totalPoints),
            'last_updated' => $this->nowUtc(),
            'updated_by' => $updatedBy,
            'row_version' => 1,
        ]);
    }

    protected function ensureMenuCategory(string $name = 'Test Category'): int
    {
        DB::table('menu_categories')->updateOrInsert([
            'name' => $name,
        ], [
            'description' => $name,
            'sort_order' => 0,
            'is_deleted' => 0,
        ]);

        return (int) DB::table('menu_categories')->where('name', $name)->value('category_id');
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createMenuItem(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $suffix = Str::upper(Str::random(6));
        $payload = array_merge([
            'category_id' => $this->ensureMenuCategory('Test Food'),
            'code' => 'ITEM-'.$suffix,
            'name' => 'Menu '.$suffix,
            'description' => 'Menu item '.$suffix,
            'img_url' => null,
            'is_available' => 1,
            'is_preorder_enabled' => 0,
            'preorder_quota_per_day' => null,
            'preorder_cutoff_minutes' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        return (int) DB::table('menu_items')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createMenuItemPrice(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $payload = array_merge([
            'item_id' => $this->createMenuItem(),
            'price' => '100000.00',
            'currency' => 'VND',
            'effective_from' => $now->copy()->subHour(),
            'effective_to' => null,
        ], $overrides);

        return (int) DB::table('menu_item_prices')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createIngredient(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $suffix = Str::upper(Str::random(6));
        $payload = array_merge([
            'code' => 'ING-'.$suffix,
            'name' => 'Ingredient '.$suffix,
            'unit_code' => 'g',
            'description' => 'Ingredient '.$suffix,
            'is_active' => 1,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        return (int) DB::table('ingredients')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createMenuItemRecipeLine(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $ingredientId = (int) ($overrides['ingredient_id'] ?? $this->createIngredient());
        $payload = array_merge([
            'item_id' => $this->createMenuItem(),
            'ingredient_id' => $ingredientId,
            'quantity' => '100.000',
            'unit_code' => (string) (DB::table('ingredients')->where('ingredient_id', $ingredientId)->value('unit_code') ?? 'g'),
            'sort_order' => 10,
            'notes' => null,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        return (int) DB::table('menu_item_recipes')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createIngredientStockMovement(array $overrides = []): int
    {
        $ingredientId = (int) ($overrides['ingredient_id'] ?? $this->createIngredient());
        $payload = array_merge([
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '1.000',
            'unit_code' => (string) (DB::table('ingredients')->where('ingredient_id', $ingredientId)->value('unit_code') ?? 'g'),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => null,
            'created_by' => null,
            'created_at' => $this->nowUtc(),
        ], $overrides);

        return (int) DB::table('ingredient_stock_movements')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createCashierShift(array $overrides = []): int
    {
        $this->requireBookingSchema();

        $now = $this->nowUtc();
        $cashierUserId = (int) ($overrides['cashier_user_id'] ?? $this->createUser(['role_name' => 'Staff']));
        $status = (string) ($overrides['status'] ?? 'Open');
        $shiftCode = (string) ($overrides['shift_code'] ?? ('CSH-'.Str::upper(Str::random(8))));
        $payload = array_merge([
            'branch_id' => 1,
            'shift_code' => $shiftCode,
            'cashier_user_id' => $cashierUserId,
            'active_cashier_user_id' => $status === 'Open' ? $cashierUserId : null,
            'status' => $status,
            'currency' => 'VND',
            'terminal_code' => 'POS-01',
            'opening_float_amount' => '0.00',
            'expected_cash_amount' => $status === 'Closed' ? '0.00' : null,
            'actual_cash_amount' => $status === 'Closed' ? '0.00' : null,
            'cash_discrepancy_amount' => $status === 'Closed' ? '0.00' : null,
            'opened_at' => $now,
            'closed_at' => $status === 'Closed' ? $now->copy()->addHours(8) : null,
            'opened_by' => $cashierUserId,
            'closed_by' => $status === 'Closed' ? $cashierUserId : null,
            'opening_note' => null,
            'closing_note' => $status === 'Closed' ? 'Closed' : null,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $status === 'Closed' ? $now->copy()->addHours(8) : $now,
        ], $overrides);

        $connection = DB::connection();
        if ($connection->getDriverName() === 'mysql') {
            unset($payload['active_cashier_user_id']);
        }

        return (int) DB::table('cashier_shifts')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createSupplier(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $suffix = Str::upper(Str::random(6));
        $payload = array_merge([
            'code' => 'SUP-'.$suffix,
            'name' => 'Supplier '.$suffix,
            'contact_name' => 'Contact '.$suffix,
            'phone' => '0900'.random_int(100000, 999999),
            'email' => strtolower('supplier-'.$suffix.'@example.test'),
            'notes' => 'Supplier '.$suffix,
            'is_active' => 1,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        return (int) DB::table('suppliers')->insertGetId($payload);
    }

    protected function createKitchenStation(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $suffix = Str::upper(Str::random(5));

        $payload = array_merge([
            'branch_id' => 1,
            'code' => 'KDS-'.$suffix,
            'name' => 'Kitchen '.$suffix,
            'description' => 'Kitchen station '.$suffix,
            'output_mode' => 'KDS',
            'printer_target' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        return (int) DB::table('kitchen_stations')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createKitchenStationRoute(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $stationId = (int) ($overrides['station_id'] ?? $this->createKitchenStation());
        $stationBranchId = (int) DB::table('kitchen_stations')
            ->where('station_id', $stationId)
            ->value('branch_id');
        $payload = array_merge([
            'station_id' => $stationId,
            'branch_id' => $stationBranchId,
            'category_id' => $this->ensureMenuCategory('Kitchen Category'),
            'sort_order' => 10,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        return (int) DB::table('kitchen_station_category_routes')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createKitchenOrderTicket(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $routeId = (int) ($overrides['route_id'] ?? $this->createKitchenStationRoute());
        $route = DB::table('kitchen_station_category_routes')->where('route_id', $routeId)->first();
        $orderItemId = (int) ($overrides['order_item_id'] ?? $this->createOrderItem());
        $orderItem = DB::table('reservation_order_items')->where('order_item_id', $orderItemId)->first();
        $order = DB::table('reservation_orders')->where('order_id', $orderItem->order_id)->first();
        $item = DB::table('menu_items')->where('item_id', $orderItem->item_id)->first();
        $station = DB::table('kitchen_stations')->where('station_id', $route->station_id)->first();

        $payload = array_merge([
            'station_id' => (int) $route->station_id,
            'order_id' => (int) $order->order_id,
            'reservation_id' => (int) $order->reservation_id,
            'order_item_id' => $orderItemId,
            'item_id' => (int) $orderItem->item_id,
            'category_id' => $item?->category_id !== null ? (int) $item->category_id : null,
            'route_id' => $routeId,
            'route_source' => 'Category',
            'output_mode' => (string) ($station->output_mode ?? 'KDS'),
            'printer_target' => $station->printer_target ?? null,
            'ticket_status' => 'Queued',
            'first_dispatched_at' => $now,
            'fired_at' => null,
            'ready_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'last_recalled_at' => null,
            'dispatch_count' => 1,
            'recall_count' => 0,
            'ticket_notes' => null,
            'created_by' => null,
            'updated_by' => null,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        return (int) DB::table('kitchen_order_item_tickets')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createRestaurantTable(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $templateCode = 'TPL-'.Str::upper(Str::random(5));
        DB::table('table_templates')->updateOrInsert([
            'template_code' => $templateCode,
        ], [
            'seats' => 4,
            'description' => 'Test template',
        ]);
        $templateId = (int) DB::table('table_templates')->where('template_code', $templateCode)->value('template_id');

        $payload = array_merge([
            'table_code' => 'TB-'.Str::upper(Str::random(6)),
            'template_id' => $templateId,
            'zone' => 'A',
            'pos_x' => 1,
            'pos_y' => 1,
            'status' => 'Available',
            'description' => 'Test table',
            'created_at' => $now,
            'updated_at' => $now,
            'is_deleted' => 0,
            'row_version' => 1,
            'price' => null,
        ], $overrides);

        return (int) DB::table('restaurant_tables')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createReservation(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $start = $now->copy()->addHour();
        $end = $start->copy()->addHours(2);
        $payload = array_merge([
            'user_id' => $this->createUser(),
            'branch_id' => 1,
            'guest_name' => null,
            'guest_phone' => null,
            'guest_email' => null,
            'reservation_code' => 'RSV-'.Str::upper(Str::random(10)),
            'reserved_at' => $now,
            'start_time' => $start,
            'end_time' => $end,
            'guest_count' => 2,
            'status' => 'Confirmed',
            'source' => 'Online',
            'checked_in_at' => null,
            'checked_out_at' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'cancelled_by' => null,
            'no_show_at' => null,
            'deposit_required_amount' => '0.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'NotRequired',
            'applied_user_voucher_id' => null,
            'discount_amount' => '0.00',
            'final_bill_amount' => null,
            'bill_currency' => 'VND',
            'billed_at' => null,
            'notes' => null,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'row_version' => 1,
        ], $overrides);

        $status = (string) ($payload['status'] ?? 'Confirmed');
        if ($status === 'Reserved' && empty($payload['checked_in_at'])) {
            $payload['checked_in_at'] = $payload['reserved_at'] ?? $now;
        }
        if ($status === 'Cancelled' && empty($payload['cancelled_at'])) {
            $payload['cancelled_at'] = $now;
        }
        if ($status === 'Completed' && empty($payload['checked_out_at'])) {
            $payload['checked_out_at'] = $now;
        }
        if ($status === 'NoShow' && empty($payload['no_show_at'])) {
            $payload['no_show_at'] = $now;
        }
        if (($payload['bill_currency'] ?? null) === null) {
            $payload['bill_currency'] = 'VND';
        }

        $depositRequiredAmount = round((float) ($payload['deposit_required_amount'] ?? 0.0), 2);
        $depositPaidAmount = round((float) ($payload['deposit_paid_amount'] ?? 0.0), 2);
        if ((string) ($payload['deposit_status'] ?? '') === 'Required') {
            $payload['deposit_status'] = $depositRequiredAmount > 0.0 && $depositPaidAmount + 0.0001 < $depositRequiredAmount
                ? 'Pending'
                : 'NotRequired';
        }

        if (Schema::hasColumn('reservations', 'active_applied_user_voucher_id')) {
            $payload['active_applied_user_voucher_id'] = in_array($status, ['Confirmed', 'Reserved'], true)
                ? ($payload['applied_user_voucher_id'] ?? null)
                : null;
        }

        $this->assertPortableReservationPayloadMatchesCanonical($payload);
        $payload = $this->stripGeneratedColumnsForInsert('reservations', $payload);

        return (int) DB::table('reservations')->insertGetId($payload);
    }

    protected function attachReservationTable(int $reservationId, ?int $tableId = null): int
    {
        $tableId ??= $this->createRestaurantTable();

        try {
            DB::table('reservation_tables')->insert([
                'reservation_id' => $reservationId,
                'table_id' => $tableId,
            ]);
        } catch (\Throwable $exception) {
            $message = (string) $exception->getMessage();
            if (! str_contains($message, 'reservation_tables overlap conflict with another active reservation')) {
                throw $exception;
            }

            $this->forceAttachReservationTableForReadModel($reservationId, $tableId);
        }

        return $tableId;
    }

    protected function forceAttachReservationTableForReadModel(int $reservationId, int $tableId): int
    {
        $reservation = DB::table('reservations')
            ->where('reservation_id', $reservationId)
            ->first(['reservation_id', 'start_time', 'end_time']);

        if ($reservation === null) {
            throw new \RuntimeException('Reservation not found for forceAttachReservationTableForReadModel.');
        }

        $now = $this->nowUtc();
        $conflicts = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->where('rt.table_id', $tableId)
            ->where('rt.reservation_id', '!=', $reservationId)
            ->whereIn('r.status', ['Confirmed', 'Reserved'])
            ->where('r.start_time', '<', $reservation->end_time)
            ->where('r.end_time', '>', $reservation->start_time)
            ->get(['r.reservation_id', 'r.status', 'r.cancelled_at']);

        foreach ($conflicts as $row) {
            DB::table('reservations')
                ->where('reservation_id', (int) $row->reservation_id)
                ->update([
                    'status' => 'Cancelled',
                    'cancelled_at' => $row->cancelled_at ?? $now,
                    'updated_at' => $now,
                ]);
        }

        DB::table('reservation_tables')->insert([
            'reservation_id' => $reservationId,
            'table_id' => $tableId,
        ]);

        foreach ($conflicts as $row) {
            DB::table('reservations')
                ->where('reservation_id', (int) $row->reservation_id)
                ->update([
                    'status' => (string) $row->status,
                    'cancelled_at' => $row->cancelled_at,
                    'updated_at' => $now,
                ]);
        }

        return $tableId;
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createOrder(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $payload = array_merge([
            'reservation_id' => $this->createReservation(),
            'order_type' => 'OnSpot',
            'status' => 'Active',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => null,
            'updated_by' => null,
            'row_version' => 1,
            'notes' => null,
        ], $overrides);

        return (int) DB::table('reservation_orders')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createOrderItem(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $itemId = (int) ($overrides['item_id'] ?? $this->createMenuItem());
        $quantity = (int) ($overrides['quantity'] ?? 1);
        $unitPrice = round((float) ($overrides['unit_price'] ?? 100000), 2);
        $payload = array_merge([
            'order_id' => $this->createOrder(),
            'item_id' => $itemId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'currency' => 'VND',
            'line_total' => round($quantity * $unitPrice, 2),
            'item_name_snapshot' => 'Snapshot '.$itemId,
            'status' => 'Ordered',
            'notes' => null,
            'updated_by' => null,
            'row_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);

        return (int) DB::table('reservation_order_items')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createPayment(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $payload = array_merge([
            'reservation_id' => $this->createReservation(),
            'branch_id' => 1,
            'cashier_shift_id' => null,
            'refund_of_payment_id' => null,
            'amount' => '100000.00',
            'currency' => 'VND',
            'payment_method' => 'Cash',
            'payment_provider' => 'Other',
            'payment_type' => 'Final',
            'status' => 'Success',
            'transaction_code' => 'TX-'.Str::upper(Str::random(8)),
            'idempotency_key' => null,
            'paid_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => null,
            'updated_by' => null,
            'notes' => null,
            'provider_response_json' => null,
            'row_version' => 1,
        ], $overrides);

        if (isset($payload['provider_response_json']) && (is_array($payload['provider_response_json']) || is_object($payload['provider_response_json']))) {
            $payload['provider_response_json'] = json_encode($payload['provider_response_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->assertPortablePaymentPayloadMatchesCanonical($payload);

        return (int) DB::table('payments')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createVoucher(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $payload = array_merge([
            'code' => 'VC-'.Str::upper(Str::random(8)),
            'description' => 'Test voucher',
            'discount_type' => 'Fixed',
            'discount_value' => '50000.00',
            'free_item_id' => null,
            'free_item_qty' => null,
            'max_usage' => null,
            'max_usage_per_user' => null,
            'min_spend' => '0.00',
            'start_date' => $now->copy()->subDay(),
            'expiry_date' => $now->copy()->addDay(),
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => null,
            'updated_by' => null,
            'row_version' => 1,
        ], $overrides);

        return (int) DB::table('vouchers')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function assignVoucher(array $overrides = []): int
    {
        $now = $this->nowUtc();
        $payload = array_merge([
            'user_id' => $this->createUser(),
            'voucher_id' => $this->createVoucher(),
            'assigned_date' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'is_used' => 0,
            'used_date' => null,
            'used_reservation_id' => null,
            'used_amount' => null,
            'lock_token' => null,
            'locked_until' => null,
            'created_by' => null,
            'updated_by' => null,
            'row_version' => 1,
        ], $overrides);

        if ((int) ($payload['is_used'] ?? 0) === 1) {
            $payload['used_date'] ??= $now;
            $payload['used_amount'] ??= '0.00';
            $payload['used_reservation_id'] ??= $this->createReservation([
                'user_id' => (int) $payload['user_id'],
                'status' => 'Completed',
                'checked_in_at' => $now->copy()->subHour(),
                'checked_out_at' => $now,
                'bill_currency' => 'VND',
            ]);
        } else {
            $payload['used_date'] = null;
            $payload['used_reservation_id'] = null;
            $payload['used_amount'] = null;
        }

        return (int) DB::table('user_vouchers')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createWaitingListEntry(array $overrides = []): int
    {
        $this->requireBookingSchema();

        $now = $this->nowUtc();
        $payload = array_merge([
            'user_id' => null,
            'customer_session_id' => null,
            'guest_name' => 'Waiting Customer',
            'phone' => '0909000000',
            'guest_count' => 2,
            'requested_at' => $now,
            'status' => 'Waiting',
            'priority' => 0,
            'notified_at' => null,
            'notify_expires_at' => null,
            'customer_response_status' => null,
            'customer_responded_at' => null,
            'customer_confirmed_arrival_at' => null,
            'notified_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'seated_at' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'notes' => null,
            'updated_by' => null,
            'row_version' => 1,
        ], $overrides);

        if (($payload['customer_confirmed_arrival_at'] ?? null) !== null && empty($payload['customer_response_status'])) {
            $payload['customer_response_status'] = 'Accepted';
        }

        if (($payload['customer_response_status'] ?? null) === 'Accepted' && empty($payload['customer_responded_at'])) {
            $payload['customer_responded_at'] = $payload['customer_confirmed_arrival_at'] ?? $payload['seated_at'] ?? $payload['notified_at'] ?? $payload['requested_at'];
        }

        $optionalColumns = [
            'customer_response_status',
            'customer_responded_at',
            'customer_confirmed_arrival_at',
        ];

        foreach ($optionalColumns as $column) {
            if (! Schema::hasColumn('waiting_list', $column)) {
                unset($payload[$column]);
            }
        }

        $this->assertPortableWaitingListPayloadMatchesCanonical($payload);

        return (int) DB::table('waiting_list')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createConversation(array $overrides = []): string
    {
        $this->requireBookingSchema();

        $now = $this->nowUtc();
        $conversationId = (string) ($overrides['conversation_id'] ?? (string) Str::uuid());

        $payload = array_merge([
            'conversation_id' => $conversationId,
            'branch_id' => 1,
            'user_id' => null,
            'customer_session_id' => null,
            'session_id' => 'sess-'.Str::lower(Str::random(12)),
            'channel' => 'WebChat',
            'status' => 'Open',
            'workflow_state' => 'Open',
            'workflow_state_reason' => 'open',
            'intent_detected' => null,
            'linked_reservation_id' => null,
            'linked_waiting_list_id' => null,
            'created_at' => $now,
            'workflow_state_changed_at' => $now,
            'first_triaged_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
        ], $overrides);

        if (! array_key_exists('workflow_state', $overrides)) {
            $payload['workflow_state'] = match ((string) ($payload['status'] ?? 'Open')) {
                'Pending' => 'PendingCustomer',
                'Closed' => 'Closed',
                default => 'Open',
            };
        }

        if (! array_key_exists('workflow_state_reason', $overrides)) {
            $payload['workflow_state_reason'] = match ((string) ($payload['workflow_state'] ?? 'Open')) {
                'PendingCustomer' => 'waiting_for_customer',
                'Closed' => 'closed',
                default => 'open',
            };
        }

        $this->assertPortableConversationPayloadMatchesCanonical($payload);

        DB::table('conversations')->insert($payload);

        return $conversationId;
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createConversationMessage(array $overrides = []): int
    {
        $this->requireBookingSchema();

        $now = $this->nowUtc();
        $payload = array_merge([
            'conversation_id' => $this->createConversation(),
            'sender' => 'user',
            'sender_id' => null,
            'message_text' => 'Need help with reservation.',
            'message_type' => 'text',
            'is_internal_note' => 0,
            'attachment_url' => null,
            'created_at' => $now,
            'is_processed' => 0,
            'processing_status' => null,
            'confidence' => null,
            'related_reservation_id' => null,
            'related_order_id' => null,
        ], $overrides);

        $this->assertPortableConversationMessagePayloadMatchesCanonical($payload);

        return (int) DB::table('conversation_messages')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createConversationFile(array $overrides = []): int
    {
        $this->requireBookingSchema();

        $payload = array_merge([
            'message_id' => $this->createConversationMessage(),
            'file_url' => 'https://example.test/conversations/file.jpg',
            'mime_type' => 'image/jpeg',
            'created_at' => $this->nowUtc(),
        ], $overrides);

        return (int) DB::table('conversation_files')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createConversationEvent(array $overrides = []): int
    {
        $this->requireBookingSchema();

        $payload = array_merge([
            'conversation_id' => $this->createConversation(),
            'event_type' => 'message_received',
            'event_by_user_id' => null,
            'event_data' => json_encode(['source' => 'fixture'], JSON_THROW_ON_ERROR),
            'created_at' => $this->nowUtc(),
        ], $overrides);

        if (isset($payload['event_data']) && (is_array($payload['event_data']) || is_object($payload['event_data']))) {
            $payload['event_data'] = json_encode($payload['event_data'], JSON_THROW_ON_ERROR);
        }

        return (int) DB::table('conversation_events')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createConversationAnalysis(array $overrides = []): int
    {
        $this->requireBookingSchema();

        $payload = array_merge([
            'conversation_id' => $this->createConversation(),
            'analyzer_name' => 'fixture_analyzer',
            'is_spam' => 0,
            'quality_score' => '0.9000',
            'extracted_info' => json_encode(['intent' => 'reservation_help'], JSON_THROW_ON_ERROR),
            'created_at' => $this->nowUtc(),
        ], $overrides);

        if (isset($payload['extracted_info']) && (is_array($payload['extracted_info']) || is_object($payload['extracted_info']))) {
            $payload['extracted_info'] = json_encode($payload['extracted_info'], JSON_THROW_ON_ERROR);
        }

        return (int) DB::table('conversation_analyses')->insertGetId($payload);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createAgentAssignment(array $overrides = []): int
    {
        $this->requireBookingSchema();

        $staffId = isset($overrides['agent_user_id'])
            ? (int) $overrides['agent_user_id']
            : $this->createUser(['role_name' => 'Staff']);

        $payload = array_merge([
            'conversation_id' => $this->createConversation(),
            'agent_user_id' => $staffId,
            'assigned_at' => $this->nowUtc(),
            'released_at' => null,
            'is_active' => 1,
            'notes' => null,
        ], $overrides);

        $payload = $this->stripGeneratedColumnsForInsert('agent_assignments', $payload);

        $assignmentId = (int) DB::table('agent_assignments')->insertGetId($payload);

        if ((int) ($payload['is_active'] ?? 0) === 1) {
            DB::table('conversations')
                ->where('conversation_id', $payload['conversation_id'])
                ->update([
                    'workflow_state' => 'Assigned',
                    'workflow_state_reason' => 'assigned',
                    'workflow_state_changed_at' => $payload['assigned_at'] ?? $this->nowUtc(),
                    'first_triaged_at' => DB::raw('COALESCE(first_triaged_at, workflow_state_changed_at, created_at)'),
                    'status' => 'Open',
                    'closed_at' => null,
                ]);
        }

        return $assignmentId;
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createMessageEntity(array $overrides = []): int
    {
        $this->requireBookingSchema();

        $payload = array_merge([
            'message_id' => $this->createConversationMessage(),
            'entity_type' => 'reservation_code',
            'entity_text' => 'RSV-TEST-001',
            'entity_normalized' => 'RSV-TEST-001',
            'extra_json' => json_encode(['source' => 'fixture'], JSON_THROW_ON_ERROR),
            'created_at' => $this->nowUtc(),
        ], $overrides);

        if (isset($payload['extra_json']) && (is_array($payload['extra_json']) || is_object($payload['extra_json']))) {
            $payload['extra_json'] = json_encode($payload['extra_json'], JSON_THROW_ON_ERROR);
        }

        return (int) DB::table('message_entities')->insertGetId($payload);
    }

    /** @param array<string,mixed> $payload */
    protected function assertPortableReservationPayloadMatchesCanonical(array $payload): void
    {
        $start = $payload['start_time'] ?? null;
        $end = $payload['end_time'] ?? null;
        if (! $start instanceof Carbon || ! $end instanceof Carbon || $start->greaterThanOrEqualTo($end)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations time-range contract.');
        }

        if ((int) ($payload['guest_count'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations guest-count contract.');
        }

        $hasUser = isset($payload['user_id']) && $payload['user_id'] !== null && (int) $payload['user_id'] > 0;
        $guestName = trim((string) ($payload['guest_name'] ?? ''));
        $guestPhone = trim((string) ($payload['guest_phone'] ?? ''));
        if (! $hasUser && ($guestName === '' || $guestPhone === '')) {
            throw new \InvalidArgumentException('Portable booking schema fixture requires user_id or guest_name + guest_phone.');
        }

        foreach (['deposit_required_amount', 'deposit_paid_amount', 'discount_amount'] as $field) {
            if ((float) ($payload[$field] ?? 0.0) < 0) {
                throw new \InvalidArgumentException(sprintf('Portable booking schema fixture violates reservations non-negative money contract for [%s].', $field));
            }
        }

        if (array_key_exists('final_bill_amount', $payload) && $payload['final_bill_amount'] !== null && (float) $payload['final_bill_amount'] < 0) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations non-negative money contract for [final_bill_amount].');
        }

        $status = (string) ($payload['status'] ?? 'Confirmed');
        if ($status === 'Reserved' && empty($payload['checked_in_at'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations reserved/check-in contract.');
        }
        if ($status === 'Cancelled' && empty($payload['cancelled_at'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations cancelled timestamp contract.');
        }
        if ($status === 'Completed' && empty($payload['checked_out_at'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations completed timestamp contract.');
        }
        if ($status === 'NoShow' && empty($payload['no_show_at'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations no-show timestamp contract.');
        }
        if (! empty($payload['billed_at']) && empty($payload['final_bill_amount'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations billed/final-bill contract.');
        }

        $depositStatus = (string) ($payload['deposit_status'] ?? 'NotRequired');
        if (! in_array($depositStatus, ['NotRequired', 'Pending', 'Paid', 'Refunded', 'PartiallyRefunded', 'Forfeited'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations deposit_status contract.');
        }

        $depositIntentStatus = (string) ($payload['deposit_intent_status'] ?? 'None');
        if (! in_array($depositIntentStatus, ['None', 'Submitted', 'Revoked'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations deposit_intent_status contract.');
        }
        if ($depositIntentStatus === 'Submitted' && empty($payload['deposit_intent_submitted_at'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations deposit intent submitted timestamp contract.');
        }
        if ($depositIntentStatus === 'Revoked' && (empty($payload['deposit_intent_submitted_at']) || empty($payload['deposit_intent_revoked_at']))) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates reservations deposit intent revoked timestamp contract.');
        }
    }

    /** @param array<string,mixed> $payload */
    protected function assertPortablePaymentPayloadMatchesCanonical(array $payload): void
    {
        if ((float) ($payload['amount'] ?? 0.0) < 0) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates payments non-negative amount contract.');
        }

        if (empty($payload['reservation_id'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates payments reservation linkage contract.');
        }

        if (trim((string) ($payload['payment_method'] ?? '')) === '') {
            throw new \InvalidArgumentException('Portable booking schema fixture violates payments payment_method contract.');
        }

        if (! in_array((string) ($payload['payment_type'] ?? 'Final'), ['Deposit', 'Final', 'Refund'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates payments payment_type contract.');
        }

        if (! in_array((string) ($payload['status'] ?? 'Pending'), ['Pending', 'Partial', 'Success', 'Failed', 'Refunded'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates payments status contract.');
        }
    }

    /** @param array<string,mixed> $payload */
    protected function assertPortableWaitingListPayloadMatchesCanonical(array $payload): void
    {
        if ((int) ($payload['guest_count'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates waiting_list guest-count contract.');
        }

        $status = (string) ($payload['status'] ?? 'Waiting');
        if (! in_array($status, ['Waiting', 'Notified', 'Seated', 'Cancelled'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates waiting_list status contract.');
        }

        if ($status === 'Notified' && (empty($payload['notified_at']) || empty($payload['notify_expires_at']))) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates waiting_list notify window contract.');
        }
        if ($status === 'Seated' && empty($payload['seated_at'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates waiting_list seated timestamp contract.');
        }
        if ($status === 'Cancelled' && empty($payload['cancelled_at'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates waiting_list cancelled timestamp contract.');
        }
        if (($payload['customer_response_status'] ?? null) !== null && empty($payload['customer_responded_at'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates waiting_list customer response timestamp contract.');
        }
        if (($payload['customer_confirmed_arrival_at'] ?? null) !== null && (string) ($payload['customer_response_status'] ?? null) !== 'Accepted') {
            throw new \InvalidArgumentException('Portable booking schema fixture violates waiting_list confirmed-arrival contract.');
        }
    }

    /** @param array<string,mixed> $payload */
    protected function assertPortableConversationPayloadMatchesCanonical(array $payload): void
    {
        if (trim((string) ($payload['conversation_id'] ?? '')) === '') {
            throw new \InvalidArgumentException('Portable booking schema fixture violates conversations primary key contract.');
        }

        if ((int) ($payload['branch_id'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates conversations branch scope contract.');
        }

        $status = (string) ($payload['status'] ?? 'Open');
        if (! in_array($status, ['Open', 'Pending', 'Closed', 'Spam'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates conversations status contract.');
        }

        $workflowState = (string) ($payload['workflow_state'] ?? 'Open');
        if (! in_array($workflowState, ['Open', 'Triaged', 'Assigned', 'PendingCustomer', 'Resolved', 'Closed'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates conversations workflow_state contract.');
        }

        if ($status === 'Closed' && $workflowState !== 'Closed') {
            throw new \InvalidArgumentException('Portable booking schema fixture violates conversations closed/status workflow alignment contract.');
        }

        if (! in_array((string) ($payload['channel'] ?? 'WebChat'), ['WebChat', 'Facebook', 'Zalo', 'Whatsapp', 'Instagram', 'Line', 'Other'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates conversations channel contract.');
        }
    }

    /** @param array<string,mixed> $payload */
    protected function assertPortableConversationMessagePayloadMatchesCanonical(array $payload): void
    {
        if (trim((string) ($payload['conversation_id'] ?? '')) === '') {
            throw new \InvalidArgumentException('Portable booking schema fixture violates conversation_messages conversation linkage contract.');
        }

        if (! in_array((string) ($payload['sender'] ?? 'user'), ['user', 'agent', 'system'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates conversation_messages sender contract.');
        }

        if (! in_array((string) ($payload['message_type'] ?? 'text'), ['text', 'image', 'file', 'location', 'unknown'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates conversation_messages type contract.');
        }

        if (trim((string) ($payload['message_text'] ?? '')) === '') {
            throw new \InvalidArgumentException('Portable booking schema fixture violates conversation_messages text contract.');
        }
    }

    /** @param array<string,mixed> $payload */
    protected function assertPortableTableHoldPayloadMatchesCanonical(array $payload): void
    {
        $start = $payload['start_time'] ?? null;
        $end = $payload['end_time'] ?? null;
        if (! $start instanceof Carbon || ! $end instanceof Carbon || $start->greaterThanOrEqualTo($end)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates table_holds time-range contract.');
        }

        if (empty($payload['expire_at'])) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates table_holds expire_at contract.');
        }

        if (! in_array((string) ($payload['hold_status'] ?? 'Holding'), ['Holding', 'Pending', 'Confirmed', 'Expired', 'Cancelled'], true)) {
            throw new \InvalidArgumentException('Portable booking schema fixture violates table_holds hold_status contract.');
        }
    }

    protected function table(string $table): Builder
    {
        return DB::table($table);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function createRestaurantTableWithSeats(int $seats, array $overrides = []): int
    {
        $templateCode = 'TPL-'.Str::upper(Str::random(6));

        DB::table('table_templates')->insert([
            'template_code' => $templateCode,
            'seats' => $seats,
            'description' => 'Test template seats '.$seats,
        ]);

        $templateId = (int) DB::table('table_templates')
            ->where('template_code', $templateCode)
            ->value('template_id');

        return $this->createRestaurantTable(array_merge([
            'template_id' => $templateId,
        ], $overrides));
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @param  list<int>  $tableIds
     */
    protected function createTableHold(array $overrides = [], array $tableIds = []): string
    {
        $now = $this->nowUtc();
        $start = isset($overrides['start_time']) && $overrides['start_time'] instanceof Carbon
            ? $overrides['start_time']
            : $now->copy()->addHour();
        $end = isset($overrides['end_time']) && $overrides['end_time'] instanceof Carbon
            ? $overrides['end_time']
            : $start->copy()->addHours(2);
        $durationMinutes = (int) ($overrides['duration_minutes'] ?? $start->diffInMinutes($end));
        $holdId = (string) ($overrides['hold_id'] ?? (string) Str::uuid());

        $payload = array_merge([
            'hold_id' => $holdId,
            'session_id' => 'sess-'.Str::lower(Str::random(12)),
            'user_id' => null,
            'branch_id' => 1,
            'confirmed_reservation_id' => null,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => $durationMinutes,
            'hold_status' => 'Holding',
            'created_at' => $now,
            'updated_at' => $now,
            'expire_at' => $now->copy()->addMinutes(15),
            'updated_by' => null,
            'row_version' => 1,
        ], $overrides);

        if (Schema::hasColumn('table_holds', 'active_session_hold_key')) {
            $payload['active_session_hold_key'] = in_array((string) ($payload['hold_status'] ?? 'Holding'), ['Holding', 'Pending'], true)
                ? ($payload['session_id'] ?? null)
                : null;
        }

        $this->assertPortableTableHoldPayloadMatchesCanonical($payload);
        $payload = $this->stripGeneratedColumnsForInsert('table_holds', $payload);

        DB::table('table_holds')->insert($payload);

        foreach ($tableIds ?: [$this->createRestaurantTable()] as $tableId) {
            try {
                DB::table('table_hold_details')->insert([
                    'hold_id' => $holdId,
                    'table_id' => $tableId,
                ]);
            } catch (\Throwable $exception) {
                if (! str_contains((string) $exception->getMessage(), 'table_hold_details overlap conflict with active reservation')) {
                    throw $exception;
                }

                $this->forceAttachTableHoldDetailForReadModel($holdId, (int) $tableId, $start, $end);
            }
        }

        return $holdId;
    }

    protected function forceAttachTableHoldDetailForReadModel(string $holdId, int $tableId, Carbon $start, Carbon $end): void
    {
        $hold = DB::table('table_holds')
            ->where('hold_id', $holdId)
            ->first(['hold_id', 'confirmed_reservation_id']);

        if ($hold === null) {
            throw new \RuntimeException('Table hold not found for forceAttachTableHoldDetailForReadModel.');
        }

        $conflictingReservationId = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->where('rt.table_id', $tableId)
            ->whereIn('r.status', ['Confirmed', 'Reserved'])
            ->where('r.start_time', '<', $end)
            ->where('r.end_time', '>', $start)
            ->orderBy('r.reservation_id')
            ->value('r.reservation_id');

        if ($conflictingReservationId === null) {
            throw new \RuntimeException('No active reservation conflict found for forceAttachTableHoldDetailForReadModel.');
        }

        $now = $this->nowUtc();
        DB::table('table_holds')
            ->where('hold_id', $holdId)
            ->update([
                'confirmed_reservation_id' => (int) $conflictingReservationId,
                'updated_at' => $now,
            ]);

        try {
            DB::table('table_hold_details')->insert([
                'hold_id' => $holdId,
                'table_id' => $tableId,
            ]);
        } finally {
            DB::table('table_holds')
                ->where('hold_id', $holdId)
                ->update([
                    'confirmed_reservation_id' => $hold->confirmed_reservation_id,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array{0:int,1:int}
     */
    protected function seedDepositReservation(array $overrides = []): array
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $customerId = $this->createUser(['role_name' => 'Customer']);

        $reservationId = $this->createReservation(array_merge([
            'user_id' => $customerId,
            'status' => 'Confirmed',
            'deposit_required_amount' => '100000.00',
            'deposit_paid_amount' => '0.00',
            'deposit_status' => 'Pending',
            'bill_currency' => 'VND',
            'row_version' => 1,
        ], $overrides));

        $this->attachReservationTable($reservationId);

        return [$staffId, $reservationId];
    }

    protected function staffHeaders(int $staffId, string $apiKey = 'test-staff-key'): array
    {
        return $this->issueStaffAuthHeaders($staffId, $apiKey);
    }

    protected function staffAuthHeaders(int $staffId, string $apiKey = 'test-staff-key'): array
    {
        return $this->issueStaffAuthHeaders($staffId, $apiKey);
    }

    protected function customerAuthHeaders(int $customerId, ?string $sessionId = null, array $context = []): array
    {
        return $this->issueCustomerAuthHeaders($customerId, $sessionId, $context);
    }

    /**
     * @return array<string,string>
     */
    private function issueStaffAuthHeaders(int $staffId, string $apiKey): array
    {
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);

        $existingKeys = (array) config('staff_auth.api_keys', []);
        $existingKeys[$apiKey] = $staffId;
        config()->set('staff_auth.api_keys', $existingKeys);

        $roleId = (int) (DB::table('users')->where('user_id', $staffId)->value('role_id') ?? 0);
        if ($roleId > 0) {
            $allowedRoleIds = array_values(array_unique(array_filter(array_map('intval', (array) config('staff_auth.allowed_role_ids', [])), static fn (int $value): bool => $value > 0)));
            if (! in_array($roleId, $allowedRoleIds, true)) {
                $allowedRoleIds[] = $roleId;
                sort($allowedRoleIds);
                config()->set('staff_auth.allowed_role_ids', $allowedRoleIds);
            }

            $roleName = trim((string) (DB::table('roles')->where('role_id', $roleId)->value('role_name') ?? ''));
            $roleCapabilities = (array) config('staff_capabilities.role_capabilities', []);
            $matchedCapabilities = [];
            foreach ($roleCapabilities as $configuredRoleName => $configuredCapabilities) {
                if (mb_strtolower(trim((string) $configuredRoleName)) !== mb_strtolower($roleName)) {
                    continue;
                }

                $matchedCapabilities = array_values(array_filter(array_map('strval', (array) $configuredCapabilities)));
                break;
            }

            if ($matchedCapabilities !== []) {
                $roleIdCapabilities = (array) config('staff_capabilities.role_id_capabilities', []);
                if (! array_key_exists($roleId, $roleIdCapabilities) && ! array_key_exists((string) $roleId, $roleIdCapabilities)) {
                    $roleIdCapabilities[$roleId] = $matchedCapabilities;
                    config()->set('staff_capabilities.role_id_capabilities', $roleIdCapabilities);
                }
            }
        }

        return [
            'X-Staff-Key' => $apiKey,
            'Accept' => 'application/json',
        ];
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,string>
     */
    private function issueCustomerAuthHeaders(int $customerId, ?string $sessionId, array $context): array
    {
        $this->requireBookingSchema();

        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.header', 'X-Customer-Token');
        config()->set('customer_auth.allow_bearer', false);
        config()->set('customer_auth.allow_legacy_user_auth_tokens', false);

        $customer = User::query()->with('role')->findOrFail($customerId);
        $roleId = (int) ($customer->role_id ?? 0);
        if ($roleId > 0) {
            $allowedRoleIds = array_values(array_unique(array_filter(array_map('intval', (array) config('customer_auth.allowed_role_ids', [])), static fn (int $value): bool => $value > 0)));
            if (! in_array($roleId, $allowedRoleIds, true)) {
                $allowedRoleIds[] = $roleId;
                sort($allowedRoleIds);
                config()->set('customer_auth.allowed_role_ids', $allowedRoleIds);
            }
        }

        $issued = app(CustomerAccessSessionStore::class)->issueForUser(
            $customer,
            now('UTC')->addMinutes(max(1, (int) config('customer_auth.access_session_ttl_minutes', 60))),
            $context,
        );

        $headers = [
            'X-Customer-Token' => $issued['plain_text_token'],
            'Accept' => 'application/json',
        ];

        if ($sessionId !== null && trim($sessionId) !== '') {
            $headers['X-Session-Id'] = trim($sessionId);
        }

        return $headers;
    }

    protected function withIdempotencyKey(array|string $headersOrKey, array|string|null $keyOrHeaders = null): array
    {
        if (is_array($headersOrKey) && is_string($keyOrHeaders)) {
            $headers = $headersOrKey;
            $key = $keyOrHeaders;
        } elseif (is_string($headersOrKey) && is_array($keyOrHeaders)) {
            $headers = $keyOrHeaders;
            $key = $headersOrKey;
        } elseif (is_string($headersOrKey) && $keyOrHeaders === null) {
            $headers = [];
            $key = $headersOrKey;
        } else {
            throw new \InvalidArgumentException('withIdempotencyKey expects (array $headers, string $key), (string $key, array $headers), or (string $key).');
        }

        return array_merge($headers, [
            'Idempotency-Key' => $key,
        ]);
    }

    protected function mockRuntimeSettings(): RuntimeSettingService
    {
        $mock = Mockery::mock(RuntimeSettingService::class);
        $mock->shouldReceive('bool')->andReturnUsing(static fn (string $key, bool $fallback): bool => $fallback);
        $mock->shouldReceive('int')->andReturnUsing(static fn (string $key, int $fallback): int => $fallback);
        $mock->shouldReceive('float')->andReturnUsing(static fn (string $key, float $fallback): float => $fallback);

        return $mock;
    }

    protected function mockReservationLocks(): ReservationLockService
    {
        $mock = Mockery::mock(ReservationLockService::class);
        $mock->shouldReceive('withLockKeys')->andReturnUsing(static fn (array $keys, callable $callback) => $callback());
        $mock->shouldReceive('withTableLocks')->andReturnUsing(static fn (array $tableIds, callable $callback) => $callback());

        return $mock;
    }

    protected function mockNotificationOutbox(): NotificationOutboxService
    {
        $mock = Mockery::mock(NotificationOutboxService::class);
        $mock->shouldIgnoreMissing();

        return $mock;
    }

    protected function mockTableStateService(): RestaurantTableStateService
    {
        $mock = Mockery::mock(RestaurantTableStateService::class);
        $mock->shouldIgnoreMissing();

        return $mock;
    }

    protected function makeCheckoutService(?NotificationOutboxService $notificationOutboxService = null): OrderSettlementWorkflow
    {
        $financialSync = new ReservationFinancialSyncService;
        $loyalty = new LoyaltyPointsService($financialSync, $this->mockRuntimeSettings());

        return new OrderSettlementWorkflow(
            $this->mockReservationLocks(),
            $notificationOutboxService ?? $this->mockNotificationOutbox(),
            $loyalty,
            $this->mockTableStateService(),
            $financialSync,
        );
    }

    protected function makeVoucherService(): ReservationVoucherWorkflow
    {
        return new ReservationVoucherWorkflow(
            $this->mockReservationLocks(),
            new ReservationFinancialSyncService,
            $this->mockRuntimeSettings(),
            new ReservationLoyaltySummaryReader($this->makeLoyaltyService()),
        );
    }

    protected function makeLoyaltyService(): LoyaltyPointsService
    {
        return new LoyaltyPointsService(new ReservationFinancialSyncService, $this->mockRuntimeSettings());
    }

    protected function makeTableOrderService(): StaffTableOrderService
    {
        return new StaffTableOrderService($this->mockReservationLocks());
    }
}
