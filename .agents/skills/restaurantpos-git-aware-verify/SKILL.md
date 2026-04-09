---
name: restaurantpos-git-aware-verify
description: Derive RestaurantPOS verification recommendations from Git diff data when available and fall back cleanly when it is not. Use when Codex wants changed-file based test and gate selection without manually assembling the path list, or when the repo is not yet a Git worktree and the same verification wrapper should still accept explicit paths.
---

# RestaurantPOS Git-Aware Verify

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/diff-policy.md` before using diff-based verification.

## Workflow

1. Run `python .agents/skills/restaurantpos-git-aware-verify/scripts/recommend_from_git.py` from the repo root.
2. If the repo has Git metadata, let the script collect unstaged, staged, and untracked paths.
3. If the repo is not a Git worktree, pass explicit paths or pipe them on stdin.
4. Feed the resulting commands into the normal verification ladder and report what the wrapper could or could not infer.
5. When a base ref is supplied, still mention whether local uncommitted changes were included.

## Guardrails

- Do not assume a clean worktree.
- Do not ignore untracked files because scaffolds and new tests often start there.
- Treat `routes/api.php`, `config/booking.php`, `config/staff_capabilities.php`, and `database/schema/mysql-schema.sql` as escalation triggers even in diff mode.
- If Git metadata is missing, say that clearly instead of pretending the diff is empty.

## Commands

```bash
python .agents/skills/restaurantpos-git-aware-verify/scripts/recommend_from_git.py
python .agents/skills/restaurantpos-git-aware-verify/scripts/recommend_from_git.py --base origin/main
python .agents/skills/restaurantpos-git-aware-verify/scripts/recommend_from_git.py app/Services/DataLifecycle/CustomerAnonymizationService.php routes/api.php
```
