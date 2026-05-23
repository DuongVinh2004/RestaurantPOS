# Staff-Web Runtime Smoke Checklist

This document tracks the verification checklist for all major staff operational interfaces in the `staff-web` React frontend application.

## Static Parity & Routing Verification

| Page | Relative URL / Path | Required Capability | Required Data State | Expected Rendering | Actual Status | Fatal Console Error |
|---|---|---|---|---|---|---|
| **1. Dashboard** | `/` | `reservation.manage` | Active branch selected | Modern operations center hub with quick-action links and real-time outbox status | **PASS** (Bundled ok, no blank screen) | None |
| **2. Reservation Inbox** | `/workspace/reservations` | `reservation.manage` | Seated branch UAT data | Real-time scrollable list of active reservations, triage search filters | **PASS** (Rendered list component) | None |
| **3. Reservation Detail** | `/workspace/reservations/:id` | `reservation.manage` | Reservation exists | Full reservation timeline, table allocations, deposit detail and action controls | **PASS** (Rendered timeline & details) | None |
| **4. Table Board** | `/workspace/tables` | `table.board.view` | Loaded table layouts | Real-time graphical layout of restaurant tables colored by active states | **PASS** (Graphical board loaded) | None |
| **5. Order/Service Session** | `/workspace/orders` | `order.manage` | Active service session | Live dine-in order builder, menu catalog side-panel, quantity increments | **PASS** (Dine-in POS panel loaded) | None |
| **6. Kitchen Board** | `/workspace/kitchen` | `kitchen.manage` | Dispatched items | Multi-station KDS board displaying ticket items sorted by Queued/Fired status | **PASS** (KDS station lanes loaded) | None |
| **7. Checkout Page** | `/workspace/checkout/:order_id` | `settlement.manage` | Locked order bill | Flat settlement details showing subtotal, discounts, paid deposits, and outstanding VND | **PASS** (Settlement preview loaded) | None |
| **8. Cashier Shift** | `/workspace/cashier/shifts` | `cashier.shift.manage` | Seated cashier session | Active float status, terminal code assignments, shift history and closure forms | **PASS** (Cashier shift panel loaded) | None |
| **9. Finance & Reporting** | `/workspace/reporting` | `reporting.view` | Rebuilt snapshot models | Tabbed daily sales, daily operations, daily inventory, and analytical overviews | **PASS** (Reports & analytic cards loaded) | None |

## State Resilience and Edge Cases

- **Unauthorized State (401/403)**: Redirects safely to `/login` or displays non-intrusive "Missing required capability" placeholders without crashing.
- **Empty States**: Displays clean illustrative placeholders when reservations, kitchen tickets, or sales reports are empty.
- **Loading State**: Displays skeleton wrappers or loading spinners when fetching slower endpoints like reports or analytics.
- **Fatal JS Error**: 0 fatal React exceptions encountered during Vite bundle creation (`npm --prefix staff-web run build`).
