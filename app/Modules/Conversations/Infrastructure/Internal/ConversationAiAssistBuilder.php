<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Infrastructure\Internal;

use App\Modules\Conversations\Domain\Models\Conversation;
use App\Modules\Conversations\Domain\Models\ConversationAnalysis;
use App\Modules\Conversations\Domain\Models\ConversationMessage;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConversationAiAssistBuilder
{
    private const FEATURE_KEY = 'staff.conversation_ai_assist';

    private const PROVIDER = 'local_heuristic';

    private const MODEL = 'conversation-summary-v1';

    private const COST_TIER = 'zero';

    private const LATENCY_BUDGET_MS = 150;

    public function __construct(
        private readonly FeatureFlagService $featureFlags,
    ) {}

    /**
     * @param  Collection<int, ConversationMessage>  $messages
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return array<string, mixed>
     */
    public function buildForConversationDetail(Conversation $conversation, Collection $messages, Collection $analyses): array
    {
        $sourceCounts = $this->sourceCounts($messages, $analyses);
        $featureResolution = $this->featureFlags->resolve(
            self::FEATURE_KEY,
            $conversation->branch_id !== null ? (int) $conversation->branch_id : null,
        );

        if (! (bool) ($featureResolution['enabled'] ?? false)) {
            return $this->fallbackEnvelope(
                status: 'disabled',
                fallbackReasonCode: 'feature_disabled',
                fallbackReason: (string) ($featureResolution['message'] ?? 'Conversation AI assist is disabled for this rollout.'),
                sourceCounts: $sourceCounts,
            );
        }

        $visibleMessages = $messages
            ->filter(static fn (mixed $message): bool => $message instanceof ConversationMessage)
            ->filter(static fn (ConversationMessage $message): bool => ! (bool) $message->is_internal_note)
            ->filter(static fn (ConversationMessage $message): bool => trim((string) $message->message_text) !== '')
            ->values();

        if ($visibleMessages->isEmpty()) {
            return $this->fallbackEnvelope(
                status: 'unavailable',
                fallbackReasonCode: 'no_visible_messages',
                fallbackReason: 'Conversation does not yet have enough visible thread context for AI assist.',
                sourceCounts: $sourceCounts,
            );
        }

        /** @var Collection<int, ConversationMessage> $customerMessages */
        $customerMessages = $visibleMessages
            ->filter(static fn (ConversationMessage $message): bool => Str::lower((string) $message->sender) === 'user')
            ->values();

        /** @var ConversationMessage|null $signalMessage */
        $signalMessage = $customerMessages->last() ?? $visibleMessages->last();

        if (! $signalMessage instanceof ConversationMessage) {
            return $this->fallbackEnvelope(
                status: 'unavailable',
                fallbackReasonCode: 'insufficient_context',
                fallbackReason: 'Conversation does not yet have enough canonical message context for AI assist.',
                sourceCounts: $sourceCounts,
            );
        }

        /** @var ConversationAnalysis|null $latestAnalysis */
        $latestAnalysis = $analyses->first();
        $summary = $this->buildSummary($conversation, $signalMessage, $latestAnalysis);

        if ($summary === null) {
            return $this->fallbackEnvelope(
                status: 'unavailable',
                fallbackReasonCode: 'insufficient_context',
                fallbackReason: 'Conversation does not yet have enough stable context for AI assist.',
                sourceCounts: $sourceCounts,
            );
        }

        $actions = $this->buildSuggestedActions($conversation, $signalMessage);
        $riskFlags = $this->buildRiskFlags($conversation, $signalMessage, $latestAnalysis);

        return [
            'status' => 'ready',
            'feature_key' => self::FEATURE_KEY,
            'provider' => self::PROVIDER,
            'model' => self::MODEL,
            'priority' => $this->determinePriority($conversation, $signalMessage, $latestAnalysis),
            'summary' => $summary,
            'suggested_actions' => array_values($actions),
            'risk_flags' => array_values($riskFlags),
            'fallback_reason_code' => null,
            'fallback_reason' => null,
            'disclaimer' => 'AI assist is optional. Verify the canonical conversation timeline before acting.',
            'latency_budget_ms' => self::LATENCY_BUDGET_MS,
            'cost_tier' => self::COST_TIER,
            'generated_from' => $sourceCounts,
        ];
    }

    /**
     * @param  Collection<int, ConversationMessage>  $messages
     * @param  Collection<int, ConversationAnalysis>  $analyses
     * @return array<string, int>
     */
    private function sourceCounts(Collection $messages, Collection $analyses): array
    {
        $messageCount = 0;
        $customerMessageCount = 0;
        $internalNoteCount = 0;

        foreach ($messages as $message) {
            if (! $message instanceof ConversationMessage) {
                continue;
            }

            $messageCount++;

            if ((bool) $message->is_internal_note) {
                $internalNoteCount++;
            }

            if (Str::lower((string) $message->sender) === 'user' && trim((string) $message->message_text) !== '') {
                $customerMessageCount++;
            }
        }

        return [
            'message_count' => $messageCount,
            'customer_message_count' => $customerMessageCount,
            'internal_note_count' => $internalNoteCount,
            'analysis_count' => $analyses->count(),
        ];
    }

    /**
     * @param  array<string, int>  $sourceCounts
     * @return array<string, mixed>
     */
    private function fallbackEnvelope(
        string $status,
        string $fallbackReasonCode,
        string $fallbackReason,
        array $sourceCounts,
    ): array {
        return [
            'status' => $status,
            'feature_key' => self::FEATURE_KEY,
            'provider' => self::PROVIDER,
            'model' => self::MODEL,
            'priority' => null,
            'summary' => null,
            'suggested_actions' => [],
            'risk_flags' => [],
            'fallback_reason_code' => $fallbackReasonCode,
            'fallback_reason' => $fallbackReason,
            'disclaimer' => 'Conversation timeline remains the source of truth when AI assist is unavailable.',
            'latency_budget_ms' => self::LATENCY_BUDGET_MS,
            'cost_tier' => self::COST_TIER,
            'generated_from' => $sourceCounts,
        ];
    }

    private function buildSummary(
        Conversation $conversation,
        ConversationMessage $signalMessage,
        ?ConversationAnalysis $latestAnalysis,
    ): ?string {
        $excerpt = $this->excerpt((string) $signalMessage->message_text);

        if ($excerpt === null) {
            return null;
        }

        $summary = $this->conversationLead($conversation, $latestAnalysis);
        $summary = $this->appendSentence($summary, 'Latest thread signal: '.$excerpt);

        if ($this->hasAnyKeyword((string) $signalMessage->message_text, ['arrival', 'late', 'delay', 'move', 'update', 'reschedule'])) {
            $summary = $this->appendSentence($summary, 'Guest is asking for a time or booking change');
        }

        if ($this->hasAnyKeyword((string) $signalMessage->message_text, ['cancel', 'refund'])) {
            $summary = $this->appendSentence($summary, 'The thread may need a policy-sensitive follow-up');
        }

        if ($this->isLowConfidence($latestAnalysis)) {
            $summary = $this->appendSentence($summary, 'Confidence is limited, so verify the full timeline before replying');
        }

        return $summary;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function buildSuggestedActions(Conversation $conversation, ConversationMessage $signalMessage): array
    {
        $actions = [];

        if ($conversation->relationLoaded('linkedReservation') && $conversation->linkedReservation !== null) {
            $reservationCode = trim((string) ($conversation->linkedReservation->reservation_code ?? ''));
            $actions['review_reservation'] = [
                'code' => 'review_reservation',
                'label' => 'Check reservation',
                'reason' => $reservationCode !== ''
                    ? sprintf('Conversation is linked to reservation %s.', $reservationCode)
                    : 'Conversation is linked to a reservation record.',
            ];
        }

        if ($conversation->relationLoaded('linkedWaitingList') && $conversation->linkedWaitingList !== null) {
            $actions['review_waiting_list'] = [
                'code' => 'review_waiting_list',
                'label' => 'Check waiting list',
                'reason' => 'Conversation is linked to a waiting-list entry.',
            ];
        }

        if ($this->hasAnyKeyword((string) $signalMessage->message_text, ['arrival', 'late', 'delay', 'move', 'update', 'reschedule'])) {
            $actions['update_arrival_note'] = [
                'code' => 'update_arrival_note',
                'label' => 'Update arrival note',
                'reason' => 'Latest guest signal suggests an arrival or booking change.',
            ];
        }

        if ($this->hasAnyKeyword((string) $signalMessage->message_text, ['cancel', 'refund'])) {
            $actions['review_policy'] = [
                'code' => 'review_policy',
                'label' => 'Review policy before reply',
                'reason' => 'Latest guest signal may trigger cancellation or refund policy handling.',
            ];
        }

        if ($actions === []) {
            $actions['review_thread'] = [
                'code' => 'review_thread',
                'label' => 'Review timeline',
                'reason' => 'Use the canonical thread timeline before replying.',
            ];
        }

        return array_slice(array_values($actions), 0, 3);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildRiskFlags(
        Conversation $conversation,
        ConversationMessage $signalMessage,
        ?ConversationAnalysis $latestAnalysis,
    ): array {
        $flags = [];

        if ($this->looksLikeSpam($conversation, $latestAnalysis)) {
            $flags['spam_risk'] = [
                'code' => 'spam_risk',
                'label' => 'Possible spam',
                'severity' => 'medium',
            ];
        }

        if ($this->needsTimeSensitiveFollowUp($conversation, $signalMessage)) {
            $flags['time_sensitive'] = [
                'code' => 'time_sensitive',
                'label' => 'Time-sensitive follow-up',
                'severity' => 'high',
            ];
        }

        if ($this->isLowConfidence($latestAnalysis)) {
            $flags['low_confidence'] = [
                'code' => 'low_confidence',
                'label' => 'Low-confidence signal',
                'severity' => 'low',
            ];
        }

        return array_values($flags);
    }

    private function determinePriority(
        Conversation $conversation,
        ConversationMessage $signalMessage,
        ?ConversationAnalysis $latestAnalysis,
    ): string {
        if ($this->looksLikeSpam($conversation, $latestAnalysis)) {
            return 'low';
        }

        if ($this->needsTimeSensitiveFollowUp($conversation, $signalMessage)
            || $this->hasAnyKeyword((string) $signalMessage->message_text, ['cancel', 'refund'])) {
            return 'high';
        }

        return 'normal';
    }

    private function needsTimeSensitiveFollowUp(Conversation $conversation, ConversationMessage $signalMessage): bool
    {
        if ($this->hasActiveWaitingListLink($conversation)) {
            return true;
        }

        return $this->hasAnyKeyword((string) $signalMessage->message_text, ['arrival', 'late', 'delay', 'move', 'update', 'reschedule']);
    }

    private function hasActiveWaitingListLink(Conversation $conversation): bool
    {
        if (! $conversation->relationLoaded('linkedWaitingList') || $conversation->linkedWaitingList === null) {
            return false;
        }

        $status = Str::lower((string) ($conversation->linkedWaitingList->status?->value ?? $conversation->linkedWaitingList->status));

        return in_array($status, ['waiting', 'notified'], true);
    }

    private function looksLikeSpam(Conversation $conversation, ?ConversationAnalysis $latestAnalysis): bool
    {
        $status = Str::lower((string) ($conversation->status?->value ?? $conversation->status));

        if ($status === 'spam') {
            return true;
        }

        return (bool) ($latestAnalysis?->is_spam ?? false);
    }

    private function isLowConfidence(?ConversationAnalysis $latestAnalysis): bool
    {
        if (! $latestAnalysis instanceof ConversationAnalysis) {
            return false;
        }

        $score = $latestAnalysis->quality_score !== null ? (float) $latestAnalysis->quality_score : null;

        return $score !== null && $score < 0.75;
    }

    private function conversationLead(Conversation $conversation, ?ConversationAnalysis $latestAnalysis): string
    {
        if ($this->looksLikeSpam($conversation, $latestAnalysis)) {
            return 'Thread may be spam and needs verification.';
        }

        if ($conversation->relationLoaded('linkedReservation') && $conversation->linkedReservation !== null) {
            $reservationCode = trim((string) ($conversation->linkedReservation->reservation_code ?? ''));

            return $reservationCode !== ''
                ? sprintf('Reservation %s needs follow-up.', $reservationCode)
                : 'Reservation-linked thread needs follow-up.';
        }

        if ($conversation->relationLoaded('linkedWaitingList') && $conversation->linkedWaitingList !== null) {
            return $this->hasActiveWaitingListLink($conversation)
                ? 'Waiting-list follow-up needs attention.'
                : 'Waiting-list-linked thread needs review.';
        }

        $intent = $this->extractIntent($conversation, $latestAnalysis);

        if ($intent !== null) {
            return sprintf('%s follow-up needs review.', Str::headline(str_replace('_', ' ', $intent)));
        }

        return 'Conversation needs follow-up.';
    }

    private function extractIntent(Conversation $conversation, ?ConversationAnalysis $latestAnalysis): ?string
    {
        $analysisIntent = $latestAnalysis instanceof ConversationAnalysis
            ? $latestAnalysis->extracted_info['intent'] ?? null
            : null;
        $intent = is_string($analysisIntent) && trim($analysisIntent) !== ''
            ? trim($analysisIntent)
            : trim((string) ($conversation->intent_detected ?? ''));

        return $intent !== '' ? $intent : null;
    }

    private function excerpt(string $value): ?string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($normalized === '') {
            return null;
        }

        return rtrim((string) Str::limit($normalized, 140, '...'), '.');
    }

    /**
     * @param  list<string>  $keywords
     */
    private function hasAnyKeyword(string $value, array $keywords): bool
    {
        $haystack = Str::lower($value);

        foreach ($keywords as $keyword) {
            if (Str::contains($haystack, Str::lower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function appendSentence(string $base, string $sentence): string
    {
        $base = rtrim(trim($base), '.');
        $sentence = rtrim(trim($sentence), '.');

        if ($base === '') {
            return $sentence === '' ? '' : $sentence.'.';
        }

        if ($sentence === '') {
            return $base.'.';
        }

        return $base.'. '.$sentence.'.';
    }
}
