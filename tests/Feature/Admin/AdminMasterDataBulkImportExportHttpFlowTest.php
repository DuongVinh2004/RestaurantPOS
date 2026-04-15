<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Modules\PrivacyAudit\Domain\Models\AuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class AdminMasterDataBulkImportExportHttpFlowTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        Carbon::setTestNow(Carbon::parse('2026-04-05 09:00:00', 'UTC'));
    }

    public function test_admin_can_dry_run_and_commit_branch_import_from_csv_with_batch_audit(): void
    {
        [$adminId, $headers] = $this->adminHeaders('bulk-branches-key');
        $existingBranchId = $this->createBranch([
            'branch_code' => 'HCM01',
            'branch_name' => 'Old HCM',
            'description' => 'Old branch',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
            'is_active' => true,
            'is_default' => false,
        ]);

        $csv = implode("\n", [
            'branch_code,branch_name,description,timezone,currency,is_active,is_default',
            'HCM01,Ho Chi Minh 01,Updated branch,Asia/Ho_Chi_Minh,VND,1,0',
            'DAN01,Da Nang 01,New branch,Asia/Ho_Chi_Minh,VND,1,0',
        ]);

        $dryRun = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/settings/branches/import', [
                'mode' => 'dry_run',
                'format' => 'csv',
                'content' => $csv,
            ]);

        $dryRun
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_master_data_import_dry_run')
            ->assertJsonPath('data.domain', 'branches')
            ->assertJsonPath('data.can_commit', true)
            ->assertJsonPath('data.summary.create_count', 1)
            ->assertJsonPath('data.summary.update_count', 1)
            ->assertJsonPath('data.summary.invalid_rows', 0);

        $commit = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/settings/branches/import', [
                'mode' => 'commit',
                'format' => 'csv',
                'content' => $csv,
            ]);

        $commit
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_master_data_import_committed')
            ->assertJsonPath('data.commit.created', 1)
            ->assertJsonPath('data.commit.updated', 1)
            ->assertJsonPath('data.commit.unchanged', 0);

        self::assertSame('Ho Chi Minh 01', DB::table('branches')->where('branch_id', $existingBranchId)->value('branch_name'));
        self::assertSame('Da Nang 01', DB::table('branches')->where('branch_code', 'DAN01')->value('branch_name'));

        $updatedLog = $this->assertAuditLogRecorded('master_data.branch.updated', 'branch', $existingBranchId);
        self::assertSame($adminId, $updatedLog->actor_user_id);

        $batchLog = AuditLog::query()
            ->where('action', 'master_data.import.committed')
            ->orderByDesc('audit_id')
            ->first();

        self::assertNotNull($batchLog);
        self::assertSame($adminId, $batchLog->actor_user_id);
        self::assertSame('branches', (string) data_get($batchLog->summary_json, 'domain'));
        $this->assertAuditSubjectRecorded($batchLog, 'master_data_domain', 'branches', 'domain');
    }

    public function test_branch_import_commit_rejects_duplicate_upsert_keys(): void
    {
        [, $headers] = $this->adminHeaders('bulk-branches-duplicate-key');

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/settings/branches/import', [
                'mode' => 'commit',
                'format' => 'json',
                'rows' => [
                    [
                        'branch_code' => 'HN01',
                        'branch_name' => 'Ha Noi 01',
                    ],
                    [
                        'branch_code' => 'HN01',
                        'branch_name' => 'Ha Noi Duplicate',
                    ],
                ],
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('meta.action', 'admin_master_data_import_commit_rejected')
            ->assertJsonPath('data.can_commit', false)
            ->assertJsonPath('data.summary.invalid_rows', 2);
    }

    public function test_admin_can_dry_run_branch_import_from_uploaded_csv_file(): void
    {
        [, $headers] = $this->adminHeaders('bulk-branches-file-key');

        $file = UploadedFile::fake()->createWithContent('branches.csv', implode("\n", [
            'branch_code,branch_name,description,timezone,currency,is_active,is_default',
            'SGN02,Saigon 02,Upload branch,Asia/Ho_Chi_Minh,VND,1,0',
        ]));

        $response = $this->withHeaders($headers)
            ->post('/api/v1/admin/settings/branches/import', [
                'mode' => 'dry_run',
                'file' => $file,
            ], [
                'Accept' => 'application/json',
                ...$headers,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_master_data_import_dry_run')
            ->assertJsonPath('data.can_commit', true)
            ->assertJsonPath('data.summary.create_count', 1);
    }

    public function test_admin_can_export_bulk_master_data_as_json_and_csv(): void
    {
        [, $headers] = $this->adminHeaders('bulk-export-key');
        $this->createBranch([
            'branch_code' => 'EXPORT01',
            'branch_name' => 'Export Branch',
            'is_default' => false,
        ]);

        $categoryId = $this->ensureMenuCategory('Export Specials');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'EXPORT-BURGER',
            'name' => 'Export Burger',
        ]);
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '125000.00',
            'currency' => 'VND',
            'effective_from' => Carbon::parse('2026-04-01 00:00:00', 'UTC'),
            'effective_to' => null,
        ]);

        $jsonResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/admin/settings/branches/export?format=json');

        $jsonResponse
            ->assertOk()
            ->assertJsonPath('meta.action', 'admin_master_data_export')
            ->assertJsonPath('meta.domain', 'branches')
            ->assertJsonPath('meta.format', 'json');

        $csvResponse = $this->withHeaders($headers)
            ->get('/api/v1/admin/menu/items/export?format=csv');

        $csvResponse->assertOk();
        $csv = $csvResponse->streamedContent();

        self::assertStringContainsString('code,name,category_name,description,img_url,is_available,is_preorder_enabled,preorder_quota_per_day,preorder_cutoff_minutes', trim((string) strtok($csv, PHP_EOL)));
        self::assertStringContainsString('EXPORT-BURGER', $csv);
        self::assertStringContainsString('Export Specials', $csv);
    }

    public function test_menu_item_and_price_imports_support_upsert_and_noop_replay(): void
    {
        [$adminId, $headers] = $this->adminHeaders('bulk-menu-key');
        $categoryId = $this->ensureMenuCategory('Bulk Specials');
        $this->createMenuItem([
            'category_id' => $categoryId,
            'code' => 'BULK-BURGER',
            'name' => 'Old Burger',
            'description' => 'Old desc',
        ]);

        $itemCommit = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/menu/items/import', [
                'mode' => 'commit',
                'format' => 'json',
                'rows' => [
                    [
                        'code' => 'BULK-BURGER',
                        'name' => 'Bulk Burger',
                        'category_name' => 'Bulk Specials',
                        'description' => 'Updated desc',
                        'is_available' => true,
                        'is_preorder_enabled' => true,
                        'preorder_quota_per_day' => 8,
                        'preorder_cutoff_minutes' => 90,
                    ],
                    [
                        'code' => 'BULK-PASTA',
                        'name' => 'Bulk Pasta',
                        'category_name' => 'Bulk Specials',
                        'description' => 'New pasta',
                        'is_available' => true,
                        'is_preorder_enabled' => false,
                        'preorder_cutoff_minutes' => 0,
                    ],
                ],
            ]);

        $itemCommit
            ->assertOk()
            ->assertJsonPath('data.commit.created', 1)
            ->assertJsonPath('data.commit.updated', 1);

        $burgerId = (int) DB::table('menu_items')->where('code', 'BULK-BURGER')->value('item_id');
        $pastaId = (int) DB::table('menu_items')->where('code', 'BULK-PASTA')->value('item_id');
        self::assertSame('Bulk Burger', DB::table('menu_items')->where('item_id', $burgerId)->value('name'));
        self::assertSame('Bulk Pasta', DB::table('menu_items')->where('item_id', $pastaId)->value('name'));

        $this->assertAuditLogRecorded('master_data.menu_item.updated', 'menu_item', $burgerId);
        $createdLog = $this->assertAuditLogRecorded('master_data.menu_item.created', 'menu_item', $pastaId);
        self::assertSame($adminId, $createdLog->actor_user_id);

        $priceSeedId = $this->createMenuItemPrice([
            'item_id' => $burgerId,
            'price' => '100000.00',
            'currency' => 'VND',
            'effective_from' => Carbon::parse('2026-04-01 00:00:00', 'UTC'),
            'effective_to' => null,
        ]);

        $priceCommit = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/menu/prices/import', [
                'mode' => 'commit',
                'format' => 'json',
                'rows' => [
                    [
                        'item_code' => 'BULK-BURGER',
                        'price' => '120000.00',
                        'currency' => 'VND',
                        'effective_from' => '2026-04-01T00:00:00Z',
                    ],
                    [
                        'item_code' => 'BULK-PASTA',
                        'price' => '98000.00',
                        'currency' => 'VND',
                        'effective_from' => '2026-04-01T00:00:00Z',
                    ],
                ],
            ]);

        $priceCommit
            ->assertOk()
            ->assertJsonPath('data.commit.created', 1)
            ->assertJsonPath('data.commit.updated', 1);

        self::assertSame('120000.00', number_format((float) DB::table('menu_item_prices')->where('price_id', $priceSeedId)->value('price'), 2, '.', ''));

        $pastaPriceId = (int) DB::table('menu_item_prices')
            ->join('menu_items', 'menu_items.item_id', '=', 'menu_item_prices.item_id')
            ->where('menu_items.code', 'BULK-PASTA')
            ->value('menu_item_prices.price_id');

        $this->assertAuditLogRecorded('master_data.menu_price.updated', 'menu_price', $priceSeedId);
        $this->assertAuditLogRecorded('master_data.menu_price.created', 'menu_price', $pastaPriceId);

        $replay = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/menu/items/import', [
                'mode' => 'commit',
                'format' => 'json',
                'rows' => [
                    [
                        'code' => 'BULK-BURGER',
                        'name' => 'Bulk Burger',
                        'category_name' => 'Bulk Specials',
                        'description' => 'Updated desc',
                        'is_available' => true,
                        'is_preorder_enabled' => true,
                        'preorder_quota_per_day' => 8,
                        'preorder_cutoff_minutes' => 90,
                    ],
                    [
                        'code' => 'BULK-PASTA',
                        'name' => 'Bulk Pasta',
                        'category_name' => 'Bulk Specials',
                        'description' => 'New pasta',
                        'is_available' => true,
                        'is_preorder_enabled' => false,
                        'preorder_cutoff_minutes' => 0,
                    ],
                ],
            ]);

        $replay
            ->assertOk()
            ->assertJsonPath('data.summary.unchanged_count', 2)
            ->assertJsonPath('data.commit.created', 0)
            ->assertJsonPath('data.commit.updated', 0);
    }

    public function test_voucher_and_loyalty_imports_validate_rows_and_commit_successfully(): void
    {
        [$adminId, $headers] = $this->adminHeaders('bulk-benefits-key');
        $freeItemId = $this->createMenuItem([
            'code' => 'FREE-ICE-TEA',
            'name' => 'Free Ice Tea',
        ]);
        self::assertGreaterThan(0, $freeItemId);

        $invalidVoucher = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/benefits/vouchers/import', [
                'mode' => 'dry_run',
                'format' => 'json',
                'rows' => [
                    [
                        'code' => 'FREE-FAIL',
                        'discount_type' => 'FreeItem',
                        'free_item_code' => 'MISSING-ITEM',
                        'free_item_qty' => 1,
                    ],
                ],
            ]);

        $invalidVoucher
            ->assertOk()
            ->assertJsonPath('data.can_commit', false)
            ->assertJsonPath('data.summary.invalid_rows', 1);

        $voucherCommit = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/benefits/vouchers/import', [
                'mode' => 'commit',
                'format' => 'json',
                'rows' => [
                    [
                        'code' => 'FREE-ICE',
                        'description' => 'Free tea voucher',
                        'discount_type' => 'FreeItem',
                        'free_item_code' => 'FREE-ICE-TEA',
                        'free_item_qty' => 1,
                        'is_active' => true,
                    ],
                ],
            ]);

        $voucherCommit
            ->assertOk()
            ->assertJsonPath('data.commit.created', 1);

        $voucherId = (int) DB::table('vouchers')->where('code', 'FREE-ICE')->value('voucher_id');
        $voucherLog = $this->assertAuditLogRecorded('master_data.voucher.created', 'voucher', $voucherId);
        self::assertSame($adminId, $voucherLog->actor_user_id);

        $loyaltyCommit = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/benefits/loyalty-tiers/import', [
                'mode' => 'commit',
                'format' => 'json',
                'rows' => [
                    [
                        'tier_code' => 'PLATINUM',
                        'tier_name' => 'Platinum',
                        'min_points' => 2500,
                        'benefits_json' => [
                            'priority_booking' => true,
                            'discount_percent' => 10,
                        ],
                        'is_active' => true,
                    ],
                ],
            ]);

        $loyaltyCommit
            ->assertOk()
            ->assertJsonPath('data.commit.created', 1);

        $tierId = (int) DB::table('loyalty_tiers')->where('tier_code', 'PLATINUM')->value('tier_id');
        $tierLog = $this->assertAuditLogRecorded('master_data.loyalty_tier.created', 'loyalty_tier', $tierId);
        self::assertSame($adminId, $tierLog->actor_user_id);
    }

    public function test_table_import_applies_create_and_rejects_operationally_linked_mutation(): void
    {
        [$adminId, $headers] = $this->adminHeaders('bulk-tables-key');
        $templateCode = 'BULK-TPL-8';
        DB::table('table_templates')->updateOrInsert([
            'template_code' => $templateCode,
        ], [
            'seats' => 8,
            'description' => 'Bulk template',
        ]);

        $createCommit = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/restaurant/tables/import', [
                'mode' => 'commit',
                'format' => 'json',
                'rows' => [
                    [
                        'branch_code' => 'MAIN',
                        'table_code' => 'BULK-TABLE-01',
                        'template_code' => $templateCode,
                        'zone' => 'Garden',
                        'status' => 'Available',
                        'description' => 'Bulk created table',
                        'price' => '0.00',
                    ],
                ],
            ]);

        $createCommit
            ->assertOk()
            ->assertJsonPath('data.commit.created', 1);

        $createdTableId = (int) DB::table('restaurant_tables')->where('table_code', 'BULK-TABLE-01')->value('table_id');
        $tableLog = $this->assertAuditLogRecorded('master_data.restaurant_table.created', 'restaurant_table', $createdTableId);
        self::assertSame($adminId, $tableLog->actor_user_id);

        $linkedTableId = $this->createRestaurantTableWithSeats(4, [
            'table_code' => 'LIVE-TABLE-01',
            'zone' => 'Main',
        ]);
        $linkedTemplateCode = (string) DB::table('table_templates')
            ->join('restaurant_tables', 'restaurant_tables.template_id', '=', 'table_templates.template_id')
            ->where('restaurant_tables.table_id', $linkedTableId)
            ->value('table_templates.template_code');
        $reservationId = $this->createReservation([
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-04-05 10:00:00', 'UTC'),
            'end_time' => Carbon::parse('2026-04-05 12:00:00', 'UTC'),
        ]);
        $this->attachReservationTable($reservationId, $linkedTableId);

        $guardedDryRun = $this->withHeaders($headers)
            ->postJson('/api/v1/admin/restaurant/tables/import', [
                'mode' => 'dry_run',
                'format' => 'json',
                'rows' => [
                    [
                        'branch_code' => 'MAIN',
                        'table_code' => 'LIVE-TABLE-01',
                        'template_code' => $linkedTemplateCode,
                        'zone' => 'VIP',
                        'status' => 'Available',
                    ],
                ],
            ]);

        $guardedDryRun
            ->assertOk()
            ->assertJsonPath('data.can_commit', false)
            ->assertJsonPath('data.summary.invalid_rows', 1);
    }

    public function test_non_admin_staff_cannot_access_bulk_master_data_routes(): void
    {
        $staffRoleId = $this->ensureRole('Staff');
        $staffId = $this->createUser(['role_id' => $staffRoleId, 'role_name' => 'Staff']);
        config()->set('staff_auth.allowed_role_ids', [$staffRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $staffRoleId => ['menu.manage'],
        ]);

        $response = $this->withHeaders($this->staffHeaders($staffId, 'bulk-forbidden-key'))
            ->postJson('/api/v1/admin/settings/branches/import', [
                'mode' => 'dry_run',
                'format' => 'json',
                'rows' => [
                    ['branch_code' => 'X', 'branch_name' => 'X'],
                ],
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('required_capability', 'settings.manage');
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    private function adminHeaders(string $apiKey): array
    {
        $adminRoleId = $this->ensureRole('Admin');
        $adminId = $this->createUser(['role_id' => $adminRoleId, 'role_name' => 'Admin']);

        config()->set('staff_auth.allowed_role_ids', [$adminRoleId]);
        config()->set('staff_capabilities.role_id_capabilities', [
            $adminRoleId => ['*'],
        ]);

        return [$adminId, $this->staffHeaders($adminId, $apiKey)];
    }
}
