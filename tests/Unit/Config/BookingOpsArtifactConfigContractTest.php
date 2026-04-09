<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class BookingOpsArtifactConfigContractTest extends TestCase
{
    public function test_booking_ops_artifact_roots_follow_release_reports_layout(): void
    {
        $this->assertSame('storage/app/booking_release/doctor', (string) config('booking_ops_artifacts.doctor.artifact_root'));
        $this->assertSame('storage/app/booking_release/deploy_checks', (string) config('booking_ops_artifacts.deploy_check.artifact_root'));
        $this->assertSame('storage/app/booking_release/release_manifest', (string) config('booking_ops_artifacts.release_manifest.artifact_root'));
    }
}
