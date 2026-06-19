<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Http\Resources\ReservationOrderResource;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReservationOrderResourceContractTest extends TestCase
{
    public function test_resource_exposes_workflow_metadata_for_reservation_scoped_settlement(): void
    {
        $order = new ReservationOrder;
        $order->order_id = 123;
        $order->reservation_id = 456;
        $order->order_type = 'OnSpot';
        $order->status = 'Active';
        $order->subtotal_amount = '100';
        $order->discount_amount = '5';
        $order->total_due_amount = '95';
        $order->paid_amount = '20';
        $order->deposit_applied_amount = '0';
        $order->deposit_net_amount = '0';
        $order->final_paid_amount = '20';
        $order->outstanding_amount = '75';
        $order->currency = 'VND';

        $payload = (new ReservationOrderResource($order))->toArray(Request::create('/'));

        self::assertSame('reservation', $payload['workflow']['settlement_scope']);
        self::assertSame('bill_snapshot', $payload['workflow']['canonical_bill_snapshot_action']);
        self::assertSame('close', $payload['workflow']['legacy_bill_snapshot_action']);
        self::assertSame('Partial', $payload['payment_status']);
    }
}
