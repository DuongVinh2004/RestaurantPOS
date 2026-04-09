<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class ReleaseArtifactNormalizerService
{
    /**
     * @return array{
     *   ok: bool,
     *   changed: bool,
     *   issues: list<string>,
     *   artifacts: array<string, array{
     *     path: string,
     *     exists: bool,
     *     changed: bool,
     *     bytes_before?: int,
     *     bytes_after?: int,
     *     normalizations: list<string>
     *   }>,
     *   meta: array{generated_at_utc: string}
     * }
     */
    public function normalize(): array
    {
        $issues = [];
        $artifacts = [];
        $anyChanged = false;

        foreach ((array) config('booking_release.artifacts', []) as $key => $definition) {
            $relativePath = trim((string) ($definition['path'] ?? ''));
            if ($relativePath === '') {
                continue;
            }

            $absolutePath = base_path($relativePath);
            $exists = File::exists($absolutePath);
            $artifact = [
                'path' => $relativePath,
                'exists' => $exists,
                'changed' => false,
                'normalizations' => [],
            ];

            if (! $exists) {
                $artifacts[$key] = $artifact;
                continue;
            }

            $before = File::get($absolutePath);
            $after = $before;

            $after = $this->stripDefiners($after, $artifact['normalizations']);
            $after = $this->promoteGuardColumnsToTableDefinitions($after, $artifact['normalizations']);

            $artifact['bytes_before'] = strlen($before);
            $artifact['bytes_after'] = strlen($after);

            if ($after !== $before) {
                File::put($absolutePath, $after);
                $artifact['changed'] = true;
                $anyChanged = true;
            }

            $artifacts[$key] = $artifact;
        }

        return [
            'ok' => $issues === [],
            'changed' => $anyChanged,
            'issues' => $issues,
            'artifacts' => $artifacts,
            'meta' => [
                'generated_at_utc' => now('UTC')->toIso8601String(),
            ],
        ];
    }

    /**
     * @param list<string> $normalizations
     */
    private function stripDefiners(string $contents, array &$normalizations): string
    {
        $updated = preg_replace('/\/\*!\d+\s+DEFINER=`[^`]+`@`[^`]+`\*\/\s*/', '', $contents) ?? $contents;
        if ($updated !== $contents) {
            $normalizations[] = 'stripped_definer_comments';
            $contents = $updated;
        }

        $updated = preg_replace('/CREATE\s+DEFINER=`[^`]+`@`[^`]+`\s+/i', 'CREATE ', $contents) ?? $contents;
        if ($updated !== $contents) {
            $normalizations[] = 'stripped_create_definers';
            $contents = $updated;
        }

        return $contents;
    }

    /**
     * @param list<string> $normalizations
     */
    private function promoteGuardColumnsToTableDefinitions(string $contents, array &$normalizations): string
    {
        $updated = $this->promoteAgentAssignmentsGuard($contents);
        if ($updated !== $contents) {
            $normalizations[] = 'promoted_agent_assignment_guard_columns';
            $contents = $updated;
        }

        $updated = $this->promoteBankAccountGuard($contents);
        if ($updated !== $contents) {
            $normalizations[] = 'promoted_bank_account_guard_columns';
            $contents = $updated;
        }

        $updated = $this->promoteReservationActiveVoucherGuard($contents);
        if ($updated !== $contents) {
            $normalizations[] = 'promoted_reservation_active_voucher_guard_columns';
            $contents = $updated;
        }

        return $contents;
    }

    private function promoteAgentAssignmentsGuard(string $contents): string
    {
        if (! preg_match('/CREATE TABLE `agent_assignments` \((.*?)\) ENGINE=InnoDB/s', $contents, $matches)) {
            return $contents;
        }

        $block = $matches[0];
        if (str_contains($block, '`active_conversation_id`') && str_contains($block, '`uq_agent_assignments__active_conversation_id`')) {
            return $contents;
        }

        $updatedBlock = str_replace(
            "  `is_active` tinyint unsigned NOT NULL DEFAULT '1',\n",
            "  `is_active` tinyint unsigned NOT NULL DEFAULT '1',\n  `active_conversation_id` char(36) GENERATED ALWAYS AS ((case when (`is_active` = 1) then `conversation_id` else NULL end)) STORED,\n",
            $block
        );
        $updatedBlock = str_replace(
            "  PRIMARY KEY (`assignment_id`),\n",
            "  PRIMARY KEY (`assignment_id`),\n  UNIQUE KEY `uq_agent_assignments__active_conversation_id` (`active_conversation_id`),\n",
            $updatedBlock
        );

        return str_replace($block, $updatedBlock, $contents);
    }

    private function promoteBankAccountGuard(string $contents): string
    {
        if (! preg_match('/CREATE TABLE `bank_accounts` \((.*?)\) ENGINE=InnoDB/s', $contents, $matches)) {
            return $contents;
        }

        $block = $matches[0];
        if (str_contains($block, '`default_user_id`') && str_contains($block, '`uq_bank_accounts__default_user_id`')) {
            return $contents;
        }

        $updatedBlock = $block;

        if (! str_contains($updatedBlock, '`default_user_id`')) {
            $updatedBlock = str_replace(
                "  `is_default` tinyint unsigned NOT NULL DEFAULT '0',\n",
                "  `is_default` tinyint unsigned NOT NULL DEFAULT '0',\n  `default_user_id` int unsigned GENERATED ALWAYS AS ((case when (`is_default` = 1) then `user_id` else NULL end)) STORED,\n",
                $updatedBlock
            );
        }

        if (! str_contains($updatedBlock, '`uq_bank_accounts__default_user_id`')) {
            $count = 0;
            $updatedBlock = preg_replace(
                '/(  UNIQUE KEY `uq_bank_accounts__user_id__bank_account_number` \(`user_id`,`bank_account_number`\))(,?\n)/',
                "$1,\n  UNIQUE KEY `uq_bank_accounts__default_user_id` (`default_user_id`)\\2",
                $updatedBlock,
                1,
                $count
            ) ?? $updatedBlock;

            if ($count === 0) {
                $updatedBlock = str_replace(
                    "  PRIMARY KEY (`bank_account_id`),\n",
                    "  PRIMARY KEY (`bank_account_id`),\n  UNIQUE KEY `uq_bank_accounts__default_user_id` (`default_user_id`),\n",
                    $updatedBlock
                );
            }
        }

        return str_replace($block, $updatedBlock, $contents);
    }

    private function promoteReservationActiveVoucherGuard(string $contents): string
    {
        if (! preg_match('/CREATE TABLE `reservations` \((.*?)\) ENGINE=InnoDB/s', $contents, $matches)) {
            return $contents;
        }

        $block = $matches[0];
        if (str_contains($block, '`active_applied_user_voucher_id`') && str_contains($block, '`uq_reservations__active_applied_user_voucher_id`')) {
            return $contents;
        }

        $updatedBlock = $block;

        if (! str_contains($updatedBlock, '`active_applied_user_voucher_id`')) {
            $updatedBlock = str_replace(
                "  `applied_user_voucher_id` int unsigned DEFAULT NULL,\n",
                "  `applied_user_voucher_id` int unsigned DEFAULT NULL,\n  `active_applied_user_voucher_id` int unsigned GENERATED ALWAYS AS ((case when (`status` in (_utf8mb4'Confirmed',_utf8mb4'Reserved')) then `applied_user_voucher_id` else NULL end)) STORED,\n",
                $updatedBlock
            );
        }

        if (! str_contains($updatedBlock, '`uq_reservations__active_applied_user_voucher_id`')) {
            $count = 0;
            $updatedBlock = preg_replace(
                '/(  UNIQUE KEY `uq_reservations__reservation_code` \(`reservation_code`\))(,?\n)/',
                "$1,\n  UNIQUE KEY `uq_reservations__active_applied_user_voucher_id` (`active_applied_user_voucher_id`)\\2",
                $updatedBlock,
                1,
                $count
            ) ?? $updatedBlock;

            if ($count === 0) {
                $updatedBlock = str_replace(
                    "  PRIMARY KEY (`reservation_id`),\n",
                    "  PRIMARY KEY (`reservation_id`),\n  UNIQUE KEY `uq_reservations__active_applied_user_voucher_id` (`active_applied_user_voucher_id`),\n",
                    $updatedBlock
                );
            }
        }

        return str_replace($block, $updatedBlock, $contents);
    }
}
