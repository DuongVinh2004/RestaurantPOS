<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\RequireStaffCapability;
use Illuminate\Http\Request;
use Tests\TestCase;

final class RequireStaffCapabilityTest extends TestCase
{
    public function test_it_allows_request_when_role_has_required_capability(): void
    {
        config()->set('staff_capabilities.role_capabilities', [
            'Staff' => ['reservation.manage'],
        ]);

        $request = Request::create('/__testing__/staff-capability', 'POST');
        $request->attributes->set('staff_actor_role_name', 'Staff');

        $response = (new RequireStaffCapability())->handle($request, function () {
            return response()->json(['ok' => true], 200);
        }, 'reservation.manage');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], $response->getData(true));
    }

    public function test_it_allows_request_when_role_has_wildcard_capability(): void
    {
        config()->set('staff_capabilities.role_capabilities', [
            'Admin' => ['*'],
        ]);

        $request = Request::create('/__testing__/staff-capability', 'POST');
        $request->attributes->set('staff_actor_role_name', 'Admin');

        $response = (new RequireStaffCapability())->handle($request, function () {
            return response()->json(['ok' => true], 200);
        }, 'payment.refund');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], $response->getData(true));
    }


    public function test_it_prefers_role_id_capabilities_when_present(): void
    {
        config()->set('staff_capabilities.role_id_capabilities', [
            7 => ['payment.refund'],
        ]);
        config()->set('staff_capabilities.role_capabilities', [
            'Staff' => ['table.board.view'],
        ]);

        $request = Request::create('/__testing__/staff-capability', 'POST');
        $request->attributes->set('staff_actor_role_id', 7);
        $request->attributes->set('staff_actor_role_name', 'Staff');

        $response = (new RequireStaffCapability())->handle($request, function () {
            return response()->json(['ok' => true], 200);
        }, 'payment.refund');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], $response->getData(true));
    }

    public function test_it_rejects_request_when_role_lacks_required_capability(): void
    {
        config()->set('staff_capabilities.role_capabilities', [
            'Staff' => ['table.board.view'],
        ]);

        $request = Request::create('/__testing__/staff-capability', 'POST');
        $request->attributes->set('staff_actor_role_name', 'Staff');

        $response = (new RequireStaffCapability())->handle($request, function () {
            $this->fail('Middleware should not allow the downstream handler when capability is missing.');
        }, 'payment.refund');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('forbidden', $response->getData(true)['error_code'] ?? null);
        $this->assertSame('payment.refund', $response->getData(true)['required_capability'] ?? null);
    }
}
