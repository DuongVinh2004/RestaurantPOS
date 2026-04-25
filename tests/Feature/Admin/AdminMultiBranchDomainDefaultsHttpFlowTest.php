<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminMultiBranchDomainDefaultsHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.env_fallback_allowed_environments', ['testing']);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_branch_foundation_keeps_default_single_site_behavior_and_allows_explicit_branch_assignment(): void
    {
        [$adminId, $adminHeaders] = $this->adminHeaders('admin-multi-branch-domain-key');
        [$staffId, $staffHeaders] = $this->cashierHeaders('staff-multi-branch-shift-key');

        $secondaryBranchId = $this->createBranch([
            'branch_code' => 'DN01',
            'branch_name' => 'Da Nang 01',
        ]);
        config()->set('staff_capabilities.role_branch_scopes.Cashier', ['default', (string) $secondaryBranchId]);

        $defaultTable = $this->withHeaders($this->withIdempotencyKey($adminHeaders, 'idem-admin-branch-table-default'))
            ->postJson('/api/v1/admin/restaurant/tables', [
                'table_code' => 'BR-TBL-DEFAULT',
                'template_id' => $this->createTableTemplate(['seats' => 4]),
                'zone' => 'Main',
            ]);

        $defaultTable->assertCreated()
            ->assertJsonPath('data.branch_id', 1)
            ->assertJsonPath('data.branch.branch_code', 'MAIN');

        $secondaryTable = $this->withHeaders($this->withIdempotencyKey($adminHeaders, 'idem-admin-branch-table-secondary'))
            ->postJson('/api/v1/admin/restaurant/tables', [
                'branch_id' => $secondaryBranchId,
                'table_code' => 'BR-TBL-DN01',
                'template_id' => $this->createTableTemplate(['seats' => 6]),
                'zone' => 'DN',
            ]);

        $secondaryTable->assertCreated()
            ->assertJsonPath('data.branch_id', $secondaryBranchId)
            ->assertJsonPath('data.branch.branch_id', $secondaryBranchId);

        $ingredientId = $this->createIngredient([
            'code' => 'ING-BR-01',
            'name' => 'Branch Rice',
            'unit_code' => 'kg',
        ]);
        $supplierId = $this->createSupplier([
            'code' => 'SUP-BR-01',
            'name' => 'Branch Supplier',
        ]);

        $defaultMovement = $this->withHeaders($this->withIdempotencyKey($adminHeaders, 'idem-admin-branch-movement-default'))
            ->postJson('/api/v1/admin/inventory/ingredients/'.$ingredientId.'/movements', [
                'movement_type' => 'StockIn',
                'quantity' => '5.000',
                'unit_code' => 'kg',
                'notes' => 'Default branch stock in',
            ]);

        $defaultMovement->assertCreated()
            ->assertJsonPath('data.branch_id', 1)
            ->assertJsonPath('meta.stock_on_hand', '5.000');

        $purchaseOrder = $this->withHeaders($this->withIdempotencyKey($adminHeaders, 'idem-admin-branch-po-secondary'))
            ->postJson('/api/v1/admin/inventory/purchase-orders', [
                'branch_id' => $secondaryBranchId,
                'supplier_id' => $supplierId,
                'order_code' => 'PO-BRANCH-0001',
                'lines' => [
                    [
                        'ingredient_id' => $ingredientId,
                        'ordered_quantity' => '2.000',
                        'unit_code' => 'kg',
                    ],
                ],
            ]);

        $purchaseOrder->assertCreated()
            ->assertJsonPath('data.branch_id', $secondaryBranchId)
            ->assertJsonPath('data.branch.branch_id', $secondaryBranchId);

        $openShift = $this->withHeaders($this->withIdempotencyKey($staffHeaders, 'idem-staff-branch-shift-open'))
            ->postJson('/api/v1/staff/cashier/shifts/open', [
                'branch_id' => $secondaryBranchId,
                'opening_float_amount' => 50000,
                'currency' => 'VND',
                'terminal_code' => 'DN-POS-01',
            ]);

        $openShift->assertCreated()
            ->assertJsonPath('data.branch_id', $secondaryBranchId)
            ->assertJsonPath('data.branch.branch_id', $secondaryBranchId)
            ->assertJsonPath('data.currency', 'VND');

        self::assertSame($adminId, $adminId);
        self::assertSame($staffId, $staffId);
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    private function adminHeaders(string $apiKey): array
    {
        $adminRoleId = $this->ensureRole('Admin');
        $adminId = $this->createUser(['role_id' => $adminRoleId, 'role_name' => 'Admin']);

        $allowedRoleIds = array_values(array_unique(array_map(
            static fn ($value): int => (int) $value,
            array_merge((array) config('staff_auth.allowed_role_ids', []), [$adminRoleId])
        )));
        config()->set('staff_auth.allowed_role_ids', $allowedRoleIds);

        $roleCapabilities = (array) config('staff_capabilities.role_id_capabilities', []);
        $roleCapabilities[$adminRoleId] = ['*'];
        config()->set('staff_capabilities.role_id_capabilities', $roleCapabilities);

        return [$adminId, $this->staffHeaders($adminId, $apiKey)];
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    private function cashierHeaders(string $apiKey): array
    {
        $staffRoleId = $this->ensureRole('Cashier');
        $staffId = $this->createUser(['role_id' => $staffRoleId, 'role_name' => 'Cashier']);

        $allowedRoleIds = array_values(array_unique(array_map(
            static fn ($value): int => (int) $value,
            array_merge((array) config('staff_auth.allowed_role_ids', []), [$staffRoleId])
        )));
        config()->set('staff_auth.allowed_role_ids', $allowedRoleIds);

        $roleCapabilities = (array) config('staff_capabilities.role_id_capabilities', []);
        $roleCapabilities[$staffRoleId] = ['cashier.shift.manage', 'settlement.manage'];
        config()->set('staff_capabilities.role_id_capabilities', $roleCapabilities);

        return [$staffId, $this->staffHeaders($staffId, $apiKey)];
    }

    private function createTableTemplate(array $overrides = []): int
    {
        $templateCode = 'TPL-BR-'.strtoupper(substr((string) md5((string) microtime(true)), 0, 6));

        $this->table('table_templates')->insert(array_merge([
            'template_code' => $templateCode,
            'seats' => 4,
            'description' => 'Branch test template',
        ], $overrides));

        return (int) $this->table('table_templates')->where('template_code', $templateCode)->value('template_id');
    }
}
