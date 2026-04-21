<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ApiContract;

use App\Modules\Payments\Http\Requests\Customer\StartReservationDepositPaymentRequest;
use App\Modules\BranchScheduling\Http\Requests\Admin\ListBranchesRequest;
use App\Modules\Reservations\Http\Requests\Customer\CreateReservationRequest;
use App\Platform\ApiContract\Services\FormRequestSchemaFactory;
use Tests\TestCase;

final class FormRequestSchemaFactoryTest extends TestCase
{
    public function test_it_describes_nested_store_reservation_payloads_without_resolving_validation(): void
    {
        $description = app(FormRequestSchemaFactory::class)->describe(CreateReservationRequest::class);

        $this->assertSame('CreateReservationRequest', $description['schema_name']);
        $this->assertSame(['end_time', 'guest_count', 'start_time'], $description['schema']['required']);
        $this->assertSame('array', $description['schema']['properties']['pre_order_items']['type']);
        $this->assertSame(
            ['item_id', 'quantity'],
            array_keys($description['schema']['properties']['pre_order_items']['items']['properties'])
        );
        $this->assertSame('sess-demo-001', $description['request_example']['session_id']);
    }

    public function test_it_extracts_query_parameters_for_branch_listing_requests(): void
    {
        $description = app(FormRequestSchemaFactory::class)->describe(ListBranchesRequest::class);
        $parameters = collect($description['query_parameters'])->keyBy('name');

        $this->assertSame(['is_active', 'q'], $parameters->keys()->all());
        $this->assertSame('boolean', $parameters['is_active']['schema']['type']);
        $this->assertSame('string', $parameters['q']['schema']['type']);
        $this->assertSame(true, $description['request_example']['is_active']);
        $this->assertSame('example', $description['request_example']['q']);
    }

    public function test_it_handles_required_row_version_requests_without_throwing_validation_exceptions(): void
    {
        $description = app(FormRequestSchemaFactory::class)->describe(StartReservationDepositPaymentRequest::class);

        $this->assertSame(
            ['row_version'],
            $description['schema']['required']
        );
        $this->assertSame(
            1,
            $description['request_example']['row_version']
        );
        $this->assertSame(
            'number',
            $description['schema']['properties']['amount']['type']
        );
    }
}
