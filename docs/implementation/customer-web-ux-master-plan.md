# Customer-Web UX Master Plan

## Intent

Build a production-grade customer-facing web foundation without expanding unfinished menu, reservation, cart, or payment flows beyond truthful placeholders and existing API integrations.

## Audit Result

### Framework and Routing

- `customer-web` is a Next.js 16 App Router app using React 19, TypeScript, Tailwind 4, shadcn/Radix primitives, TanStack Query, React Hook Form, Zod, Vitest, and Playwright.
- Root layout wraps all routes with `AppQueryProvider`, `AuthProvider`, `AppShell`, and `Toaster`.
- Current route groups:
  - `(public)`: `/`, `/booking`, `/menu/[id]`
  - `(auth)`: `/login`, plus existing untracked register work in the workspace
  - `(protected)`: `/account`, `/reservations`, `/reservations/new`, `/reservations/[id]`, `/waiting-list`
- Root `/` currently renders `MenuPage`, so there is no separate smart homepage.
- `/menu` list route is missing; menu listing lives at root and menu details live under `/menu/[id]`.

### Existing Pages and Components

- Existing pages already cover menu browse, booking, reservations, waiting list, account, login, deposit/billing/preorder panels, and privacy/benefits panels.
- Existing shadcn wrappers exist under `customer-web/src/components/ui`.
- Existing state components include `LoadingBlock`, `EmptyState`, and `ErrorState`.
- Existing layout components include `AppShell`, `ProtectedRoute`, `BackendStatusBanner`, and `PublicFooter`.
- Gaps for this foundation:
  - Missing named customer UI wrapper layer: `AppButton`, `AppCard`, `AppBadge`, `AppInput`, `AppSelect`, `AppTextarea`, `AppSkeleton`, `SectionHeader`, `PriceText`, `StatusPill`, `StepIndicator`, `QuantityStepper`, `BottomSheet`, `ConfirmDialog`, `ResponsivePageShell`, `CustomerHeader`, and `CustomerBottomNav`.
  - Some existing customer copy in current files is mojibake, which reduces polish and accessibility.
  - No branch selector or selected-branch persistence.
  - No smart homepage separate from menu browse.

### API Client and Data Fetching

- `customer-web/src/lib/api/sdk-client.ts` uses the generated `RestaurantPosClient`.
- The client injects:
  - `X-Customer-Token` from `lib/auth/storage`
  - `X-Session-Id` from session storage, creating one when needed
  - `X-Requested-With: customer-web`
- Envelope handling and normalized API errors already exist in `src/lib/api/envelope.ts` and `src/lib/api/errors.ts`.
- Query keys are centralized in `src/lib/api/query-keys.ts`.
- Generated client artifacts are copied under `customer-web/src/lib/contracts/generated`. Per repo rules, these must not be hand-edited.

### Auth and Session Handling

- Customer auth is header-based and separated from staff/admin auth.
- `AuthProvider` bootstraps with `/api/v1/auth/customer/me`, refreshes on eligible unauthorized restore, and clears token/session on logout.
- Token storage is centralized in `lib/auth/storage.ts`.
- Guest session storage exists through `ensureCustomerSessionId`, but the app lacks explicit `useCustomerSession` and `useCustomerIdentity` hooks and a clear continue-as-guest CTA for auth-required surfaces.
- Login is password-based through existing auth APIs. OTP login is not visible in the current customer-web contract evidence and should not be invented.

### Branch and Location Support

- Public backend route `GET /api/v1/restaurant/profile` is contract-visible and returns the default branch profile:
  - `branch_id`
  - `branch_code`
  - `branch_name`
  - `timezone`
  - `business_hours`
  - `today_hours`
  - `current_status`
- No customer-facing branch collection or branch detail route is visible in the generated contract or route file.
- Branch model has address/phone data in backend resources for admin/staff contexts, but the customer profile contract does not expose address or phone.
- Customer location UX can only safely select the default profile as a single available branch today. Multiple branch selection and nearest-branch matching are missing API support.

### Existing Feature Support

| Surface | Status | Evidence | Required Headers | Frontend Decision |
|---|---|---|---|---|
| Restaurant profile/default branch | stable/live | `GET /api/v1/restaurant/profile` in routes and generated SDK | none | Use live for selected branch card and opening status. |
| Auth session | stable/live | generated SDK auth batch and existing provider | `X-Customer-Token`, optional `X-Session-Id` | Keep live. Add explicit customer session/identity hooks. |
| Menu catalog list | stable/live | generated SDK and support matrix | optional branch query when future API supports multi-branch | Keep `/menu` live; homepage links to it. |
| Reservations | stable/live for existing pages | generated SDK and support matrix | `X-Customer-Token`, `X-Session-Id`, `Idempotency-Key` | Do not add new reservation behavior in this batch. |
| Waiting list | scaffolded/flagged | support matrix says Wave 2 env flag | `X-Customer-Token`, `Idempotency-Key` | Keep nav/CTA as placeholder or gated entry. |
| Preorder/cart/payment | scaffolded/flagged or deferred | support matrix marks preorder env-gated; deposit/bill conditional | varies | Only show placeholders or existing gated panels. |
| Multi-branch selector | blocked/unavailable | no customer branch collection/detail contract | none | Implement single-branch selector boundary and document missing API. |
| Location near-me selection | blocked/unavailable for matching | no branch collection with coordinates | browser permission only | Allow user-triggered geolocation status, but do not compute real nearest branch. |

### Backend APIs Already Available

- `GET /api/v1/restaurant/profile`
- `POST /api/v1/auth/customer/register`
- `POST /api/v1/auth/customer/login`
- `GET /api/v1/auth/customer/me`
- `POST /api/v1/auth/customer/refresh`
- `POST /api/v1/auth/customer/logout`
- Existing menu, table availability, reservations, waiting-list, loyalty/voucher, privacy, preorder, deposit, bill, and payment-session routes listed in `routes/api/customer_self_service.php` and the customer-web support matrix.

### Missing APIs

- Customer-facing branch collection.
- Customer-facing branch detail by id.
- Customer-facing branch address and phone in public profile/branch contract.
- Branch coordinates or distance-ready metadata.
- Featured dishes/promotions endpoint suitable for homepage.
- Upcoming reservation summary endpoint scoped specifically for homepage. Existing reservations list may support this later, but this batch should not introduce data exposure risk.
- Favorites/recent dishes endpoint.
- Voucher/loyalty summary as default homepage data. Existing gated account benefit routes are Wave 2 and should remain gated.
- OTP auth contract.

### Frontend Gaps

- No customer UI foundation with stable names for shared components.
- No explicit customer shell components split from `AppShell`.
- No selected-branch state abstraction.
- No smart homepage.
- No explicit guest identity/session hooks.
- No reusable location permission state.
- Mojibake text should be normalized in touched customer-facing files.

### Risks

- Existing worktree is heavily dirty; edits must stay scoped and avoid reverting unrelated user changes.
- Generated API artifacts and customer generated SDK files are already modified in the workspace; this batch must not hand-edit them.
- Single default-branch profile may be mistaken for full branch selection if copy is not careful.
- Waiting list, preorder, loyalty, vouchers, and payment surfaces are not day-1 homepage dependencies.
- Location permission must only run after a user action.

## Implementation Phases

1. Audit and document current state.
2. Add customer UI/design foundation wrappers using existing shadcn/Radix primitives.
3. Split customer layout shell into reusable `CustomerHeader`, `CustomerBottomNav`, and `ResponsivePageShell`.
4. Add explicit guest-first session hooks and return-to-action helpers.
5. Add single-branch location selector foundation backed by `restaurant/profile` and local persistence.
6. Replace root menu page with a smart homepage and add `/menu` list route.
7. Run focused frontend verification: install if needed, lint, typecheck, build.

## Completed Work

- Initial audit completed from mandated repo files, route files, customer-web structure, generated SDK copies, support matrix, and relevant backend profile controller/resource.
- Added customer UI foundation wrappers around the existing shadcn/Radix primitives.
- Split reusable customer layout pieces into `ResponsivePageShell`, `CustomerHeader`, and `CustomerBottomNav`.
- Added explicit `useCustomerSession` and `useCustomerIdentity` hooks.
- Added return-to-action storage for future login-required flows.
- Added single-branch selection state backed by `GET /api/v1/restaurant/profile`, local selected-branch persistence, branch detail display, opening-hours display, directions link, and user-triggered geolocation handling.
- Replaced root `/` with a smart homepage and moved the existing menu listing to `/menu`.
- Normalized touched customer shell/footer/login/session copy to readable English and removed mojibake from those touched surfaces.

## Changed Files

- `docs/implementation/customer-web-ux-master-plan.md`
- `customer-web/src/app/layout.tsx`
- `customer-web/src/app/(public)/page.tsx`
- `customer-web/src/app/(public)/menu/page.tsx`
- `customer-web/src/components/customer/layout.tsx`
- `customer-web/src/components/customer/ui.tsx`
- `customer-web/src/components/layout/app-shell.tsx`
- `customer-web/src/components/layout/app-shell.test.tsx`
- `customer-web/src/components/layout/protected-route.tsx`
- `customer-web/src/components/layout/protected-route.test.tsx`
- `customer-web/src/components/layout/public-footer.tsx`
- `customer-web/src/components/layout/public-footer.test.tsx`
- `customer-web/src/features/auth/hooks.ts`
- `customer-web/src/features/auth/login-page.tsx`
- `customer-web/src/features/branch/branch-selector.tsx`
- `customer-web/src/features/branch/hooks.ts`
- `customer-web/src/features/branch/state.ts`
- `customer-web/src/features/branch/state.test.ts`
- `customer-web/src/features/home/home-page.tsx`
- `customer-web/src/features/restaurant/state.ts`
- `customer-web/src/lib/auth/return-to-action.ts`
- `customer-web/src/lib/auth/return-to-action.test.ts`
- `customer-web/src/lib/auth/route-access.ts`

## Added or Updated Tests

- `customer-web/src/components/layout/app-shell.test.tsx`
- `customer-web/src/components/layout/protected-route.test.tsx`
- `customer-web/src/components/layout/public-footer.test.tsx`
- `customer-web/src/features/branch/state.test.ts`
- `customer-web/src/lib/auth/return-to-action.test.ts`

## API Dependencies

- Live homepage/branch dependency: `GET /api/v1/restaurant/profile`
- Live auth dependency: customer auth session routes in generated SDK
- Live menu link target: existing `MenuPage` adapter using `GET /api/v1/menu/items`
- Future placeholders must remain gated by `customerWebRollout` and `featureFlags`.

## Verification

- `npm --prefix customer-web install` passed. NPM reported 5 moderate audit findings.
- `npm --prefix customer-web run lint` passed.
- `npm --prefix customer-web run typecheck` passed.
- `npm --prefix customer-web run test -- src/lib/auth/return-to-action.test.ts src/features/branch/state.test.ts src/components/layout/app-shell.test.tsx src/components/layout/protected-route.test.tsx src/components/layout/public-footer.test.tsx` passed: 5 files, 16 tests.
- `npm --prefix customer-web run build` passed. The build's contract governance check passed, then warned that generated API artifacts already have local Git changes in the workspace. This batch did not hand-edit generated artifacts.
- Existing customer-web dev server responded at `http://127.0.0.1:3001`.
- Playwright screenshot smoke passed for desktop and mobile homepage render.

## Missing APIs

- Customer branch list/detail with public address, phone, timezone, opening status, and optional coordinates.
- Homepage featured dishes/promotions.
- Homepage upcoming reservation summary scoped to current customer/session.
- Favorites/recent dishes.
- OTP login.

## Remaining Risks

- The branch selector is intentionally single-branch until a customer-facing branch collection/detail contract exists.
- Address and phone are shown from existing frontend contact constants because `restaurant/profile` does not expose public branch address/phone.
- `Find near me` can request browser location after user action, but cannot choose a true nearest branch until branch coordinates are exposed.
- Existing generated API artifacts and customer generated SDK files remain dirty from prior workspace changes; confirm they came from the canonical artifact refresh chain before release.
- Existing menu/booking/reservation pages still contain older copy patterns and should be normalized in later smaller passes.

## Next Tasks

- Add customer-facing branch list/detail backend contract when ready, including address, phone, timezone, open status, and optional coordinates.
- Normalize remaining menu, booking, reservation, waiting-list, and account page copy using the new customer UI foundation.
- Wire selected branch into future menu/reservation/waiting-list/preorder adapters only when those contracts clearly support branch scoping.
- Add visual browser smoke coverage for the new homepage and branch selector if this batch is promoted toward release.

## Task 2 Audit - Core Customer Flows

### Current Flow State

- Menu catalog is live through `GET /api/v1/menu/categories`, `GET /api/v1/menu/items`, and `GET /api/v1/menu/items/{id}`. The generated menu item contract supports category, search, preorder-only, availability, image, description, price, and preorder policy. It does not expose branch id filtering, ingredients, allergens, size/options/toppings, best-seller/new/vegetarian/vegan/spicy/promotion flags, related dishes, or popularity sort.
- Menu list and item detail pages exist under `/menu` and `/menu/[id]`, but the list needs URL-backed filters, local sort/filter polish, branch-context display, and a cart entry instead of only preorder preview.
- Preorder APIs are contract-visible and gated by `NEXT_PUBLIC_FEATURE_PREORDER`: menu preorder preview plus reservation preorder show/preview/replace/clear. Request bodies only support `{ item_id, quantity }`; item notes, toppings/options, and standalone cart checkout are not available.
- Reservation booking has table availability, table holds, reservation create, reservation detail, list, cancel, reschedule, deposit, bill, preorder, and benefits panels. Branch ids are supported in table availability and reservation create contracts, but the current reservation create adapter does not yet include the selected branch id when creating without a held table.
- Booking management exists for list/detail/cancel/reschedule and includes row-versioned mutations. Cancellation needs an explicit confirm dialog in the customer UI.
- Waiting list routes are contract-visible but Wave 2 env-gated. Create/list/detail/accept/confirm-arrival/decline/cancel are present. The create contract supports `branch_id`, contact, party size, priority, and notes; it does not expose queue position or estimated wait time.

### Task 2 Support Matrix

| Surface | Status | Evidence | Required Headers | Frontend Decision |
|---|---|---|---|---|
| Menu browse/detail | stable/live | generated SDK menu routes | none | Improve live UI using only exposed filters and truthful disabled states for unsupported filters. |
| Branch-aware menu | blocked/unavailable | menu query lacks `branch_id` | none | Show selected branch context, but do not send branch filter until API exists. |
| Local preorder/cart | scaffolded/flagged | cart can persist client-side; submit needs reservation preorder APIs | `X-Session-Id` for session key, `Idempotency-Key` for submit mutations | Add session+branch scoped cart and attach to reservation/preorder surfaces where supported. |
| Reservation create/detail/manage | stable/live | generated SDK and existing adapters | `X-Customer-Token`, `X-Session-Id`, `Idempotency-Key` | Add selected branch propagation, clearer UX, and confirmation for destructive actions. |
| Waiting list | scaffolded/flagged | generated SDK and support matrix Wave 2 | `X-Customer-Token`, `Idempotency-Key` | Keep behind rollout flag, add selected branch propagation and truthful no-estimate/position states. |

### Task 2 API Gaps

- Menu branch filtering.
- Menu dietary/spicy/promotion/popularity metadata.
- Menu item ingredients, allergens, options/toppings, related dishes, and customer personalization.
- Standalone preorder/cart submit not attached to a reservation.
- Item notes/options/toppings in preorder submit contracts.
- Dedicated serve timing field for preorder; only reservation/order notes are available.
- Reservation occasion and dining preferences as structured fields.
- Waiting-list estimated wait time and queue position.
- Customer booking lookup by reservation code without an authenticated/session owner context.

### Task 2 Implementation Sequence

1. Update menu list/detail to use selected branch context, URL-backed supported filters, debounced search, local availability/sort, unsupported-filter disabled states, and session+branch cart add/update/remove.
2. Add a local preorder cart store scoped by `X-Session-Id` and selected branch, including item notes and serve timing as frontend state with documented API gaps.
3. Propagate selected branch id into reservation create and waiting-list create payloads.
4. Add destructive confirmation for reservation cancellation and waiting-list cancellation.
5. Update documentation and run customer-web lint, typecheck, and build.

### Task 2 Completed Work

- Rebuilt `/menu` browsing around the Task 1 customer UI wrappers.
- Added URL-backed menu search/category/available/preorder/sort state with debounced search.
- Added truthful disabled UI for unsupported menu filters: best seller, new, vegetarian, vegan, spicy, and promotion.
- Added branch context to menu browsing without sending unsupported `branch_id` to the menu API.
- Added session + branch scoped local preorder cart persistence.
- Added cart item quantity update, remove, local item notes, serve timing, subtotal, clear confirmation, and attach-to-reservation CTA.
- Added dish detail quantity/note add-to-cart UI and truthful integration states for ingredients, allergens, options/toppings, and preorder validation.
- Propagated selected branch id into table availability, table hold handoff, reservation create, and waiting-list create form state.
- Hydrated reservation create preorder items from the local session cart when preorder rollout is enabled, then cleared that local cart after successful reservation creation.
- Added confirmation dialogs before reservation cancellation and waiting-list cancellation.
- Updated contract governance so the menu preorder gate checks the new cart-based `featureFlags.preorder` guard.

### Task 2 Changed Files

- `docs/implementation/customer-web-ux-master-plan.md`
- `customer-web/scripts/check-contract-governance.mjs`
- `customer-web/src/app/(public)/menu/page.tsx`
- `customer-web/src/features/menu/menu-page.tsx`
- `customer-web/src/features/menu/menu-detail-page.tsx`
- `customer-web/src/features/preorder/cart-panel.tsx`
- `customer-web/src/features/preorder/local-cart.ts`
- `customer-web/src/features/preorder/local-cart.test.ts`
- `customer-web/src/features/reservations/api.ts`
- `customer-web/src/features/reservations/schemas.ts`
- `customer-web/src/features/reservations/reservation-create-page.tsx`
- `customer-web/src/features/reservations/reservation-detail-page.tsx`
- `customer-web/src/features/table-booking/table-booking-page.tsx`
- `customer-web/src/features/waiting-list/schemas.ts`
- `customer-web/src/features/waiting-list/waiting-list-page.tsx`

### Task 2 APIs Used

- `GET /api/v1/menu/categories`
- `GET /api/v1/menu/items`
- `GET /api/v1/menu/items/{id}`
- `POST /api/v1/menu/preorder/preview` through existing reservation preorder preview paths, not as standalone cart submit.
- `GET /api/v1/tables/available`
- `POST /api/v1/table-holds`
- `POST /api/v1/reservations`
- `GET /api/v1/reservations`
- `GET /api/v1/reservations/{id}`
- `POST /api/v1/reservations/{id}/cancel`
- `POST /api/v1/reservations/{id}/reschedule`
- `GET /api/v1/reservations/{id}/preorder`
- `POST /api/v1/reservations/{id}/preorder/preview`
- `PUT /api/v1/reservations/{id}/preorder`
- `DELETE /api/v1/reservations/{id}/preorder`
- `GET /api/v1/waiting-list`
- `POST /api/v1/waiting-list`
- `GET /api/v1/waiting-list/{id}`
- Waiting-list accept, confirm-arrival, decline, and cancel owner actions.

### Task 2 Remaining Gaps

- Menu branch filtering remains blocked because `GET /api/v1/menu/items` has no `branch_id` query parameter.
- Menu best-seller/new/dietary/spicy/promotion filters and popularity sort remain blocked because menu item metadata does not expose those fields.
- Dish ingredients, allergens, options, toppings, related dishes, and personalization remain blocked by missing menu/customer preference APIs.
- Cart item notes and serve timing are stored locally only; the preorder submit contracts only accept item id and quantity.
- Standalone preorder submit without a reservation remains unavailable.
- Structured reservation occasion, child/high-chair, quiet area, and private room fields are missing; notes remain the only available flexible field.
- Waiting-list estimated wait time and queue position are not exposed by the customer waiting-list contract.

### Task 2 Verification

- `npm --prefix customer-web run lint` passed.
- `npm --prefix customer-web run typecheck` passed.
- `npm --prefix customer-web run test -- src/features/preorder/local-cart.test.ts` passed: 1 file, 3 tests.
- `npm --prefix customer-web run build` passed.
- Build warning remains: generated contract artifacts already have local Git changes from the existing workspace. This batch did not hand-edit generated SDK artifacts.

## Task 3 Audit - Personalization, Payments, Privacy, PWA, Final QA

### Current Support State

- Voucher and loyalty reads are contract-visible through `GET /api/v1/me/vouchers` and `GET /api/v1/me/loyalty`, gated by `NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS`.
- Reservation-level voucher apply/remove and loyalty redeem/release are contract-visible and already use row version plus idempotency helpers.
- Deposit and bill payment sessions are contract-visible and session-bound. Existing frontend code stores only provider session ids in browser session storage and does not store payment-sensitive data.
- Active order reads are available through `GET /api/v1/reservations/{reservation_id}/active-order`; the payload is loose but includes order status and item status when an active order exists.
- Privacy requests and data export are contract-visible and gated by `NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS` and `NEXT_PUBLIC_FEATURE_DATA_EXPORT`.
- Profile editing, customer dining preferences, notification preferences, feedback/review submission, public review publishing, favorites/recent dishes, voucher apply to standalone cart, and push notification subscription APIs are not visible in the customer self-service route surface.
- Notification infrastructure exists backend-side for Email real delivery and SMS/Zalo stub/provider-ready delivery, but there is no customer-web preference endpoint or push/PWA notification endpoint.
- The root layout already has production metadata, theme color, and Apple web app metadata. There is no manifest route yet.
- There is no analytics implementation in customer-web. A typed no-op event layer is the safest integration point until a real sink is selected.

### Task 3 Support Matrix

| Surface | Status | Evidence | Frontend Decision |
|---|---|---|---|
| Voucher wallet | rollout-gated/live-conditional | `GET /api/v1/me/vouchers` | Keep gated, polish wallet list/detail states, near-expiry, minimum spend, expired, unavailable, and reservation apply guidance. |
| Reservation voucher apply/remove | rollout-gated/live-conditional | reservation benefits routes | Keep existing idempotent mutations and improve customer copy only. |
| Loyalty summary | rollout-gated/live-conditional | `GET /api/v1/me/loyalty` | Keep gated, show points, tier, next tier, benefits integration state, and history when returned. |
| Deposit payment UX | live-conditional | deposit preview/session routes | Improve payment status, failure/retry, next action, and amount breakdown without changing backend behavior. |
| Bill payment UX | live-conditional | active-order, bill-preview, bill, payment-session routes | Add order tracking from active-order payload and clearer bill/payment breakdown states. |
| Feedback/review | blocked/unavailable | no customer self-service route | Add local UI-ready integration state only; do not submit or publish data. |
| Profile/preferences | partial frontend-only | auth profile + loyalty user fields only | Display known customer info; keep dining preferences local integration state and document missing persistence API. |
| Privacy center | rollout-gated/live-conditional | data export and privacy request routes | Polish copy and keep anonymization request idempotent; do not hard-delete finance/audit data. |
| Notifications | blocked/unavailable for customer-web prefs/push | backend notification preference service exists, no customer route | Add frontend notification preference integration state and document missing API. |
| PWA | frontend-only | Next app metadata exists | Add manifest route and offline-friendly metadata only; no unsafe service worker cache. |
| Analytics | no infrastructure | no existing analytics/telemetry code | Add typed no-op event layer and call it from touched customer flows. |

### Task 3 Missing APIs

- Customer profile read/update endpoint scoped to customer token.
- Customer dining preference read/update endpoint.
- Customer notification preference read/update endpoint.
- Push subscription registration and delivery endpoints.
- Feedback/review create endpoint with reservation/order ownership checks.
- Feedback reward/voucher-after-feedback endpoint.
- Favorite dishes and recent dishes endpoints.
- Voucher application endpoint for standalone local cart/preorder.
- Dedicated order tracking ETA, item progress, and call-staff/help endpoints.
- Receipt/invoice download link in customer payment-session or bill payload.

### Task 3 Implementation Sequence

1. Normalize touched account, voucher, loyalty, privacy, deposit, bill, and payment copy to customer-facing Vietnamese.
2. Add typed frontend-only integration state for profile preferences, notifications, feedback, and analytics where APIs are missing.
3. Add active-order tracking UI using the existing active-order payload and stop polling once order status is terminal.
4. Improve payment session UX with pending/success/failure/retry states and amount/reference breakdowns without storing payment-sensitive data.
5. Add a manifest route and conservative PWA metadata without adding unsafe offline caching.
6. Run customer-web lint, typecheck, focused tests, and build; then update final QA status.

### Task 3 Completed Work

- Added a typed no-op customer analytics event layer and wired it into touched CTA/payment/voucher flows without introducing a third-party sink.
- Improved voucher wallet state parsing for expired, unavailable, minimum spend, branch/menu restriction, and near-expiry states.
- Updated reservation benefits apply flow to emit the typed voucher analytics event while preserving existing idempotent API behavior.
- Rebuilt account/customer area around known identity, loyalty, vouchers, payment/deposit/billing, privacy, notification, preference, and feedback panels.
- Added frontend-only profile/preference, notification preference, and feedback panels as integration states because customer-scoped persistence APIs are not exposed yet.
- Improved privacy center copy and tests while preserving existing data export/anonymization request behavior and data-lifecycle retention constraints.
- Added loyalty/voucher surfaces to account/homepage only through the existing gated account-benefits API.
- Added active order tracking parsing and UI from the existing reservation active-order payload, including terminal-state polling stop behavior.
- Improved deposit and bill payment UX with payment pending/success/failure/retry copy, safe provider links, amount/reference metadata, and explicit non-storage of payment-sensitive data.
- Added reusable payment breakdown rendering for deposit and bill totals using only fields returned by the API.
- Added a Next manifest route and metadata updates for install-friendly customer-web behavior without adding unsafe service-worker caching.
- Added or updated focused tests for order tracking, voucher state, account composition, privacy, deposit payment sessions, and bill payment sessions.

### Task 3 Changed Files

- `docs/implementation/customer-web-ux-master-plan.md`
- `customer-web/src/app/layout.tsx`
- `customer-web/src/app/manifest.ts`
- `customer-web/src/components/customer/ui.tsx`
- `customer-web/src/features/account/account-page.tsx`
- `customer-web/src/features/account/account-page.test.tsx`
- `customer-web/src/features/account/profile-preferences-panel.tsx`
- `customer-web/src/features/billing/billing-panel.tsx`
- `customer-web/src/features/billing/billing-panel.test.tsx`
- `customer-web/src/features/deposit/deposit-panel.tsx`
- `customer-web/src/features/deposit/deposit-panel.test.tsx`
- `customer-web/src/features/feedback/feedback-panel.tsx`
- `customer-web/src/features/home/home-page.tsx`
- `customer-web/src/features/notifications/notification-preferences-panel.tsx`
- `customer-web/src/features/orders/order-tracking.ts`
- `customer-web/src/features/orders/order-tracking-card.tsx`
- `customer-web/src/features/orders/order-tracking.test.ts`
- `customer-web/src/features/payments/payment-breakdown.tsx`
- `customer-web/src/features/payments/payment-session-card.tsx`
- `customer-web/src/features/privacy/privacy-panel.tsx`
- `customer-web/src/features/privacy/privacy-panel.test.tsx`
- `customer-web/src/features/vouchers/benefits-panel.tsx`
- `customer-web/src/features/vouchers/state.ts`
- `customer-web/src/features/vouchers/state.test.ts`
- `customer-web/src/lib/analytics/events.ts`
- `customer-web/src/lib/contracts/format.ts`

### Task 3 APIs Used

- `GET /api/v1/me/vouchers`
- `GET /api/v1/me/loyalty`
- Reservation benefits apply/remove and loyalty redeem/release routes already present behind the account-benefits rollout gate.
- `GET /api/v1/reservations/{reservation_id}/active-order`
- Reservation deposit preview, intent, payment-session show/create/refresh/confirm routes.
- Reservation bill preview/detail/payment-session show/create/refresh/confirm routes.
- Customer privacy request and data export routes behind privacy/data-export rollout gates.

### Task 3 Incomplete Features

- Profile editing and dining preferences are UI-ready only. No customer-scoped read/update endpoints are exposed.
- Notification preferences are UI-ready only. Backend notification infrastructure exists, but customer-web preference and push subscription routes are not exposed.
- Feedback/review is UI-ready only. No customer-owned feedback submission endpoint is exposed.
- Voucher application to standalone local cart/preorder is unavailable. Existing live apply/remove support remains reservation-scoped.
- Order tracking uses the active-order payload only. ETA, item-level progress detail, and call-staff/help actions are not exposed.
- PWA support is limited to manifest/metadata. No service worker cache was added because cache invalidation and offline data safety are not defined yet.
- Analytics is typed no-op only until a product analytics sink is selected.

### Task 3 Missing Backend APIs

- Customer profile read/update endpoint scoped to `X-Customer-Token`.
- Customer dining preference read/update endpoint.
- Customer notification preference read/update endpoint.
- Push subscription registration and push delivery endpoints.
- Feedback/review create endpoint with reservation/order ownership checks.
- Feedback reward/voucher-after-feedback endpoint.
- Favorite dishes and recent dishes endpoints.
- Voucher application endpoint for standalone cart/preorder.
- Dedicated order tracking ETA, item progress, and call-staff/help endpoints.
- Receipt/invoice customer links in customer payment-session or bill payload.

### Task 3 Verification

- `npm --prefix customer-web run test -- src/features/deposit/deposit-panel.test.tsx src/features/billing/billing-panel.test.tsx` passed: 2 files, 14 tests.
- `npm --prefix customer-web run test -- src/features/orders/order-tracking.test.ts src/features/vouchers/state.test.ts src/features/account/account-page.test.tsx src/features/privacy/privacy-panel.test.tsx` passed: 4 files, 15 tests.
- `npm --prefix customer-web run lint` passed.
- `npm --prefix customer-web run typecheck` passed.
- `npm --prefix customer-web run build` passed. Contract governance check passed.

### Task 3 Failed Commands And Warnings

- Final verification has no failed commands.
- Earlier focused deposit/billing tests failed because `Payment received` became valid in multiple places after the payment card rebuild; the tests were updated to assert presence without requiring a single match.
- Build warning remains: generated contract artifacts already have local Git changes in this workspace. This batch did not hand-edit generated artifacts. Confirm they came from the canonical backend artifact refresh plus customer-web `sync:contracts` chain before release.

### Task 3 Risks

- Account, notification, feedback, and preference panels must remain clearly integration-state only until backend ownership and persistence APIs exist.
- Payment provider links are rendered only when safe `http` or `https` URLs are present in provider payloads; the backend still needs to expose receipt/invoice customer links for those actions to become useful.
- The active-order payload is loose, so order tracking intentionally tolerates missing fields and should be revisited if a stricter contract is added.
- The repo contains many unrelated local changes. Avoid broad formatting or generated artifact edits in the next PR.
- Existing generated API artifacts are dirty and should be reconciled before release.

### Task 3 Next PR Recommendations

- Add customer profile/preference and notification preference APIs with ownership checks, then replace frontend-only panels with live adapters.
- Add a customer feedback API scoped by reservation/order ownership and decide whether feedback is private only or publishable review content.
- Extend payment-session/bill payloads with receipt and invoice customer links if the backend can expose them safely.
- Add structured order tracking contract fields for ETA, item progress, and help CTA support.
- Decide the analytics sink and replace the no-op layer without changing call sites.
- Reconcile generated contract artifacts through the canonical refresh chain.

## Customer-Web Vietnamese UI Policy Pass

### Completed Work

- Added `customer-web/AGENTS.md` so future customer-web interface work defaults to Vietnamese for visible customer copy: headings, buttons, form labels, statuses, empty/loading/error states, toasts, metadata, and manifest.
- Preserved English only for identifiers, route names, enum/contract keys, generated artifacts, technical diagnostics, and backend-entered restaurant data.
- Normalized touched shell, footer, auth/session, branch, menu, cart, account, voucher, loyalty, privacy, deposit, bill, payment, order-tracking, notification, feedback, metadata, manifest, and dev-mock customer-facing copy to Vietnamese.
- Updated customer-web tests that asserted old English UI labels.
- Translated rollout `frontendDecision` text in `support-matrix.ts` because it can become customer-visible through rollout decision descriptions.

### Changed Files In This Pass

- `customer-web/AGENTS.md`
- `customer-web/src/app/layout.tsx`
- `customer-web/src/app/manifest.ts`
- `customer-web/src/app/(public)/page.tsx`
- `customer-web/src/app/(public)/menu/page.tsx`
- `customer-web/src/components/customer/layout.tsx`
- `customer-web/src/components/customer/ui.tsx`
- `customer-web/src/components/layout/app-shell.tsx`
- `customer-web/src/components/layout/app-shell.test.tsx`
- `customer-web/src/components/layout/protected-route.tsx`
- `customer-web/src/components/layout/protected-route.test.tsx`
- `customer-web/src/components/layout/public-footer.tsx`
- `customer-web/src/components/layout/public-footer.test.tsx`
- `customer-web/src/features/account/account-page.tsx`
- `customer-web/src/features/account/account-page.test.tsx`
- `customer-web/src/features/account/profile-preferences-panel.tsx`
- `customer-web/src/features/billing/billing-panel.tsx`
- `customer-web/src/features/billing/billing-panel.test.tsx`
- `customer-web/src/features/branch/branch-selector.tsx`
- `customer-web/src/features/branch/hooks.ts`
- `customer-web/src/features/branch/state.ts`
- `customer-web/src/features/branch/state.test.ts`
- `customer-web/src/features/deposit/deposit-panel.tsx`
- `customer-web/src/features/deposit/deposit-panel.test.tsx`
- `customer-web/src/features/feedback/feedback-panel.tsx`
- `customer-web/src/features/home/home-page.tsx`
- `customer-web/src/features/menu/menu-page.tsx`
- `customer-web/src/features/menu/menu-page.test.tsx`
- `customer-web/src/features/menu/menu-detail-page.tsx`
- `customer-web/src/features/notifications/notification-preferences-panel.tsx`
- `customer-web/src/features/orders/order-tracking.ts`
- `customer-web/src/features/orders/order-tracking-card.tsx`
- `customer-web/src/features/payments/payment-session-card.tsx`
- `customer-web/src/features/preorder/cart-panel.tsx`
- `customer-web/src/features/privacy/privacy-panel.tsx`
- `customer-web/src/features/privacy/privacy-panel.test.tsx`
- `customer-web/src/features/restaurant/state.ts`
- `customer-web/src/features/vouchers/benefits-panel.tsx`
- `customer-web/src/features/vouchers/state.ts`
- `customer-web/src/features/vouchers/state.test.ts`
- `customer-web/src/features/reservations/reservation-create-page.tsx`
- `customer-web/src/features/reservations/reservation-detail-page.tsx`
- `customer-web/src/features/waiting-list/waiting-list-page.tsx`
- `customer-web/src/lib/api/errors.ts`
- `customer-web/src/lib/api/mock-fetch.ts`
- `customer-web/src/lib/auth/route-access.ts`
- `customer-web/src/lib/auth/session.ts`
- `customer-web/src/lib/config/support-matrix.ts`
- `customer-web/src/lib/config/support-matrix.test.ts`
- `customer-web/src/lib/contracts/format.ts`

### Verification

- `npm --prefix customer-web run typecheck` passed during the repair/translation pass.
- `npm --prefix customer-web run test -- src/components/layout/app-shell.test.tsx src/components/layout/protected-route.test.tsx src/components/layout/public-footer.test.tsx src/features/branch/state.test.ts src/features/account/account-page.test.tsx src/features/privacy/privacy-panel.test.tsx src/features/vouchers/state.test.ts src/features/deposit/deposit-panel.test.tsx src/features/billing/billing-panel.test.tsx src/features/orders/order-tracking.test.ts src/features/menu/menu-page.test.tsx src/features/menu/menu-detail-page.test.tsx src/features/preorder/local-cart.test.ts src/lib/config/support-matrix.test.ts` passed: 13 files, 54 tests.
- `npm --prefix customer-web run test -- src/lib/config/support-matrix.test.ts` passed after translating rollout decision text: 1 file, 6 tests.
- `npm --prefix customer-web run lint` passed.
- `npm --prefix customer-web run typecheck` passed.
- `npm --prefix customer-web run build` passed. Contract governance check passed.

### Remaining Risks

- Some source strings remain intentionally English because they are contract keys, generated/route evidence, backend status values, mock email addresses, or lookup keys used to translate backend-entered sample data.
- Build warning remains: generated contract artifacts already have local Git changes in this workspace. This pass did not hand-edit generated SDK artifacts.

### Task 3 Manual QA Checklist

- [ ] Guest opens homepage.
- [ ] Guest selects branch.
- [ ] Guest browses menu.
- [ ] Guest searches dish.
- [ ] Guest views dish detail.
- [ ] Guest adds dish to cart.
- [ ] Guest starts reservation.
- [ ] Guest confirms reservation.
- [ ] Customer views booking detail.
- [ ] Customer cancels booking with confirmation.
- [ ] Customer joins waiting list.
- [ ] Customer views waiting status.
- [ ] Customer applies voucher.
- [ ] Customer sees payment pending/success/failure states.
- [ ] Customer views loyalty/profile page.
- [ ] Customer edits preferences if supported.
- [ ] Customer submits feedback.
- [ ] Mobile layout works.
- [ ] Empty states work.
- [ ] API error states work.
- [ ] Loading states work.
- [ ] Keyboard navigation works.
- [x] No TypeScript errors.
- [x] No lint errors.
- [x] Production build succeeds.
