<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\FloorOperations\Domain\Audit\TableStateAuditLogger;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TableStateAuditLoggerTest extends TestCase
{
    public function test_it_builds_transition_records_only_for_changed_statuses(): void
    {
        $records = TableStateAuditLogger::buildTransitionRecords(
            beforeRows: [[
                'table_id' => 5,
                'status' => 'Available',
                'row_version' => 7,
                'updated_at' => '2026-03-15 10:00:00.000000',
            ]],
            afterRows: [[
                'table_id' => 5,
                'status' => 'Occupied',
                'row_version' => 8,
                'updated_at' => '2026-03-15 10:05:00.000000',
            ]],
            action: 'table_state_occupied',
            actorUserId: 42,
            context: [
                'reservation_id' => 99,
                'source' => 'staff_check_in',
            ],
            occurredAt: Carbon::create(2026, 3, 15, 10, 5, 0, 'UTC'),
        );

        $this->assertCount(1, $records);
        $this->assertSame('restaurant_table', $records[0]['entity_type']);
        $this->assertSame('5', $records[0]['entity_id']);
        $this->assertSame('table_state_occupied', $records[0]['action']);
        $this->assertSame('Available', $records[0]['before_json']['status'] ?? null);
        $this->assertSame('Occupied', $records[0]['after_json']['status'] ?? null);
        $this->assertSame(99, $records[0]['after_json']['context']['reservation_id'] ?? null);
    }

    public function test_it_skips_records_when_status_does_not_change(): void
    {
        $records = TableStateAuditLogger::buildTransitionRecords(
            beforeRows: [[
                'table_id' => 8,
                'status' => 'Available',
                'row_version' => 3,
                'updated_at' => '2026-03-15 10:00:00.000000',
            ]],
            afterRows: [[
                'table_id' => 8,
                'status' => 'Available',
                'row_version' => 4,
                'updated_at' => '2026-03-15 10:01:00.000000',
            ]],
            action: 'table_state_released',
        );

        $this->assertSame([], $records);
    }
}

