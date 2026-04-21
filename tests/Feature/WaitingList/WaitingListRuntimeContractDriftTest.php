<?php

declare(strict_types=1);

namespace Tests\Feature\WaitingList;

use App\Modules\Waitlist\Http\Controllers\Customer\WaitlistController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WaitingListRuntimeContractDriftTest extends TestCase
{
    public function test_customer_waiting_list_routes_stay_on_canonical_owner_only_runtime_contract(): void
    {
        $legacyClass = 'App\\Services\\WaitingList\\CustomerWaitingListSelfService';
        $customerController = WaitlistController::class;
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
        $this->assertFalse(class_exists($legacyClass, false), 'Legacy guest self-service residue should not remain autoloadable.');
        $this->assertFileDoesNotExist(
            base_path('app/Services/WaitingList/CustomerWaitingListSelfService.php'),
            'Legacy guest self-service residue should be removed once the owner-only runtime contract is canonical.'
        );
    }
}
