# Read Map

Start with the smallest useful set.

## New Customer-Web App Setup

Read:

- Root `AGENTS.md`
- `.codex/AGENTS.md`
- Existing `package.json` files
- Existing `customer-web` files if present
- Contract router skill

Avoid reading full backend modules until a specific API surface is chosen.

## API Flow

Read:

- Relevant frozen OpenAPI path via targeted search
- Generated SDK or mutation contract section for the route
- `lib/api` or intended API client files
- Feature adapter and tests

## UI Screen

Read:

- Current route/page file
- Closest feature component
- Shared UI/state components
- Design system and screen recipe references

## Form or Mutation

Read:

- Existing form component in same feature
- Feature adapter
- API client error parser
- Tests for form or mutation behavior

## Verification

Read:

- `customer-web/package.json`
- Changed files
- Existing tests beside changed files
- Verification skill reference
