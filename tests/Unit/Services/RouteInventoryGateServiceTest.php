<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\ApiContract\Services\RouteInventoryGateService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RouteInventoryGateServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(app_path('Http/Controllers/Api/Nested/CustomerNestedInventoryProbeController.php'));
        File::deleteDirectory(app_path('Http/Controllers/Api/Nested'));
        parent::tearDown();
    }

    public function test_definition_reads_canonical_route_inventory_fixture(): void
    {
        $definition = app(RouteInventoryGateService::class)->definition();

        $this->assertSame('route_inventory', $definition['suite']);
        $this->assertSame('tests/fixtures/route_inventory_gate.json', $definition['definition_path']);
        $this->assertNotEmpty($definition['expected_routes']);
        $this->assertNotEmpty($definition['smoke_requests']);
    }

    public function test_definition_keeps_operational_boundary_statuses_for_health_smoke_requests(): void
    {
        $definition = app(RouteInventoryGateService::class)->definition();
        $smoke = collect($definition['smoke_requests'])->keyBy('key');

        $this->assertSame('api/v1/health', $smoke['health']['uri']);
        $this->assertSame([200, 503], $smoke['health']['allowed_statuses']);
        $this->assertSame('api/v1/health/detailed', $smoke['health_detailed']['uri']);
        $this->assertSame([401, 403], $smoke['health_detailed']['allowed_statuses']);
        $this->assertSame('api/v1/health/redis', $smoke['health_redis']['uri']);
        $this->assertSame([401, 403], $smoke['health_redis']['allowed_statuses']);
        $this->assertSame('api/v1/payments/providers/simulated/webhooks', $smoke['webhook_boundary']['uri']);
        $this->assertSame([202, 401, 422], $smoke['webhook_boundary']['allowed_statuses']);
        $this->assertSame('api/v1/staff/tables/board', $smoke['staff_tables_board_auth']['uri']);
        $this->assertSame([401], $smoke['staff_tables_board_auth']['allowed_statuses']);
        $this->assertSame('api/v1/admin/settings/branches', $smoke['admin_branches_auth']['uri']);
        $this->assertSame([401], $smoke['admin_branches_auth']['allowed_statuses']);
    }

    public function test_definition_parses_optional_middleware_contracts_for_locked_routes(): void
    {
        $definition = app(RouteInventoryGateService::class)->definition();
        $routes = collect($definition['expected_routes'])->keyBy('key');

        $this->assertSame(
            ['App\\Http\\Middleware\\StaffApiKeyMiddleware', 'staff.capability:ops.health.view'],
            $routes['get_v1healthdetailed']['middleware_contains'] ?? []
        );
        $this->assertSame(
            ['App\\Http\\Middleware\\StaffApiKeyMiddleware', 'staff.capability:ops.health.view'],
            $routes['health_redis']['middleware_contains'] ?? []
        );
        $this->assertSame(
            ['App\\Http\\Middleware\\StaffApiKeyMiddleware', 'staff.capability:table.board.view'],
            $routes['staff_tables_board']['middleware_contains'] ?? []
        );
        $this->assertSame(
            ['App\\Http\\Middleware\\StaffApiKeyMiddleware'],
            $routes['metrics']['middleware_contains'] ?? []
        );
    }

    public function test_inspect_reports_clean_route_inventory_for_current_runtime(): void
    {
        $report = app(RouteInventoryGateService::class)->inspect();

        $this->assertTrue($report['ok']);
        $this->assertTrue($report['checks']['route_action_methods']['ok']);
        $this->assertTrue($report['checks']['expected_routes']['ok']);
        $this->assertTrue($report['checks']['public_controllers']['ok']);
    }

    public function test_public_controller_inventory_has_no_runtime_unlocked_controllers(): void
    {
        $report = app(RouteInventoryGateService::class)->inspect();

        $this->assertSame([], $report['checks']['public_controllers']['meta']['unlocked_public_controllers'] ?? []);
    }

    public function test_public_controller_inventory_discovers_nested_customer_controllers(): void
    {
        $nestedPath = app_path('Http/Controllers/Api/Nested/CustomerNestedInventoryProbeController.php');
        File::ensureDirectoryExists(dirname($nestedPath));
        File::put($nestedPath, <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Nested;

use App\Http\Controllers\Controller;

class CustomerNestedInventoryProbeController extends Controller
{
    public function __invoke(): void
    {
    }
}
PHP
        );

        require_once $nestedPath;

        $controllers = app(RouteInventoryGateService::class)->discoverPublicControllers();

        $this->assertContains('App\Http\Controllers\Api\Nested\CustomerNestedInventoryProbeController', $controllers);
    }

    public function test_public_controller_inventory_discovers_customer_and_webhook_controllers(): void
    {
        $controllers = app(RouteInventoryGateService::class)->discoverPublicControllers();

        $this->assertContains('App\\Modules\\Catalog\\Http\\Controllers\\Customer\\MenuCatalogController', $controllers);
        $this->assertContains('App\\Modules\\Reservations\\Http\\Controllers\\Customer\\ReservationSelfServiceController', $controllers);
        $this->assertContains('App\\Modules\\Payments\\Http\\Controllers\\Webhook\\PaymentProviderWebhookController', $controllers);
    }
}
