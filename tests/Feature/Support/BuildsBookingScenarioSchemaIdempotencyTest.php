<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class BuildsBookingScenarioSchemaIdempotencyTest extends TestCase
{
    use BuildsBookingScenario;

    public function test_require_booking_schema_can_run_twice_without_readding_notification_outbox_indexes(): void
    {
        $this->requireBookingSchema();
        $this->requireBookingSchema();

        $this->assertTrue(Schema::hasIndex('notification_outbox', 'idx_notification_outbox__dedupe_key__created_at'));
        $this->assertTrue(Schema::hasIndex('notification_outbox', 'idx_notification_outbox__recipient_user_id__status__created_at'));
    }
}
