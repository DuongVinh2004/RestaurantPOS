# Storage Policy

Customer-web should keep auth storage small and explicit.

## Store

- Customer access token.
- Optional customer session id when returned or required by a stable route.
- Minimal timestamp metadata if needed for refresh scheduling.

## Do Not Store

- Staff keys.
- Admin credentials.
- Full customer profiles.
- Payment details.
- Reservation or bill snapshots.

## Access Pattern

Expose storage through `lib/auth`, not direct browser storage calls inside components. Keep read/write methods narrow enough to mock in tests.

## Logout

Logout must clear token, session id, and customer-scoped query cache.
