<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WaitingListReleaseArtifactContractTest extends TestCase
{
    public function test_waiting_list_release_artifacts_lock_customer_response_contract_fragments(): void
    {
        $schemaDumpPath = base_path((string) config('booking_release.artifacts.schema_dump.path', 'database/schema/mysql-schema.sql'));
        $fullDumpPath = base_path((string) config('booking_release.artifacts.full_dump.path', 'db_all.sql'));

        $this->assertTrue(File::exists($schemaDumpPath), 'Schema dump artifact is missing.');
        $this->assertTrue(File::exists($fullDumpPath), 'Full dump artifact is missing.');

        $schemaDump = File::get($schemaDumpPath);
        $fullDump = File::get($fullDumpPath);

        $requiredFragments = [
            '`customer_session_id` varchar(100)',
            'idx_waiting_list__customer_session_id__requested_at',
            '`customer_response_status` varchar(30)',
            'chk_waiting_list__status_notified_requires_window',
            'chk_waiting_list__status_seated_requires_timestamp',
            'chk_waiting_list__status_cancelled_requires_timestamp',
            'chk_waiting_list__customer_response_requires_timestamp',
            'chk_waiting_list__customer_arrival_requires_accept',
        ];

        foreach ($requiredFragments as $fragment) {
            $this->assertStringContainsString($fragment, $schemaDump);
            $this->assertStringContainsString($fragment, $fullDump);
        }

        $waitingListUserForeignKeyFragment = 'ALTER TABLE `waiting_list` ADD CONSTRAINT `fk_waiting_list__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;';
        $this->assertStringContainsString($waitingListUserForeignKeyFragment, $schemaDump);
        $this->assertStringContainsString($waitingListUserForeignKeyFragment, $fullDump);

        $this->assertContains(
            '2026_04_01_000041_waiting_list_db_contract_reconciliation.sql',
            (array) config('booking_release.required_sql_patches', [])
        );
        $this->assertContains(
            '2026_04_04_000043_waiting_list_user_fk_generated_column_hotfix.sql',
            (array) config('booking_release.required_sql_patches', [])
        );
    }
}
