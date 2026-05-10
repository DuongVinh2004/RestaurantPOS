<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Application\UseCases\ApiKeys\RotateStaffApiKeyHandler;
use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\IdentityAccess\Infrastructure\Internal\AuthenticatedStaffPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use App\Support\AuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Lam moi phien staff theo 2 duong:
 * app/API token thi rotate key hien tai,
 * browser session thi rotate refresh key va cap access key ngan han moi.
 */
class RefreshStaffSessionHandler
{
    public function __construct(
        private readonly StaffApiKeyStore $staffApiKeyStore,
        private readonly RotateStaffApiKeyHandler $rotateStaffApiKeyHandler,
        private readonly AuthenticatedStaffPayloadBuilder $authenticatedStaffPayloadBuilder,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $staffApiKeyId): array
    {
        // Pha 1: xac minh key hien tai van active truoc khi cho phep refresh.
        $current = $this->requireActiveStaffApiKey($staffApiKeyId);

        // Pha 2: rotate key cu thanh key moi de cat replay risk cua token cu.
        $rotated = $this->rotateStaffApiKeyHandler->handle(
            (int) $current->getKey(),
            (string) $current->label,
            $this->staffSessionExpiry(),
        );

        // Load role truoc khi build payload de client nhan du actor context ngay sau refresh.
        $replacement = $rotated['record'];
        $replacement->loadMissing('user.role');

        // Audit giu du cap revoked/replacement ids de ops trace duoc vong doi token.
        AuditEvent::info('staff_password_login_refreshed', [
            'user_id' => (int) ($replacement->user_id ?? 0),
            'revoked_staff_api_key_id' => (int) ($rotated['revoked']->getKey() ?? 0),
            'replacement_staff_api_key_id' => (int) $replacement->getKey(),
        ]);

        // Pha 3: tra payload moi kem plaintext key moi de client thay token ngay.
        return $this->authenticatedStaffPayloadBuilder->build(
            $replacement,
            (string) $rotated['plaintext_key'],
        );
    }

    /**
     * @return array{payload:array<string,mixed>,refresh_token:string}
     */
    public function handleBrowserSession(int $refreshStaffApiKeyId, ?int $currentAccessStaffApiKeyId = null): array
    {
        // Browser flow bat dau bang refresh token dang duoc giu o lop cookie/session.
        $currentRefresh = $this->requireActiveStaffApiKey($refreshStaffApiKeyId);

        // Rotate refresh key de moi lan refresh deu vo hieu hoa refresh token cu.
        $rotatedRefresh = $this->rotateStaffApiKeyHandler->handle(
            (int) $currentRefresh->getKey(),
            (string) $currentRefresh->label,
            $this->staffSessionExpiry(),
        );

        // Access key cu duoc revoke best-effort de giam kha nang ton tai nhieu access token song song.
        if ($currentAccessStaffApiKeyId !== null && $currentAccessStaffApiKeyId > 0 && $currentAccessStaffApiKeyId !== $refreshStaffApiKeyId) {
            $this->revokeBestEffort($currentAccessStaffApiKeyId, 'staff_browser_access_refresh');
        }

        $replacementRefresh = $rotatedRefresh['record'];
        // Issue access token ngan han moi tu cung user cua refresh session da xoay.
        $access = $this->staffApiKeyStore->issueKey(
            (int) $replacementRefresh->user_id,
            $this->browserAccessTokenLabel((string) $replacementRefresh->label),
            $this->browserAccessExpiry(),
        );

        $accessRecord = $access['record'];
        $accessRecord->loadMissing('user.role');

        // Audit tach rieng cac id de debug browser session lifecycle de hon.
        AuditEvent::info('staff_browser_session_refreshed', [
            'user_id' => (int) ($accessRecord->user_id ?? 0),
            'revoked_refresh_staff_api_key_id' => (int) $rotatedRefresh['revoked']->getKey(),
            'replacement_refresh_staff_api_key_id' => (int) $replacementRefresh->getKey(),
            'access_staff_api_key_id' => (int) $accessRecord->getKey(),
        ]);

        $payload = $this->authenticatedStaffPayloadBuilder->build(
            $accessRecord,
            (string) $access['plaintext_key'],
        );
        $payload['auth_mode'] = 'staff_browser_session';
        $payload['session_transport'] = 'refresh_cookie';

        return [
            'payload' => $payload,
            'refresh_token' => (string) $rotatedRefresh['plaintext_key'],
        ];
    }

    private function requireActiveStaffApiKey(int $staffApiKeyId): StaffApiKey
    {
        // Gate active nay duoc dung chung cho moi flow can doc/refresh phien staff.
        $record = $this->staffApiKeyStore->showKey($staffApiKeyId);

        if ($record->revoked_at !== null || ($record->expires_at !== null && ! $record->expires_at->utc()->isFuture())) {
            throw ValidationException::withMessages([
                'staff_api_key' => ['Staff auth session is no longer active.'],
            ]);
        }

        $record->loadMissing('user.role');

        return $record;
    }

    private function staffSessionExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('staff_auth.session_ttl_minutes', 720)));
    }

    private function browserAccessExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('staff_auth.browser_session.access_ttl_minutes', 5)));
    }

    private function browserAccessTokenLabel(string $refreshLabel): string
    {
        // Neu refresh label theo prefix chuan thi copy suffix sang access label de session list de doi chieu.
        $accessPrefix = trim((string) config('staff_auth.browser_session.access_label_prefix', 'Staff Browser Access Token'));
        $refreshPrefix = trim((string) config('staff_auth.browser_session.refresh_label_prefix', 'Staff Browser Refresh Session'));
        $suffix = $refreshPrefix !== '' && str_starts_with($refreshLabel, $refreshPrefix)
            ? trim(substr($refreshLabel, strlen($refreshPrefix)))
            : '';

        return ($accessPrefix !== '' ? $accessPrefix : 'Staff Browser Access Token').($suffix !== '' ? ' '.$suffix : '');
    }

    private function revokeBestEffort(int $staffApiKeyId, string $reason): void
    {
        try {
            $this->staffApiKeyStore->revokeKey($staffApiKeyId, $reason);
        } catch (Throwable $exception) {
            // Khong fail ca luong refresh vi access cu revoke that bai, nhung van phai de warning cho ops.
            AuditEvent::warning('staff_browser_access_revoke_failed', [
                'staff_api_key_id' => $staffApiKeyId,
                'reason' => $reason,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
