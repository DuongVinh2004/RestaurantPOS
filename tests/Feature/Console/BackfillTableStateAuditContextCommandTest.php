<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BackfillTableStateAuditContextCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reservation_orders');

        Schema::create('reservation_orders', function (Blueprint $table): void {
            $table->unsignedInteger('order_id')->primary();
            $table->unsignedInteger('reservation_id');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->unsignedInteger('audit_id')->primary();
            $table->string('entity_type', 50);
            $table->string('entity_id', 64);
            $table->string('action', 50);
            $table->text('after_json')->nullable();
            $table->text('meta_json')->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    public function test_it_backfills_missing_context_for_recent_settlement_finalize_rows(): void
    {
        DB::table('reservation_orders')->insert([
            'order_id' => 501,
            'reservation_id' => 91,
        ]);

        DB::table('audit_logs')->insert([
            'audit_id' => 1,
            'entity_type' => 'restaurant_table',
            'entity_id' => '12',
            'action' => 'table_state_released',
            'after_json' => json_encode(['status' => 'Available'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta_json' => json_encode([
                'request' => [
                    'path' => 'api/v1/staff/orders/501/settlement/finalize',
                    'method' => 'POST',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => Carbon::now('UTC'),
        ]);

        $exitCode = Artisan::call('booking:backfill-table-state-audit-context', ['--limit' => 10]);

        $this->assertSame(0, $exitCode);

        $afterPayload = json_decode((string) DB::table('audit_logs')->where('audit_id', 1)->value('after_json'), true, 512, JSON_THROW_ON_ERROR);
        $metaPayload = json_decode((string) DB::table('audit_logs')->where('audit_id', 1)->value('meta_json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            'order_id' => 501,
            'reservation_id' => 91,
            'source' => 'staff_settlement_finalize',
            'reason' => 'settlement_finalize',
        ], $afterPayload['context'] ?? null);
        $this->assertSame(91, $metaPayload['context']['reservation_id'] ?? null);
    }

    public function test_dry_run_reports_matches_without_updating_rows(): void
    {
        DB::table('reservation_orders')->insert([
            'order_id' => 777,
            'reservation_id' => 88,
        ]);

        DB::table('audit_logs')->insert([
            'audit_id' => 2,
            'entity_type' => 'restaurant_table',
            'entity_id' => '5',
            'action' => 'table_state_released',
            'after_json' => json_encode(['status' => 'Available'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta_json' => json_encode([
                'request' => [
                    'path' => 'api/v1/staff/orders/777/settlement/finalize',
                    'method' => 'POST',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => Carbon::now('UTC'),
        ]);

        $exitCode = Artisan::call('booking:backfill-table-state-audit-context', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);

        $afterPayload = json_decode((string) DB::table('audit_logs')->where('audit_id', 2)->value('after_json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('context', $afterPayload);
    }
}
