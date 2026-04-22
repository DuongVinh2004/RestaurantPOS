<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Application\Services;

use App\Enums\StaffConversationWorkflowState;
use App\Modules\Conversations\Domain\Models\AgentAssignment;
use App\Modules\Conversations\Domain\Models\Conversation;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Notifications\Application\Services\NotificationChannelManager;
use App\Modules\Notifications\Application\Services\NotificationPreferenceService;
use Illuminate\Support\Carbon;

class StaffConversationOutboundReplySupportService
{
    public function __construct(
        private readonly NotificationChannelManager $channelManager,
        private readonly NotificationPreferenceService $notificationPreferenceService,
    ) {}

    /**
     * @return array{
     *   supported: bool,
     *   channel: ?string,
     *   delivery_mode: ?string,
     *   recipient: ?string,
     *   recipient_masked: ?string,
     *   recipient_user_id: ?int,
     *   reason_code: ?string,
     *   reason: ?string,
     *   quiet_until_utc: ?string
     * }
     */
    public function describe(
        Conversation $conversation,
        ?int $staffActorUserId = null,
        ?AgentAssignment $activeAssignment = null,
    ): array {
        $conversation->loadMissing([
            'user',
            'branch',
            'linkedReservation.user',
            'linkedWaitingList.user',
        ]);

        $activeAssignment ??= $conversation->relationLoaded('activeAssignment')
            ? $conversation->activeAssignment
            : $conversation->activeAssignment()->with(['agent.role'])->first();

        $workflowState = $conversation->workflowState();
        $status = $conversation->status?->value ?? (string) $conversation->status;
        if (
            in_array($status, ['Spam'], true)
            || in_array($workflowState, [StaffConversationWorkflowState::Resolved, StaffConversationWorkflowState::Closed], true)
        ) {
            return $this->unsupported(
                'conversation_not_open',
                'Outbound reply is only available while the conversation remains active in the inbox workflow.',
            );
        }

        $branchValidation = $this->validateBranchConsistency($conversation);
        if ($branchValidation !== null) {
            return $branchValidation;
        }

        $userValidation = $this->validateUserConsistency($conversation);
        if ($userValidation !== null) {
            return $userValidation;
        }

        if (
            $activeAssignment instanceof AgentAssignment
            && $staffActorUserId !== null
            && $staffActorUserId > 0
            && (int) $activeAssignment->agent_user_id !== $staffActorUserId
        ) {
            return $this->unsupported(
                'assigned_to_other_staff',
                'Conversation is actively assigned to another staff actor. Use take-over before sending an outbound reply.',
            );
        }

        $recipientUser = $this->resolveRecipientUser($conversation);
        $recipientEmail = $this->normalizeNullableString($recipientUser?->email);
        if ($recipientEmail === null) {
            return $this->unsupported(
                'missing_email_recipient',
                'Outbound reply requires a linked customer email address.',
                channel: 'Email',
            );
        }

        $emailChannel = $this->channelManager->describe('Email');
        $deliveryMode = $this->normalizeNullableString($emailChannel['delivery_mode'] ?? null);
        $supportsLiveDelivery = ($emailChannel['supports_live_delivery'] ?? false) === true;
        if (($emailChannel['enabled'] ?? false) !== true || ! $supportsLiveDelivery) {
            return $this->unsupported(
                'email_delivery_unavailable',
                'Outbound reply is unavailable because the email channel is not configured for real delivery.',
                channel: 'Email',
                deliveryMode: $deliveryMode,
            );
        }

        $preference = $this->notificationPreferenceService->evaluate(
            $recipientUser?->user_id !== null ? (int) $recipientUser->user_id : null,
            'Email',
            Carbon::now('UTC'),
            $this->normalizeNullableString($conversation->branch?->timezone ?? null),
        );

        if (($preference['enabled'] ?? true) !== true) {
            return $this->unsupported(
                'recipient_prefers_no_email',
                'Outbound reply is disabled by the recipient notification preferences.',
                channel: 'Email',
                deliveryMode: $deliveryMode,
            );
        }

        $quietUntil = $preference['quiet_until'] ?? null;

        return [
            'supported' => true,
            'channel' => 'Email',
            'delivery_mode' => $deliveryMode,
            'recipient' => $recipientEmail,
            'recipient_masked' => $this->maskRecipient($recipientEmail),
            'recipient_user_id' => $recipientUser?->user_id !== null ? (int) $recipientUser->user_id : null,
            'reason_code' => null,
            'reason' => null,
            'quiet_until_utc' => $quietUntil instanceof Carbon ? $quietUntil->copy()->utc()->toIso8601String() : null,
        ];
    }

    private function resolveRecipientUser(Conversation $conversation): ?User
    {
        if ($conversation->user instanceof User) {
            return $conversation->user;
        }

        if ($conversation->linkedReservation?->user instanceof User) {
            return $conversation->linkedReservation->user;
        }

        if ($conversation->linkedWaitingList?->user instanceof User) {
            return $conversation->linkedWaitingList->user;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validateBranchConsistency(Conversation $conversation): ?array
    {
        $branchIds = array_values(array_unique(array_filter([
            $conversation->branch_id,
            $conversation->linkedReservation?->branch_id,
            $conversation->linkedWaitingList?->branch_id,
        ], static fn (mixed $value): bool => $value !== null && (int) $value > 0)));

        if (count($branchIds) <= 1) {
            return null;
        }

        return $this->unsupported(
            'branch_mismatch',
            'Conversation links are not branch-consistent enough to support an outbound reply.',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validateUserConsistency(Conversation $conversation): ?array
    {
        $userIds = array_values(array_unique(array_filter([
            $conversation->user_id,
            $conversation->linkedReservation?->user_id,
            $conversation->linkedWaitingList?->user_id,
        ], static fn (mixed $value): bool => $value !== null && (int) $value > 0)));

        if (count($userIds) <= 1) {
            return null;
        }

        return $this->unsupported(
            'customer_mismatch',
            'Conversation links resolve to different customers, so outbound reply is blocked until the record is corrected.',
        );
    }

    /**
     * @return array{
     *   supported: false,
     *   channel: ?string,
     *   delivery_mode: ?string,
     *   recipient: null,
     *   recipient_masked: null,
     *   recipient_user_id: null,
     *   reason_code: string,
     *   reason: string,
     *   quiet_until_utc: null
     * }
     */
    private function unsupported(
        string $reasonCode,
        string $reason,
        ?string $channel = null,
        ?string $deliveryMode = null,
    ): array {
        return [
            'supported' => false,
            'channel' => $channel,
            'delivery_mode' => $deliveryMode,
            'recipient' => null,
            'recipient_masked' => null,
            'recipient_user_id' => null,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'quiet_until_utc' => null,
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function maskRecipient(string $recipient): string
    {
        $at = strpos($recipient, '@');
        if ($at === false) {
            if (strlen($recipient) <= 4) {
                return str_repeat('*', strlen($recipient));
            }

            return substr($recipient, 0, 2).str_repeat('*', max(1, strlen($recipient) - 4)).substr($recipient, -2);
        }

        $local = substr($recipient, 0, $at);
        $domain = substr($recipient, $at + 1);
        $localLength = strlen($local);

        $localMasked = match (true) {
            $localLength <= 0 => '',
            $localLength === 1 => '*',
            $localLength === 2 => $local[0].'*',
            default => $local[0].str_repeat('*', $localLength - 2).$local[$localLength - 1],
        };

        return $localMasked.'@'.$domain;
    }
}
