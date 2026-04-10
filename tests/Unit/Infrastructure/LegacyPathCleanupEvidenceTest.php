<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Http\Controllers\Api\CustomerWaitingListController;
use App\Http\Controllers\Api\Staff\StaffTableBoardController;
use App\Http\Requests\Customer\ListOwnerWaitingListRequest;
use App\Http\Requests\Customer\RespondOwnerWaitingListRequest;
use App\Http\Requests\Customer\StoreOwnerWaitingListRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegacyPathCleanupEvidenceTest extends TestCase
{
    public function test_app_namespace_path_parity_has_no_mismatches(): void
    {
        $appPath = base_path('app');
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));
        $mismatches = [];

        foreach ($rii as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (! is_string($contents)) {
                continue;
            }

            if (! preg_match('/^namespace\s+([^;]+);/m', $contents, $nsMatch)) {
                continue;
            }

            if (! preg_match('/^class\s+(\w+)/m', $contents, $classMatch)) {
                continue;
            }

            $declared = trim($nsMatch[1]).'\\'.trim($classMatch[1]);
            $relative = str_replace([$appPath.DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR], ['', '', '\\'], $file->getPathname());
            $expected = 'App\\'.$relative;

            if ($declared !== $expected) {
                $mismatches[] = [$file->getPathname(), $declared, $expected];
            }
        }

        self::assertSame([], $mismatches, 'Found namespace/path mismatches under app/.');
    }

    public function test_customer_waiting_list_owner_flow_stays_on_canonical_controller_service_and_requests(): void
    {
        $store = Route::getRoutes()->getByAction(CustomerWaitingListController::class.'@store');
        $show = Route::getRoutes()->getByAction(CustomerWaitingListController::class.'@show');
        $accept = Route::getRoutes()->getByAction(CustomerWaitingListController::class.'@accept');
        $confirm = Route::getRoutes()->getByAction(CustomerWaitingListController::class.'@confirmArrival');
        $decline = Route::getRoutes()->getByAction(CustomerWaitingListController::class.'@decline');
        $cancel = Route::getRoutes()->getByAction(CustomerWaitingListController::class.'@cancel');

        self::assertNotNull($store);
        self::assertNotNull($show);
        self::assertNotNull($accept);
        self::assertNotNull($confirm);
        self::assertNotNull($decline);
        self::assertNotNull($cancel);

        $controller = app(CustomerWaitingListController::class);
        self::assertInstanceOf(CustomerWaitingListController::class, $controller);

        $this->assertMethodRequestType('index', ListOwnerWaitingListRequest::class);
        $this->assertMethodRequestType('store', StoreOwnerWaitingListRequest::class);
        $this->assertMethodRequestType('show', Request::class);
        $this->assertMethodRequestType('accept', RespondOwnerWaitingListRequest::class);
        $this->assertMethodRequestType('confirmArrival', RespondOwnerWaitingListRequest::class);
        $this->assertMethodRequestType('decline', RespondOwnerWaitingListRequest::class);
        $this->assertMethodRequestType('cancel', RespondOwnerWaitingListRequest::class);
    }

    public function test_legacy_waiting_list_and_staff_order_read_residue_has_been_removed(): void
    {
        $paths = [
            'app/Http/Requests/WaitingList/AcceptCustomerWaitingListInviteRequest.php',
            'app/Http/Requests/WaitingList/CancelCustomerWaitingListRequest.php',
            'app/Http/Requests/WaitingList/ConfirmCustomerWaitingListArrivalRequest.php',
            'app/Http/Requests/WaitingList/DeclineCustomerWaitingListInviteRequest.php',
            'app/Http/Requests/WaitingList/ListCustomerWaitingListRequest.php',
            'app/Http/Requests/WaitingList/MutateCustomerWaitingListInviteRequest.php',
            'app/Http/Requests/WaitingList/StoreCustomerWaitingListRequest.php',
            'app/Http/Requests/WaitingList/Concerns/AuthorizesCustomerWaitingListSelfService.php',
            'app/Services/WaitingList/CustomerWaitingListSelfService.php',
            'app/Services/StaffOrderReadController.php',
            'app/Services/Staff/StaffOrderReadController.php',
        ];

        foreach ($paths as $path) {
            self::assertFileDoesNotExist(base_path($path), sprintf('Legacy runtime residue should be removed: %s', $path));
        }
    }

    public function test_staff_table_board_legacy_alias_is_kept_as_explicit_compatibility_route(): void
    {
        $canonical = Route::getRoutes()->getByAction(StaffTableBoardController::class.'@index');
        $legacy = Route::getRoutes()->getByAction(StaffTableBoardController::class.'@legacyIndex');

        self::assertNotNull($canonical);
        self::assertSame('api/v1/staff/tables/board', $canonical->uri());

        self::assertNotNull($legacy);
        self::assertSame('api/v1/staff/table-board', $legacy->uri());
    }

    private function assertMethodRequestType(string $method, string $expectedClass): void
    {
        $reflection = new \ReflectionMethod(CustomerWaitingListController::class, $method);
        $parameters = $reflection->getParameters();

        self::assertNotEmpty($parameters, sprintf('Expected %s::%s to declare a request parameter.', CustomerWaitingListController::class, $method));

        $requestParameter = $parameters[count($parameters) - 1];
        $type = $requestParameter->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame($expectedClass, $type->getName(), sprintf('Expected %s::%s to use %s.', CustomerWaitingListController::class, $method, $expectedClass));
    }
}
