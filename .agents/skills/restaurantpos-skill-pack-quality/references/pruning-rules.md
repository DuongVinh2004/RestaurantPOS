# Pruning Rules

Use this file when the project has too many Codex skills or an external pack duplicates existing guidance.

## Keep

Keep skills that protect high-risk RestaurantPOS work:

- auth, identity, RBAC, branch scope
- FOH reservations, order lifecycle, KDS, checkout, inventory
- SQL-first schema and release artifacts
- API contracts and split-web auth/client contracts
- targeted verification, runtime smoke, audit, performance, and skill quality
- frontend app skills only when they encode app-specific patterns

## Merge

Merge skills when they are:

- generic Laravel, React, or debugging advice already covered by AGENTS.md
- duplicate safety checklists without scripts or app-specific routing
- broad process skills that can live inside `context-router`, `targeted-verification`, or `skill-pack-quality`
- UI micro-skills that can be one concise cross-app UI guard

## Delete

Delete temporary or unused skill-pack files when:

- all useful guidance has been merged
- the source pack fails validation
- the source pack lacks `agents/openai.yaml`
- it would add broad discovery metadata without a clear RestaurantPOS trigger

## Description Budget

- Keep frontmatter descriptions specific and short enough for discovery context.
- Include when-to-use triggers in the description.
- Keep long checklists in references and link them from `SKILL.md`.
