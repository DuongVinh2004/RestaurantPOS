# Support Matrix Template

Use this compact format before implementing customer-web flows.

| Feature | Route evidence | Status | Headers | Frontend decision | Notes |
|---|---|---|---|---|---|
| Customer login | `POST /...` from frozen OpenAPI or SDK | `stable/live` | none before login | Live adapter | Request and response envelopes are explicit. |
| Waiting list | Artifact missing or owner/session unclear | `scaffolded/flagged` | `X-Customer-Token`, maybe `X-Session-Id` | Disabled UI and adapter boundary | Do not enable until 401/403 and owner contract are fixed. |

## Status Values

- `stable/live`: safe for real adapter use.
- `scaffolded/flagged`: UI and adapter boundary are allowed, live calls are disabled.
- `blocked/unavailable`: do not build the main journey on this surface.

## Evidence Priority

1. Frozen OpenAPI under `storage/app/booking_release/openapi-v1.json`.
2. Generated consumer artifacts under `build/api-consumer/`.
3. Frontend-facing docs generated from contract metadata.
4. Route/controller inspection only to explain gaps.
