Legacy staff-web modules archived during Patch 1.

Canonical runtime entrypoints now live under:
- `src/App.tsx`
- `src/app/router/index.tsx`
- `src/app/store/*`
- `src/features/*` workspace pages that are still outside `_legacy/`

Everything under `src/_legacy/` is reference-only:
- excluded from TypeScript build
- excluded from ESLint
- excluded from Vitest

Do not extend this tree for new work. Port any still-useful behavior into the active app before deleting legacy references.
