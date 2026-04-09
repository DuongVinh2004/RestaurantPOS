---
name: restaurantpos-skill-pack-quality
description: Validate and maintain the RestaurantPOS project-local Codex skill pack. Use when Codex adds or edits skills, scripts, references, or `.codex` guidance and needs to catch placeholder text, stale metadata, broken relative links, missing `agents/openai.yaml`, or Python script errors before depending on the pack in future sessions.
---

# RestaurantPOS Skill Pack Quality

Read `AGENTS.md`, `.codex/AGENTS.md`, and `references/quality-rules.md` before editing many skills at once.

## Workflow

1. Run `python .agents/skills/restaurantpos-skill-pack-quality/scripts/validate_skill_pack.py` from the repo root before and after a large skill batch.
2. If only a few skills changed, pass their folders or names to keep the output tight.
3. Fix metadata, TODO placeholders, broken links, and script errors before polishing prose.
4. If a skill adds Python helpers, make sure the validator compiles them and run a representative smoke command manually.
5. In the final report, call out any remaining discovery limitation such as "new skills may require a fresh Codex session".

## Guardrails

- Keep `SKILL.md` procedural and concise; move long reference material into `references/`.
- Keep resource links one level deep from `SKILL.md`.
- Prefer Python stdlib in helper scripts so validation works without extra packages.
- Treat a stale `agents/openai.yaml` as a real defect because discovery quality depends on it.
- Do not leave scaffold markers, generic filler, or unused placeholder structure around.

## Commands

```bash
python .agents/skills/restaurantpos-skill-pack-quality/scripts/validate_skill_pack.py
python .agents/skills/restaurantpos-skill-pack-quality/scripts/validate_skill_pack.py restaurantpos-data-lifecycle restaurantpos-prompt-router
python .agents/skills/restaurantpos-skill-pack-quality/scripts/validate_skill_pack.py .agents/skills/restaurantpos-git-aware-verify --json
```
