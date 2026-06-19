# ADR: Conflict Resolution Protocol

## Context
Due to concurrent operations (Realtime Presence ADR) and offline syncs (Offline Operation Capture ADR), the staff web client will inevitably face situations where its local state is stale compared to the server's state, leading to mutation conflicts.

## Decision
1. **No Automatic Financial Merges**: The system will NEVER automatically merge conflicting financial data (Bills, Payments, Refunds, Settlements, Inventory quantities, Prices, Discounts, Taxes).
2. **Conflict Response Standard**: The API will return a standardized 409 Conflict envelope containing the current server state (`latest_version`, `current_state`).
3. **UI Protocol**:
   - The mutation is halted.
   - The UI presents a clear conflict resolution dialog.
   - The user is informed of the conflict (e.g., "The order has been updated by another staff member").
   - The user is given options to:
     - Review the latest server state.
     - Discard local changes and reload.
     - (In safe contexts) Retry their action on top of the new state.
4. **Idempotency Protection**: All financial and high-risk mutations must use an `Idempotency-Key` to prevent double-charging or duplicate entries during retries or syncs.

## Consequences
- Requires standardizing the 409 conflict payload across all Laravel modules.
- Ensures that operators are always aware of state changes, preventing silent data loss or duplication.
