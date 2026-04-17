# Response Envelope

Parse Laravel API responses centrally.

## Success

Expected stable success shapes:

```json
{ "data": {} }
```

```json
{ "data": [], "meta": {} }
```

Adapters should return typed domain data, not raw envelopes, unless pagination metadata is part of the feature contract.

## Error

Normalize failures into a frontend error model with:

- `status`
- `message`
- `errorCode`
- `requestId`
- `validationErrors`
- `raw`

Preserve backend fields when available:

- `error_code`
- `message`
- `request_id`
- `errors`

## UX Mapping

- `401`: ask the customer to sign in again.
- `403`: explain that this item is not available for this session or account.
- `409`: show stale or conflict copy and offer refresh.
- `422`: attach validation messages to form fields when possible.
- `5xx`: show a retryable service error and keep `requestId` available for support.
