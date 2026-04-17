# RestaurantPOS PlantUML Bundle v9

This bundle is aligned to the current `RestaurantPOS-Laravel` repo snapshot and uses the canonical folder name `restaurantpos_plantuml`.

Inventory:
- 28 use case diagrams (`UC-01..23` + `UCD-00..04`)
- 32 activity diagrams (`AD-01..32`)
- 24 sequence diagrams (`SD-01..24`)
- 13 class diagrams (`CD-00..12`)
- 13 ERD diagrams (`ERD-00..12`)

v9 hardening focus:
- sync `README.md`, `manifest.txt`, `coverage_report.md`, and `audit_report.md` to the real inventory
- add dedicated cashier-shift behavioral coverage with `UC-23`, `AD-32`, and `SD-24`
- rewrite stale sequence diagrams to actual controller and service names in auth, waiting list, floor ops, kitchen, payments, notifications, and ops
- fix notification-only structural slice `CD-12` and `ERD-12` to the SQL-first schema (`outbox_id`, `attempt_id`, `provider_key`, `status`, `next_retry_at`, quiet-hour columns)
- keep route labels preview-safe with `:param`
- keep use case, activity, and ERD files free from one-line `skinparam ... { ... }` blocks
- keep ERD relationships on supported crow-foot tokens only

Compatibility notes:
- class diagrams remain hybrid domain + database diagrams; they are not reverse-engineered OO class models
- sequence diagrams are repo-bound at route, controller, and service level; they do not claim every internal method call
- ERD diagrams stay domain-sliced instead of one all-table physical ERD
