<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\PrivacyCompliance\Domain\Models\AuditLog;

trait AssertsAuditTrail
{
    protected function assertAuditLogRecorded(string $action, string $entityType, int|string $entityId): AuditLog
    {
        $log = AuditLog::query()
            ->where('action', $action)
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $entityId)
            ->orderByDesc('audit_id')
            ->first();

        self::assertNotNull(
            $log,
            sprintf('Expected audit log [%s] for %s:%s to exist.', $action, $entityType, (string) $entityId)
        );

        return $log;
    }

    protected function assertAuditLogCount(int $expected, string $action, string $entityType, int|string $entityId): void
    {
        $count = AuditLog::query()
            ->where('action', $action)
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $entityId)
            ->count();

        self::assertSame(
            $expected,
            $count,
            sprintf('Unexpected audit log count for [%s] on %s:%s.', $action, $entityType, (string) $entityId)
        );
    }

    protected function assertAuditSubjectRecorded(AuditLog $log, string $subjectType, int|string $subjectId, ?string $role = null): void
    {
        $subject = $log->subjects()
            ->where('subject_type', $subjectType)
            ->where('subject_id', (string) $subjectId)
            ->when($role !== null, fn ($query) => $query->where('subject_role', $role))
            ->first();

        $message = sprintf(
            'Expected audit subject %s:%s%s on audit #%d.',
            $subjectType,
            (string) $subjectId,
            $role !== null ? ' ['.$role.']' : '',
            (int) $log->audit_id,
        );

        self::assertNotNull($subject, $message);
    }
}
