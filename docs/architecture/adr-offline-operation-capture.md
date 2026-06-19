# ADR: Offline Operation Capture

## Context
Network instability in restaurant environments is common. We need to allow staff to continue capturing operational data (like order drafts) when offline to prevent service disruption, but we cannot compromise financial integrity or inventory accuracy by allowing destructive or complex operations to finalize without server confirmation.

## Decision
1. **Bounded Offline Capabilities**: Offline mode is ONLY permitted for safe, recoverable actions, strictly limited to:
   - Creating order drafts.
   - Modifying order drafts that haven't been sent to the kitchen.
   - Adding notes to orders/items.
   - UI navigation and state (filters, sorting).
   - Queuing commands for server confirmation.
2. **Strictly Prohibited Offline Operations**: We explicitly ban offline completion for:
   - Payments & Refunds.
   - Cashier shift close & Bill settlement.
   - Inventory adjustments & receiving.
   - Kitchen dispatch confirmation.
3. **Queue Architecture**: Permitted offline operations will be stored in an IndexedDB queue. Each operation includes:
   - `client_operation_id`
   - `command_type`
   - `aggregate_id`
   - `base_row_version`
   - `payload`
   - `status` (Draft, Pending Sync, Syncing, Conflict, Rejected, Confirmed)
4. **Explicit UI State**: The application must clearly display connection status (Online, Unstable, Offline, Syncing, Sync Failed) using both icons and text.

## Consequences
- Requires a reliable IndexedDB abstraction with schema versioning.
- Prevents financial drift by treating the server as the ultimate source of truth.
