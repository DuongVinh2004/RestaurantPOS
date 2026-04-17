# Verification Ladder

Pick the smallest set that proves the change.

## Always Consider

```bash
npm run lint
npm run typecheck
```

## Unit or Component Tests

```bash
npm run test -- api
npm run test -- auth
npm run test -- feature-flags
npm run test -- reservations
npm run test -- billing
```

Use the script names that exist in `customer-web/package.json`.

## Build

```bash
npm run build
```

Run this when changes touch:

- App Router routes or layouts.
- Providers.
- Env parsing.
- Feature flags.
- Next.js config.
- Shared client/server module boundaries.

## Browser Smoke

Start the dev server and verify visually when the batch changes major UI flows, navigation, or forms.

## Backend Contract Follow-Up

If the frontend change depends on route readiness, report whether backend contract gates were inspected or still need to run. Do not run backend artifact generation unless the batch changes backend contract files.
