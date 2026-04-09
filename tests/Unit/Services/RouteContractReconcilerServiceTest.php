<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RouteContractReconcilerService;
use App\Services\RouteInventoryGateService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RouteContractReconcilerServiceTest extends TestCase
{
    private string $root = 'storage/framework/testing/route_contract_reconcile';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->root));

        parent::tearDown();
    }

    public function test_reconcile_reports_clean_contract_for_current_runtime(): void
    {
        $report = app(RouteContractReconcilerService::class)->reconcile();

        $this->assertTrue($report['ok']);
        $this->assertSame([], $report['route_inventory']['drift']['missing_from_locked'] ?? []);
        $this->assertSame([], $report['route_inventory']['drift']['missing_from_runtime'] ?? []);
        $this->assertSame([], $report['staff_capabilities']['drift']['missing_from_config'] ?? []);
        $this->assertSame([], $report['staff_capabilities']['drift']['missing_from_runtime'] ?? []);
        $this->assertSame([], $report['staff_capabilities']['drift']['routes_missing_capability_middleware'] ?? []);
    }

    public function test_reconcile_candidate_preserves_locked_middleware_contracts_and_generates_alias_groups(): void
    {
        $report = app(RouteContractReconcilerService::class)->reconcile();
        $candidateRoutes = collect((array) ($report['route_inventory']['candidate']['expected_routes'] ?? []))
            ->keyBy(fn (array $route): string => strtoupper((string) ($route['method'] ?? 'GET')).' '.(string) ($route['uri'] ?? ''));

        $this->assertSame(
            ['App\\Http\\Middleware\\StaffApiKeyMiddleware', 'staff.capability:table.board.view'],
            $candidateRoutes['GET api/v1/staff/tables/board']['middleware_contains'] ?? []
        );

        $aliasGroup = collect((array) ($report['route_inventory']['candidate']['alias_groups'] ?? []))
            ->first(fn (array $group): bool => (string) ($group['canonical'] ?? '') === 'api/v1/staff/orders/{order_id}/settlement/finalize');

        $this->assertIsArray($aliasGroup);
        $this->assertSame('App\\Http\\Controllers\\Api\\Staff\\StaffCheckoutController@finalizeSettlement', $aliasGroup['canonical_action'] ?? null);
        $this->assertSame('api/v1/staff/orders/{order_id}/checkout', $aliasGroup['aliases'][0]['uri'] ?? null);
        $this->assertSame('App\\Http\\Controllers\\Api\\Staff\\StaffCheckoutController@checkout', $aliasGroup['aliases'][0]['action'] ?? null);
    }

    public function test_reconcile_can_refresh_custom_contract_files_from_runtime_candidates(): void
    {
        $definition = app(RouteInventoryGateService::class)->definition();
        $fixturePath = $this->root.'/route_inventory_gate.json';
        $configPath = $this->root.'/staff_capabilities.php';
        $fixtureAbsolutePath = base_path($fixturePath);
        $configAbsolutePath = base_path($configPath);

        File::ensureDirectoryExists(dirname($fixtureAbsolutePath));
        File::put($fixtureAbsolutePath, json_encode([
            'suite' => 'route_inventory',
            'description' => 'Synthetic stale route inventory for reconcile tests.',
            'expected_routes' => [
                (array) (($definition['expected_routes'] ?? [])[0] ?? []),
            ],
            'smoke_requests' => array_values((array) ($definition['smoke_requests'] ?? [])),
            'alias_groups' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        File::put($configAbsolutePath, <<<'PHP'
<?php

declare(strict_types=1);

return [
    'known_capabilities' => [],
    'route_capabilities' => [],
    'route_aliases' => [],
    'capability_aliases' => [],
];
PHP
        );

        $service = app(RouteContractReconcilerService::class);

        $before = $service->reconcile($fixturePath, $configPath);

        $this->assertFalse($before['ok']);
        $this->assertNotEmpty($before['route_inventory']['drift']['missing_from_locked'] ?? []);
        $this->assertNotEmpty($before['staff_capabilities']['drift']['missing_from_config'] ?? []);

        $writes = $service->writeReconciledArtifacts(true, true, $before, $fixturePath, $configPath);

        $this->assertSame($fixturePath, $writes['writes']['route_inventory']);
        $this->assertSame($configPath, $writes['writes']['staff_capabilities']);

        $after = $service->reconcile($fixturePath, $configPath);

        $this->assertTrue($after['ok']);
        $this->assertSame([], $after['route_inventory']['drift']['missing_from_locked'] ?? []);
        $this->assertSame([], $after['staff_capabilities']['drift']['missing_from_config'] ?? []);
    }
}
