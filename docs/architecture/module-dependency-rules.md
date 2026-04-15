# Module Dependency Rules

## Allowed directions

- `Modules -> Platform`: allowed for feature flags, metrics, release artifacts, verification helpers, and other cross-cutting infrastructure.
- `HTTP -> Application -> Domain`: preferred direction inside each module.
- `Module -> other module Application/Domain`: allowed when the dependency is part of a real workflow boundary and there is a clear owning module.

## Current accepted cross-module dependencies

- `WaitingList` may call into `BranchScheduling`, `Reservations`, `FloorOps`, `Notifications`, `Reporting`, and `Platform`.
- `Conversations` may read `Reservations`, `WaitingList`, `Notifications`, `FloorOps`, and `Platform`.
- `Notifications` may read source-domain records needed for delivery context, but it does not own the source workflow.
- `PrivacyAudit` may read across transactional modules and use `FloorOps` branch scope helpers plus `Platform` audit plumbing.
- `Reporting` may read `CheckoutPayments`, `Reservations`, `WaitingList`, inventory tables, and `Platform`.
- `AdminMasterDataBulk` may call true owner services in `BranchScheduling`, `BenefitsLoyalty`, menu/admin services, and table admin services. It does not become the owner of those domains.

## Disallowed directions

- Do not move business ownership back into `app/Services/` or `app/Support/`.
- Do not let `AdminMasterDataBulk` own menu, voucher, branch, table, or loyalty domain rules directly.
- Do not let `Notifications` absorb source business workflow logic.
- Do not let `Reporting` write transactional state as part of read-model queries.
- Do not let `PrivacyAudit` become the canonical owner of every source entity it reads.

## Coupling guardrails

- Prefer importing canonical module namespaces instead of the legacy shim namespaces when editing module code.
- Prefer application services or narrow domain models over deep reach-through into another module's internals.
- If a wrapper remains under `app/Services/` or `app/Http/`, treat it as compatibility surface only.
