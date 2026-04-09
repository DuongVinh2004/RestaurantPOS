<?php

declare(strict_types=1);

namespace Tests\Feature\WaitingList;

use App\Http\Controllers\Api\CustomerWaitingListController;
use App\Services\WaitingList\CustomerWaitingListSelfService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WaitingListRuntimeContractDriftTest extends TestCase
{
    public function test_customer_waiting_list_routes_do_not_wire_legacy_guest_self_service_residue(): void
    {
        $legacyClass = CustomerWaitingListSelfService::class;
        $customerController = CustomerWaitingListController::class;
        $customerWaitingRoutes = 0;

        foreach (Route::getRoutes() as $route) {
            $uri = (string) $route->uri();
            if (! str_starts_with($uri, 'api/v1/waiting-list')) {
                continue;
            }

            $customerWaitingRoutes++;
            $actionName = (string) ($route->getActionName() ?? '');
            $uses = $route->getAction()['uses'] ?? null;
            $serializedUses = is_string($uses) ? $uses : json_encode($uses);

            $this->assertStringContainsString($customerController, $actionName);
            $this->assertStringNotContainsString($legacyClass, $actionName);
            $this->assertStringNotContainsString($legacyClass, (string) $serializedUses);
        }

        $this->assertGreaterThan(0, $customerWaitingRoutes);
    }
}
