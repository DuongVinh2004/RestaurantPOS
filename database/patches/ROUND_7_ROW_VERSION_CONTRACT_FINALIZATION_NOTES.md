# Round 7 — row_version contract finalization

Scope:
- require `row_version` on remaining staff mutation request classes that still accepted nullable values
- add a static row-version contract snapshot for deploy/ops checks
- fail release checks when any targeted staff mutation request stops requiring `row_version`

This round intentionally adds **no new SQL migration**. It closes the optimistic-locking contract at the HTTP/request boundary and adds regression detection so future changes do not silently re-open stale-write paths.
