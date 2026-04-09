<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BackfillConfirmedHoldLinkageCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('table_hold_details');
        Schema::dropIfExists('table_holds');
        Schema::dropIfExists('reservation_tables');
        Schema::dropIfExists('reservations');

        Schema::create('reservations', function (Blueprint $table): void {
            $table->increments('reservation_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
        });

        Schema::create('reservation_tables', function (Blueprint $table): void {
            $table->increments('reservation_table_id');
            $table->unsignedInteger('reservation_id');
            $table->unsignedInteger('table_id');
        });

        Schema::create('table_holds', function (Blueprint $table): void {
            $table->string('hold_id')->primary();
            $table->string('session_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('confirmed_reservation_id')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('hold_status');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('table_hold_details', function (Blueprint $table): void {
            $table->increments('hold_detail_id');
            $table->string('hold_id');
            $table->unsignedInteger('table_id');
        });
    }

    public function test_it_backfills_exact_hold_to_reservation_linkage(): void
    {
        $start = Carbon::parse('2026-03-16 18:00:00', 'UTC');
        $end = Carbon::parse('2026-03-16 20:00:00', 'UTC');

        DB::table('reservations')->insert([
            'reservation_id' => 101,
            'user_id' => 77,
            'start_time' => $start,
            'end_time' => $end,
        ]);
        DB::table('reservation_tables')->insert([
            ['reservation_id' => 101, 'table_id' => 3],
            ['reservation_id' => 101, 'table_id' => 4],
        ]);
        DB::table('table_holds')->insert([
            'hold_id' => 'hold-link-101',
            'session_id' => 'sess-101',
            'user_id' => 77,
            'confirmed_reservation_id' => null,
            'start_time' => $start,
            'end_time' => $end,
            'hold_status' => 'Confirmed',
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ]);
        DB::table('table_hold_details')->insert([
            ['hold_id' => 'hold-link-101', 'table_id' => 3],
            ['hold_id' => 'hold-link-101', 'table_id' => 4],
        ]);

        $exitCode = Artisan::call('booking:backfill-confirmed-hold-linkage', ['--limit' => 10]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(101, DB::table('table_holds')->where('hold_id', 'hold-link-101')->value('confirmed_reservation_id'));
    }

    public function test_dry_run_reports_matches_without_updating_rows(): void
    {
        $start = Carbon::parse('2026-03-16 18:00:00', 'UTC');
        $end = Carbon::parse('2026-03-16 20:00:00', 'UTC');

        DB::table('reservations')->insert([
            'reservation_id' => 202,
            'user_id' => 88,
            'start_time' => $start,
            'end_time' => $end,
        ]);
        DB::table('reservation_tables')->insert([
            'reservation_id' => 202,
            'table_id' => 9,
        ]);
        DB::table('table_holds')->insert([
            'hold_id' => 'hold-link-dry-run',
            'session_id' => 'sess-dry-run',
            'user_id' => 88,
            'confirmed_reservation_id' => null,
            'start_time' => $start,
            'end_time' => $end,
            'hold_status' => 'Holding',
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ]);
        DB::table('table_hold_details')->insert([
            'hold_id' => 'hold-link-dry-run',
            'table_id' => 9,
        ]);

        $exitCode = Artisan::call('booking:backfill-confirmed-hold-linkage', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertNull(DB::table('table_holds')->where('hold_id', 'hold-link-dry-run')->value('confirmed_reservation_id'));
    }
}
