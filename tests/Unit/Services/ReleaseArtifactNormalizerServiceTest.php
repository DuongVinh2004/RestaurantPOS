<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ReleaseArtifactNormalizerService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleaseArtifactNormalizerServiceTest extends TestCase
{
    private string $root = 'storage/framework/testing/release_normalizer';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->root));
        parent::tearDown();
    }

    public function test_normalize_strips_definers_and_promotes_guard_columns(): void
    {
        $schemaPath = base_path($this->root . '/schema.sql');
        File::ensureDirectoryExists(dirname($schemaPath));
        File::put($schemaPath, <<<'SQL'
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cleanup_expired_holds`()
BEGIN
END;

CREATE TABLE `agent_assignments` (
  `assignment_id` int unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` char(36) NOT NULL,
  `agent_user_id` int unsigned NOT NULL,
  `assigned_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `released_at` datetime(6) DEFAULT NULL,
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`assignment_id`),
  KEY `fk_agent_assignments__agent_user_id__users` (`agent_user_id`),
  KEY `idx_agent_assignments__conversation_id__is_active` (`conversation_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `bank_accounts` (
  `bank_account_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `bank_account_number` varchar(100) NOT NULL,
  `bank_name` varchar(200) DEFAULT NULL,
  `account_holder_name` varchar(200) DEFAULT NULL,
  `is_default` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`bank_account_id`),
  UNIQUE KEY `uq_bank_accounts__user_id__bank_account_number` (`user_id`,`bank_account_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `reservations` (
  `reservation_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `reservation_code` varchar(50) NOT NULL,
  `status` enum('Confirmed','Reserved','Cancelled') NOT NULL DEFAULT 'Confirmed',
  `applied_user_voucher_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`reservation_id`),
  UNIQUE KEY `uq_reservations__reservation_code` (`reservation_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);

        config()->set('booking_release.artifacts', [
            'schema_dump' => [
                'path' => $this->root . '/schema.sql',
                'optional' => false,
                'required_fragments' => [],
            ],
        ]);

        $report = app(ReleaseArtifactNormalizerService::class)->normalize();
        $normalized = File::get($schemaPath);

        $this->assertTrue($report['ok']);
        $this->assertTrue($report['changed']);
        $this->assertStringNotContainsString('DEFINER=', $normalized);
        $this->assertStringContainsString('`active_conversation_id`', $normalized);
        $this->assertStringContainsString('`uq_agent_assignments__active_conversation_id`', $normalized);
        $this->assertStringContainsString('`default_user_id`', $normalized);
        $this->assertStringContainsString('`uq_bank_accounts__default_user_id`', $normalized);
        $this->assertStringContainsString('`active_applied_user_voucher_id`', $normalized);
        $this->assertStringContainsString('`uq_reservations__active_applied_user_voucher_id`', $normalized);
        $this->assertContains('promoted_reservation_active_voucher_guard_columns', $report['artifacts']['schema_dump']['normalizations']);
    }
}
