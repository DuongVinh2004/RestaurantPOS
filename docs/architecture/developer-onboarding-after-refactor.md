# Developer Onboarding After Refactor

## Read order

1. Start in `app/Modules/` and identify the owning module for the workflow you are touching.
2. Read the matching module HTTP controller to find the entrypoint.
3. Follow that controller into `Application/Services`.
4. Read the owning `Domain/Models`, `State`, and value objects.
5. Only then read `app/Platform/` if the flow depends on metrics, artifacts, release gates, or observability.

## Working rules

- When adding a feature, first decide whether it is domain-owned or platform-owned.
- If the code touches routes, requests, resources, or API artifacts, re-run the route and artifact gates.
- If the code touches privacy, notifications, waiting-list, or reporting behavior, assume the release artifacts and tests are contract-sensitive.
- If you see a legacy class under `app/Http/`, `app/Models/`, `app/Services/`, or `app/Support/`, check whether it is only a shim before editing it.

## Safe change checklist

- Use canonical `App\Modules\...` imports inside modules.
- Keep controllers thin and reuse existing application services.
- Re-run targeted tests for the touched modules plus route or artifact gates when HTTP surfaces move.
- Do not remove compatibility shims unless the repo proves no callers remain.
