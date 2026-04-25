# Staff-web Auth Session Hardening

## Release Note - 2026-04-25

Intent: reduce the blast radius of a stolen staff API key from browser persistent storage.

Changed behavior:

- `staff-web` no longer stores the staff `access_token` / `X-Staff-Key` value in `localStorage`.
- The active staff token is held in JavaScript memory only and is sent as `X-Staff-Key` for the existing backend contract.
- Any legacy `restaurantpos.staff_web.staff_api_key` value found in `localStorage` is removed on auth storage access.
- Staff login and refresh still receive a database-backed opaque access key from the backend for `X-Staff-Key`; staff-web keeps it in memory only.
- An opt-in browser refresh-cookie contract can now survive reload without putting the refresh credential in JS storage.
- Staff logout revokes the active server-side session material where available, clears refresh/CSRF cookies on cookie-backed sessions, then clears client memory in a `finally` path.
- Default staff auth session TTL is now 120 minutes unless `STAFF_AUTH_SESSION_TTL_MINUTES` overrides it.

Security tradeoff:

- This is a staged cookie-backed refresh design, not a full replacement for every staff auth consumer.
- It removes reusable staff keys from persistent browser storage, so a later localStorage read cannot recover the key after reload.
- A live XSS running in the active page can still read or use in-memory JavaScript state while the page remains open.
- When enabled, reload can restore the staff session by POSTing to refresh with an HttpOnly refresh cookie and CSRF header.
- Previously issued tokens that were already copied outside the browser remain server-active until expiry or explicit revocation.
- Cookie-backed refresh still issues short-lived memory access tokens through the existing `X-Staff-Key` middleware. Refresh-cookie secrets are labeled separately and are rejected as header auth.

Compatibility:

- Default backend route shape, `X-Staff-Key` transport, generated API artifacts, and CORS `supports_credentials=false` remain unchanged.
- Cookie-backed refresh is gated by `STAFF_AUTH_BROWSER_SESSION_COOKIE_ENABLED=true` and `VITE_STAFF_REFRESH_COOKIE_ENABLED=true`.
- Browser clients must continue to avoid `credentials: 'include'` for general API calls. staff-web only opts credentials into `/auth/staff/login`, `/auth/staff/refresh`, and `/auth/staff/logout` when the rollout flag is enabled.
- Existing header-based staff login/refresh/logout remains available for the rollout window.

Ops notes:

- Operators should expect staff-web users to re-authenticate after a page reload unless both backend and staff-web cookie flags are enabled.
- Keep `CORS_ALLOWED_ORIGINS` exact and narrow; do not enable wildcard origins.
- Set `CORS_SUPPORTS_CREDENTIALS=true` only for exact staff-web/customer-web origins participating in the cookie rollout. Do not combine credentials with wildcard origins or origin patterns.
- Default refresh cookie attributes are HttpOnly, `Secure`, `SameSite=Lax`, and path `/api/v1/auth/staff`.
- CSRF protection uses a signed readable `staff_web_csrf` cookie echoed as `X-Staff-CSRF`; simple cross-site forms cannot set the header, and non-allowlisted origins fail CORS preflight.
- For split subdomains, keep the refresh cookie host-only where possible. If staff-web must read the CSRF cookie across subdomains, configure only `STAFF_AUTH_BROWSER_CSRF_COOKIE_DOMAIN` to the narrow parent domain.
- Deploy staff-web behind a response-header CSP where possible. A practical starting point is `default-src 'self'; script-src 'self'; connect-src 'self' <api-origin>; img-src 'self' data:; style-src 'self' 'unsafe-inline'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'`.
- If a staff key is suspected leaked, use `php artisan staff-auth:api-keys:revoke <staff_api_key_id> --json` or rotate through the existing staff auth commands.

Rollout:

1. Enable exact `CORS_ALLOWED_ORIGINS` for the staff-web origin.
2. Set `STAFF_AUTH_BROWSER_SESSION_COOKIE_ENABLED=true` and `CORS_SUPPORTS_CREDENTIALS=true` on the backend.
3. Set `VITE_STAFF_REFRESH_COOKIE_ENABLED=true` on staff-web.
4. Verify login, reload -> refresh restore, protected API `401`, CSRF `419`, and logout cookie clearing.
