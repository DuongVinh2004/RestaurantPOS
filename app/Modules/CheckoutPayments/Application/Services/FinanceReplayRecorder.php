<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Application\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceReplayRecorder
{
    public const SCOPE_STAFF_CHECKOUT = 'staff.checkout';
    public const SCOPE_STAFF_PAY_ORDER = 'staff.pay_order';

    public function assertReplayMatches(
        string $scope,
        string $aggregateType,
        int $aggregateId,
        string $idempotencyKey,
        ?string $requestFingerprint,
        string $message
    ): void {
        $record = $this->find($scope, $aggregateType, $aggregateId, $idempotencyKey);
        if ($record === null) {
            return;
        }

        $recordedFingerprint = trim((string) ($record->request_fingerprint ?? ''));
        $incomingFingerprint = trim((string) ($requestFingerprint ?? ''));
        if ($recordedFingerprint !== '' && $incomingFingerprint !== '' && ! hash_equals($recordedFingerprint, $incomingFingerprint)) {
            throw ValidationException::withMessages([
                'idempotency_key' => [$message],
            ]);
        }
    }

    public function find(string $scope, string $aggregateType, int $aggregateId, string $idempotencyKey): ?object
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return null;
        }

        $record = DB::table('finance_replay_records')
            ->where('scope', $scope)
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return is_object($record) ? $record : null;
    }

    /**
     * @param  array<string,mixed>  $context
     */
    public function recordSuccess(
        string $scope,
        string $aggregateType,
        int $aggregateId,
        string $idempotencyKey,
        ?string $requestFingerprint,
        string $resultType,
        int $resultId,
        array $context = []
    ): void {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return;
        }

        $now = now('UTC');
        $payload = [
            'scope' => $scope,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $requestFingerprint !== null && trim($requestFingerprint) !== '' ? trim($requestFingerprint) : null,
            'result_type' => $resultType,
            'result_id' => $resultId,
            'context_json' => $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            DB::table('finance_replay_records')->insert($payload);

            return;
        } catch (QueryException $exception) {
            if (! $this->isDuplicateReplayRecordException($exception)) {
                throw $exception;
            }
        }

        $this->assertReplayMatches(
            scope: $scope,
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            idempotencyKey: $idempotencyKey,
            requestFingerprint: $requestFingerprint,
            message: 'This idempotency key is already bound to a different finance mutation request payload.'
        );
    }

    private function isDuplicateReplayRecordException(QueryException $exception): bool
    {
        $message = strtolower((string) ($exception->errorInfo[2] ?? $exception->getMessage()));

        return str_contains($message, 'uq_finance_replay_records__scope_aggregate_key')
            || str_contains($message, 'finance_replay_records.scope')
            || str_contains($message, 'unique constraint failed: finance_replay_records.scope')
            || str_contains($message, 'duplicate');
    }
}
