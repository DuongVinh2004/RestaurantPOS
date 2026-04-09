<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class BookingReleaseRequiredSqlPatchesTest extends TestCase
{
    public function test_required_sql_patch_inventory_tracks_all_numbered_patch_artifacts(): void
    {
        $expected = glob(database_path('patches/[0-9][0-9][0-9][0-9]_[0-9][0-9]_[0-9][0-9]_[0-9][0-9][0-9][0-9][0-9][0-9]_*.sql')) ?: [];
        $expected = array_map('basename', $expected);
        sort($expected);

        $actual = array_values((array) config('booking_release.required_sql_patches', []));
        sort($actual);

        self::assertSame($expected, $actual);
    }
}
