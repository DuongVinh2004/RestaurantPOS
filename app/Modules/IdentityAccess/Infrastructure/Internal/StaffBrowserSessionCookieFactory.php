<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Internal;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class StaffBrowserSessionCookieFactory
{
    public function enabled(): bool
    {
        return (bool) config('staff_auth.browser_session.enabled', false);
    }

    public function requested(Request $request): bool
    {
        $bodyMode = trim((string) $request->input('session_transport', ''));
        $headerMode = trim((string) ($request->header('X-Staff-Session-Mode') ?? ''));

        return $bodyMode === 'refresh_cookie' || $headerMode === 'refresh_cookie';
    }

    public function refreshCookieName(): string
    {
        return $this->nonEmptyConfig('refresh_cookie_name', 'staff_web_refresh');
    }

    public function csrfCookieName(): string
    {
        return $this->nonEmptyConfig('csrf_cookie_name', 'staff_web_csrf');
    }

    public function csrfHeaderName(): string
    {
        return $this->nonEmptyConfig('csrf_header', 'X-Staff-CSRF');
    }

    public function refreshTokenFromRequest(Request $request): string
    {
        return trim((string) ($request->cookies->get($this->refreshCookieName()) ?? ''));
    }

    public function csrfTokenFromRequest(Request $request): string
    {
        return trim((string) ($request->cookies->get($this->csrfCookieName()) ?? ''));
    }

    public function csrfHeaderFromRequest(Request $request): string
    {
        return trim((string) ($request->header($this->csrfHeaderName()) ?? ''));
    }

    public function issueCsrfToken(): string
    {
        $nonce = Str::random(40);

        return $nonce.'.'.$this->signCsrfNonce($nonce);
    }

    public function csrfMatches(Request $request): bool
    {
        $cookieToken = $this->csrfTokenFromRequest($request);
        $headerToken = $this->csrfHeaderFromRequest($request);

        return $cookieToken !== ''
            && $headerToken !== ''
            && hash_equals($cookieToken, $headerToken)
            && $this->csrfTokenIsSigned($cookieToken);
    }

    public function makeRefreshCookie(string $refreshToken): Cookie
    {
        return Cookie::create(
            $this->refreshCookieName(),
            $refreshToken,
            now('UTC')->addMinutes($this->refreshTtlMinutes()),
            $this->nonEmptyConfig('refresh_cookie_path', '/api/v1/auth/staff'),
            $this->nullableConfig('refresh_cookie_domain'),
            $this->secure(),
            true,
            false,
            $this->sameSite(),
        );
    }

    public function makeCsrfCookie(string $csrfToken): Cookie
    {
        return Cookie::create(
            $this->csrfCookieName(),
            $csrfToken,
            now('UTC')->addMinutes($this->refreshTtlMinutes()),
            $this->nonEmptyConfig('csrf_cookie_path', '/'),
            $this->nullableConfig('csrf_cookie_domain'),
            $this->secure(),
            false,
            false,
            $this->sameSite(),
        );
    }

    /**
     * @return list<Cookie>
     */
    public function clearCookies(): array
    {
        return [
            Cookie::create(
                $this->refreshCookieName(),
                '',
                now('UTC')->subYear(),
                $this->nonEmptyConfig('refresh_cookie_path', '/api/v1/auth/staff'),
                $this->nullableConfig('refresh_cookie_domain'),
                $this->secure(),
                true,
                false,
                $this->sameSite(),
            ),
            Cookie::create(
                $this->csrfCookieName(),
                '',
                now('UTC')->subYear(),
                $this->nonEmptyConfig('csrf_cookie_path', '/'),
                $this->nullableConfig('csrf_cookie_domain'),
                $this->secure(),
                false,
                false,
                $this->sameSite(),
            ),
        ];
    }

    private function refreshTtlMinutes(): int
    {
        return max(1, (int) config('staff_auth.session_ttl_minutes', 30));
    }

    private function sameSite(): string
    {
        $sameSite = strtolower(trim((string) config('staff_auth.browser_session.same_site', 'lax')));

        return in_array($sameSite, [Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE], true)
            ? $sameSite
            : Cookie::SAMESITE_LAX;
    }

    private function secure(): bool
    {
        return (bool) config('staff_auth.browser_session.secure', true);
    }

    private function csrfTokenIsSigned(string $token): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$nonce, $signature] = $parts;

        return $nonce !== '' && hash_equals($this->signCsrfNonce($nonce), $signature);
    }

    private function signCsrfNonce(string $nonce): string
    {
        $key = trim((string) config('app.key', ''));
        if ($key === '') {
            $key = 'restaurantpos-staff-browser-session-csrf';
        }

        return hash_hmac('sha256', $nonce, $key);
    }

    private function nonEmptyConfig(string $key, string $default): string
    {
        $value = trim((string) config('staff_auth.browser_session.'.$key, $default));

        return $value !== '' ? $value : $default;
    }

    private function nullableConfig(string $key): ?string
    {
        $value = trim((string) config('staff_auth.browser_session.'.$key, ''));

        return $value !== '' ? $value : null;
    }
}
