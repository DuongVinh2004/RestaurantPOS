# Screen Recipes

## Auth Login

Structure:

- Compact brand/title area.
- Login form card.
- Inline error alert.
- Submit button with pending state.
- Secondary help text.

## Account Overview

Structure:

- Greeting and account status.
- Current session panel.
- Loyalty panel only if stable/live.
- Recent reservations or bills.
- Dev-only sanitized diagnostics.

## Menu Browse

Structure:

- Header with search/filter shell.
- List of available menu items from stable live route.
- Category/detail affordances only when flags are enabled.
- Empty state for no available items.

## Reservation List

Structure:

- Upcoming reservation summary.
- Reservation cards with date, time, party size, status.
- Detail action.
- Create/reschedule/cancel only when live contract is stable.

## Bill or Payment

Structure:

- Amount and status header.
- Line items or preview.
- Deposit or bill payment action.
- Processing status with refresh.
- Failure state with retry guidance.

## Blocked Feature

Structure:

- Clear title.
- One sentence explaining availability.
- Safe alternate action.
- No fake records or disabled form fields that look broken.
