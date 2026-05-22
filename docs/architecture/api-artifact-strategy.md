# API Artifact Strategy

RestaurantPOS heavily relies on generated API artifacts to ensure the frontend clients (`customer-web` and `staff-web`) remain tightly synchronized with the backend Laravel API.

## Why We Use API Artifacts

In split-stack applications, the API boundary is the most common source of regressions. If the backend changes a response shape or requires a new field, the frontend will break unless it is updated simultaneously.

By generating explicit artifacts, we:
- Provide strict type safety for Next.js and React clients.
- Provide human-readable documentation for the API surface.
- Catch API drift during CI/CD before it hits production.

## Key Artifacts

### OpenAPI Artifact
The foundation of our contract is the OpenAPI definition (e.g., `storage/app/booking_release/openapi-v1.json`). This machine-readable file describes all endpoints, HTTP methods, required parameters, and response envelopes.

### Generated SDK, Enums, and Mutation Contracts
From the OpenAPI definition, we automatically generate:
- **TypeScript SDK**: Functions that map directly to backend endpoints, providing `customer-web` and `staff-web` with typed requests and responses.
- **Enums & State Maps**: Shared constants (e.g., `OrderState`, `ReservationStatus`) so the frontend doesn't hardcode raw strings.
- **Postman Collections**: Ready-to-use collections for manual API exploration.
- **Mutation Contracts**: Markdown or JSON files detailing complex write operations (POST/PUT/PATCH).

These generated files live in `build/api-consumer/`. **Developers should never hand-edit the API shapes in the frontend source code.**

### Release Manifest
The `release_manifest_snapshot.json` (often found in `storage/app/booking_release/`) explicitly lists the current state of the API routes and schema. This manifest is packaged with every release to verify that the deployed environment matches the expected compiled state.

## Drift Control

### How Stale Artifacts Are Detected
If a backend developer modifies a controller, adds a route, or changes a Form Request but forgets to regenerate the artifacts, the CI pipeline will catch it.
- `php artisan booking:release-manifest --verify-frozen` checks if the current backend codebase matches the frozen artifacts. If there is a mismatch, the release gate fails.

### Regenerating Artifacts
When you change the API, you must update the artifacts locally before committing:
```bash
composer api:artifacts
```
This command regenerates the OpenAPI spec, builds the new TypeScript SDK, and updates the release manifest.

---

## Interview Explanation

**English Version:**
"To prevent regressions between our Laravel backend and our Next.js/React frontends, we use a strict API artifact strategy. Instead of hand-writing API calls on the frontend, we generate an OpenAPI spec from the backend codebase. From that spec, we generate a TypeScript SDK, enums, and mutation contracts. We also generate a release manifest that CI uses to verify that the API contract hasn't drifted. This ensures end-to-end type safety and guarantees that frontend clients are always communicating correctly with the backend."

**Vietnamese Version:**
"Để ngăn chặn các lỗi (regressions) giữa backend Laravel và frontend Next.js/React, chúng tôi sử dụng chiến lược API artifact nghiêm ngặt. Thay vì viết tay các API call ở frontend, chúng tôi generate một bản đặc tả OpenAPI từ backend. Từ bản đặc tả đó, chúng tôi tiếp tục generate ra TypeScript SDK, các enum và hợp đồng thay đổi dữ liệu (mutation contracts). Chúng tôi cũng tạo ra một release manifest để hệ thống CI kiểm tra nhằm đảm bảo hợp đồng API không bị sai lệch (drift). Điều này mang lại sự an toàn kiểu dữ liệu (type safety) từ đầu đến cuối và đảm bảo rằng frontend luôn giao tiếp chính xác với backend."
