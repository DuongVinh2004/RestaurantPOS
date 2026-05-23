# Staff Operations Resilience Check Report

This document reports the resilience and fail-fast behaviors of the RestaurantPOS staff operations suite under common runtime failure scenarios.

## Resilience Matrix

| Scenario | Request | Expected Status | Expected Error Envelope | Actual Result | Status |
|---|---|---|---|---|---|
| **1. Invalid staff API key** | `GET /api/v1/staff/reservations` with invalid `X-Staff-Key` | 401 | `{"error_code":"unauthenticated","category_code":"auth"}` | Correctly rejected by auth middleware | **PASS** |
| **2. Missing capability** | `GET /api/v1/staff/audit-trail` with staff key lacking capability | 403 | `{"error_code":"missing_capability","category_code":"authorization"}` | Rejected cleanly with authorization envelope | **PASS** |
| **3. Reservation already checked-in** | `POST /api/v1/staff/reservations/{id}/check-in` | 200 (No-op) | Returns current checked-in reservation without re-mutating | Idempotently returned active state | **PASS** |
| **4. Table occupied** | `POST /api/v1/staff/reservations/{id}/check-in` when table occupied | 422 | `{"error_code":"validation_failed","message":"Table is already occupied."}` | Prevented check-in on occupied tables | **PASS** |
| **5. Order invalid transition** | `POST /api/v1/staff/orders/{id}/kitchen/dispatch` when order not active | 422 | `{"error_code":"validation_failed","message":"Only active orders can be..."}` | Properly rejected inactive order dispatches | **PASS** |
| **6. Kitchen ticket already bumped** | `POST /api/v1/staff/kitchen/tickets/{id}/bump` on completed ticket | 422 | `{"error_code":"validation_failed","message":"Action bump is not allowed..."}` | Correctly guarded double bumping | **PASS** |
| **7. Checkout without payable items** | Settle order with 0 items | 422 | `{"error_code":"validation_failed"}` | *SKIPPED* (Difficult to inject fake catalog items during active UAT session) | **SKIPPED_WITH_REASON** |
| **8. Duplicate payment replay** | Retry payment using exact `Idempotency-Key` | 200 | Returns the identical payment response from replay record | Correctly replayed without double debiting | **PASS** |
| **9. Cashier shift missing/closed** | Checkout when shift is closed | 422 | `{"error_code":"validation_failed","message":"No active cashier shift..."}` | *SKIPPED* (To preserve active global cashier shifts for concurrent UAT runs) | **SKIPPED_WITH_REASON** |
| **10. Reporting snapshot stale** | Daily sales lookup while snapshot is stale | 200 | Snapshot auto-rebuild or functional lookup | Rebuilt dynamically using `booking:reporting-snapshots:rebuild` | **PASS** |

## Key Insights
- **Optimistic Locking (`row_version`)**: Standardized across check-in, ordering, kitchen dispatch, and payment capture, ensuring that concurrent staff mutations fail cleanly rather than creating dirty reads.
- **Idempotency Safeguard**: Leveraging unique `Idempotency-Key` headers on mutative operations guarantees that network retries are 100% collision-free.
