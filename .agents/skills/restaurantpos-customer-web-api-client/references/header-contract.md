# Header Contract

Customer-web uses explicit headers.

## Auth

- `X-Customer-Token`: primary customer auth header.
- `X-Session-Id`: add only for session-bound customer routes.
- `Idempotency-Key`: add for unsafe mutations when the backend contract requires replay protection.

## Rules

- Do not use cookies for auth.
- Do not send `credentials: include`.
- Do not send staff or admin headers from customer-web.
- Keep header injection inside `lib/api` or `lib/auth`.
- Let feature adapters request a session id or idempotency key explicitly instead of guessing.

## Testing

Header tests should cover:

- No token stored.
- Token stored.
- Session-bound request.
- Mutation with generated idempotency key.
- Mutation with caller-provided idempotency key.
