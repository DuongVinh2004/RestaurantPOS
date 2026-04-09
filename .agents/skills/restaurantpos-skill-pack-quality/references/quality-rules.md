# Quality Rules

## Baseline checks

- Every skill directory must contain `SKILL.md` and `agents/openai.yaml`.
- `SKILL.md` frontmatter must contain only `name` and `description`.
- Frontmatter `name` must match the folder name.
- `description` must explain both what the skill does and when to use it.
- No file in the skill should contain scaffold leftovers such as template section headers, placeholder bracket text, or unfinished boilerplate prose.

## Resource checks

- Relative Markdown links in `SKILL.md` and `references/*.md` must resolve inside the skill folder.
- Python files under `scripts/` must compile with stdlib `py_compile`.
- Empty resource directories are acceptable only if they are part of the intended structure for the current batch.

## Manual follow-up after the validator passes

- Smoke-test new scripts with one realistic invocation.
- Read `agents/openai.yaml` and make sure the short description still matches the updated `SKILL.md`.
- If a skill changes routing or verification logic, update the matching process skills instead of leaving parallel logic to drift.
