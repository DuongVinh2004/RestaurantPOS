<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Application\Services;

use App\Modules\Conversations\Domain\Models\ConversationFile;
use App\Modules\Conversations\Domain\Models\ConversationMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\URL;

class StaffConversationFileAccessService
{
    private const ACCESS_TTL_MINUTES = 5;

    public function __construct(
        private readonly StaffConversationInboxService $inboxService,
    ) {
    }

    /**
     * @return array{
     *   file_id:int,
     *   file_url:string,
     *   access_expires_at:string,
     *   mime_type:?string,
     *   created_at:?string
     * }
     */
    public function filePayload(ConversationFile $file, string $conversationId, ?CarbonImmutable $expiresAt = null): array
    {
        $expiresAt ??= $this->accessExpiresAt();

        return [
            'file_id' => (int) $file->file_id,
            'file_url' => $this->temporaryFileAccessUrl($conversationId, (int) $file->file_id, $expiresAt),
            'access_expires_at' => $expiresAt->utc()->toIso8601String(),
            'mime_type' => $file->mime_type,
            'created_at' => $file->created_at instanceof \DateTimeInterface
                ? CarbonImmutable::instance($file->created_at)->utc()->toIso8601String()
                : ($file->created_at !== null ? CarbonImmutable::parse((string) $file->created_at)->utc()->toIso8601String() : null),
        ];
    }

    /**
     * @return array{file_id:?int,message_id:int,access_url:string,access_expires_at:string,mime_type:?string}|null
     */
    public function preferredAttachmentPayload(ConversationMessage $message, ?CarbonImmutable $expiresAt = null): ?array
    {
        $expiresAt ??= $this->accessExpiresAt();
        $file = $message->preferredAttachmentFile();
        if ($file instanceof ConversationFile) {
            return [
                'file_id' => (int) $file->file_id,
                'message_id' => (int) $message->message_id,
                'access_url' => $this->temporaryFileAccessUrl((string) $message->conversation_id, (int) $file->file_id, $expiresAt),
                'access_expires_at' => $expiresAt->utc()->toIso8601String(),
                'mime_type' => $file->mime_type,
            ];
        }

        if ($message->legacyAttachmentUrl() === null) {
            return null;
        }

        return [
            'file_id' => null,
            'message_id' => (int) $message->message_id,
            'access_url' => $this->temporaryLegacyAttachmentAccessUrl((string) $message->conversation_id, (int) $message->message_id, $expiresAt),
            'access_expires_at' => $expiresAt->utc()->toIso8601String(),
            'mime_type' => null,
        ];
    }

    public function temporaryFileAccessUrl(string $conversationId, int $fileId, ?CarbonImmutable $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'staff.conversations.files.access',
            ($expiresAt ?? $this->accessExpiresAt())->utc(),
            [
                'conversation_id' => $conversationId,
                'file_id' => $fileId,
            ],
        );
    }

    public function temporaryLegacyAttachmentAccessUrl(string $conversationId, int $messageId, ?CarbonImmutable $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'staff.conversations.messages.attachment',
            ($expiresAt ?? $this->accessExpiresAt())->utc(),
            [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
            ],
        );
    }

    public function resolveFileDownloadUrl(string $conversationId, int $fileId, ?int $staffActorUserId = null): string
    {
        $this->inboxService->findSummaryOrFail($conversationId, $staffActorUserId);

        /** @var ConversationFile|null $file */
        $file = ConversationFile::query()
            ->where('file_id', $fileId)
            ->whereHas('message', static function ($query) use ($conversationId): void {
                $query->where('conversation_id', $conversationId);
            })
            ->first();

        if (! $file instanceof ConversationFile) {
            throw (new ModelNotFoundException())->setModel(ConversationFile::class, [$fileId]);
        }

        return $this->validatedStoredUrl((string) ($file->file_url ?? ''));
    }

    public function resolveLegacyAttachmentDownloadUrl(string $conversationId, int $messageId, ?int $staffActorUserId = null): string
    {
        $this->inboxService->findSummaryOrFail($conversationId, $staffActorUserId);

        /** @var ConversationMessage|null $message */
        $message = ConversationMessage::query()
            ->where('message_id', $messageId)
            ->where('conversation_id', $conversationId)
            ->first();

        if (! $message instanceof ConversationMessage) {
            throw (new ModelNotFoundException())->setModel(ConversationMessage::class, [$messageId]);
        }

        return $this->validatedStoredUrl((string) ($message->legacyAttachmentUrl() ?? ''));
    }

    private function accessExpiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->addMinutes(self::ACCESS_TTL_MINUTES);
    }

    private function validatedStoredUrl(string $url): string
    {
        $normalized = trim($url);
        if ($normalized === '' || str_starts_with($normalized, 'redacted://')) {
            throw (new ModelNotFoundException())->setModel(ConversationFile::class);
        }

        $validated = filter_var($normalized, FILTER_VALIDATE_URL);
        $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));
        if ($validated === false || ! in_array($scheme, ['http', 'https'], true)) {
            throw (new ModelNotFoundException())->setModel(ConversationFile::class);
        }

        return $normalized;
    }
}
