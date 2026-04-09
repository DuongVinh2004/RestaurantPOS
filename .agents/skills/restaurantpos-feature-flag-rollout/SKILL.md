---
name: restaurantpos-feature-flag-rollout
description: Change RestaurantPOS feature flags safely with rollout discipline. Use when Codex adds or modifies a feature flag, changes default state by environment, touches feature flag resolution or console commands, gates new entry points, or updates tests and docs for rollout-sensitive behavior.
---

# RestaurantPOS Feature Flag Rollout

Read `config/feature_flags.php`, `docs/runbooks/feature-flags-rollout-guide.md`, and `references/flags.md` before editing flag behavior.

## Workflow

1. Register or change the flag default in config with production-like safety in mind.
2. Review how `FeatureFlagService` resolves environment, branch, and fallback state.
3. Gate new entry points without breaking in-flight terminal flows unless the request explicitly requires a hard stop.
4. Update console, audit, docs, and tests in the same batch.
5. Verify both default behavior and override behavior.

## Guardrails

- Unknown keys should remain disabled
- Missing DB override should still fall back safely to config defaults
- Production-like defaults should stay conservative unless the request explicitly changes rollout policy
- Flag behavior that affects operator playbooks should also use `$restaurantpos-runbook-sync`
