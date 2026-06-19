# ADR: Staff Web Command Architecture

## Context
RestaurantPOS staff operations require high speed, typically performed by power users. Relying solely on point-and-click UI slows down operations such as opening tables, adding items, or navigating between domains (e.g., KDS, Checkout). An omni Command Palette (accessible via `Ctrl+K` or `Cmd+K`) is needed to support keyboard-first workflows.

## Decision
1. **Command Palette Foundation**: We will build a global Command Palette interface.
2. **Intent Parsing**: A local `CommandParser` will tokenize user input into actionable intents (e.g., `/move-table 5 12` -> `action: move_table, args: [5, 12]`).
3. **Execution Safety**: The client only parses the intent. It does NOT bypass API rules. The execution must hit the same authenticated, RBAC-protected API endpoints as the GUI.
4. **Destructive Commands**: Any command that alters state or handles financial data must display a clear confirmation prompt before execution.
5. **No Unauthorized Context**: The Command Palette will only suggest commands and search results that the current staff session is authorized to access.
6. **Local Search & Remote Debounce**: Local commands will be indexed for <50ms p95 response times. Remote searches (like finding a specific receipt) will use debounce, cancellation, and loading states.

## Consequences
- Requires 100% branch coverage for the `CommandParser` and `ArgumentValidator`.
- All operations must be modeled cleanly in the UI layer so they can be invoked both by buttons and by the Command Palette.
- Enhances operator speed and accessibility.
