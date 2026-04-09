#!/usr/bin/env python3
"""Validate the RestaurantPOS project-local Codex skill pack."""

from __future__ import annotations

import argparse
import json
import py_compile
import re
import sys
from pathlib import Path


VALID_NAME = re.compile(r"^[a-z0-9-]{1,64}$")
PLACEHOLDER_PATTERNS = [
    re.compile(r"\[" + r"TODO(?::|\])", re.IGNORECASE),
    re.compile(" ".join(["Structuring", "This", "Skill"]), re.IGNORECASE),
    re.compile(" ".join(["Complete", "and", "informative", "explanation", "of", "what", "the", "skill", "does"]), re.IGNORECASE),
    re.compile(" ".join(["Delete", "this", "entire"]) + r'.*"'
        + " ".join(["Structuring", "This", "Skill"]) + r'" section when done', re.IGNORECASE),
]
MARKDOWN_LINK = re.compile(r"\[[^\]]+\]\(([^)#]+)(?:#[^)]+)?\)")
TEXT_EXTENSIONS = {".md", ".py", ".yaml", ".yml", ".json", ".toml"}


def repo_root() -> Path:
    return Path(__file__).resolve().parents[4]


def skills_root() -> Path:
    return repo_root() / ".agents" / "skills"


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="ignore")


def discover_targets(raw_targets: list[str]) -> list[Path]:
    base = skills_root()
    if not raw_targets:
        return sorted(path for path in base.iterdir() if path.is_dir())

    resolved: list[Path] = []
    for raw in raw_targets:
        candidate = Path(raw)
        if not candidate.is_absolute():
            if (base / raw).is_dir():
                candidate = base / raw
            else:
                candidate = (repo_root() / raw).resolve()
        if candidate.is_dir():
            resolved.append(candidate)
        else:
            raise FileNotFoundError(f"Skill target not found: {raw}")

    return sorted(resolved)


def parse_frontmatter(skill_path: Path) -> tuple[dict[str, str], list[str], str]:
    text = read_text(skill_path)
    if not text.startswith("---"):
        return {}, ["SKILL.md must start with YAML frontmatter"], text

    lines = text.splitlines()
    try:
        closing = lines[1:].index("---") + 1
    except ValueError:
        return {}, ["SKILL.md frontmatter is missing closing ---"], text

    frontmatter_lines = lines[1:closing]
    body = "\n".join(lines[closing + 1 :])
    data: dict[str, str] = {}
    errors: list[str] = []

    for line in frontmatter_lines:
        if ":" not in line:
            errors.append(f"Invalid frontmatter line: {line}")
            continue
        key, value = line.split(":", 1)
        data[key.strip()] = value.strip().strip('"').strip("'")

    extra_keys = sorted(set(data) - {"name", "description"})
    if extra_keys:
        errors.append(f"SKILL.md frontmatter has unsupported keys: {', '.join(extra_keys)}")

    for required in ("name", "description"):
        if not data.get(required):
            errors.append(f"SKILL.md frontmatter is missing `{required}`")

    return data, errors, body


def parse_openai_yaml(path: Path) -> tuple[dict[str, str], list[str]]:
    if not path.exists():
        return {}, ["agents/openai.yaml is missing"]

    lines = read_text(path).splitlines()
    values: dict[str, str] = {}
    in_interface = False

    for line in lines:
        stripped = line.strip()
        if not stripped:
            continue
        if stripped == "interface:":
            in_interface = True
            continue
        if not in_interface:
            continue
        if not line.startswith("  "):
            break
        if ":" not in stripped:
            continue
        key, value = stripped.split(":", 1)
        values[key.strip()] = value.strip().strip('"').strip("'")

    errors: list[str] = []
    for required in ("display_name", "short_description", "default_prompt"):
        if not values.get(required):
            errors.append(f"agents/openai.yaml is missing `interface.{required}`")

    return values, errors


def check_placeholders(skill_dir: Path) -> list[str]:
    errors: list[str] = []
    for path in skill_dir.rglob("*"):
        if not path.is_file() or path.suffix.lower() not in TEXT_EXTENSIONS:
            continue
        text = read_text(path)
        for pattern in PLACEHOLDER_PATTERNS:
            if pattern.search(text):
                rel = path.relative_to(repo_root()).as_posix()
                errors.append(f"Placeholder text found in {rel}")
                break
    return errors


def check_links(skill_dir: Path) -> list[str]:
    errors: list[str] = []
    for path in skill_dir.rglob("*.md"):
        text = read_text(path)
        for match in MARKDOWN_LINK.findall(text):
            if match.startswith(("http://", "https://", "mailto:")):
                continue
            target = (path.parent / match).resolve()
            if not target.exists():
                rel = path.relative_to(repo_root()).as_posix()
                errors.append(f"Broken relative link in {rel}: {match}")
    return errors


def check_python_scripts(skill_dir: Path) -> list[str]:
    errors: list[str] = []
    for path in sorted(skill_dir.rglob("*.py")):
        try:
            py_compile.compile(str(path), doraise=True)
        except py_compile.PyCompileError as exc:
            rel = path.relative_to(repo_root()).as_posix()
            errors.append(f"Python compile failed for {rel}: {exc.msg}")
    return errors


def validate_skill(skill_dir: Path) -> dict[str, object]:
    errors: list[str] = []
    warnings: list[str] = []

    skill_md = skill_dir / "SKILL.md"
    frontmatter, fm_errors, body = parse_frontmatter(skill_md)
    errors.extend(fm_errors)

    name = str(frontmatter.get("name", ""))
    if name and not VALID_NAME.fullmatch(name):
        errors.append(f"Invalid skill name `{name}`")
    if name and name != skill_dir.name:
        errors.append(f"Frontmatter name `{name}` does not match folder `{skill_dir.name}`")

    description = str(frontmatter.get("description", ""))
    if description and len(description.split()) < 10:
        warnings.append("Description is unusually short; discovery quality may be weak")

    if not body.strip():
        errors.append("SKILL.md body is empty")
    elif len(body.splitlines()) > 500:
        warnings.append("SKILL.md body exceeds 500 lines; consider moving detail into references")

    openai_values, openai_errors = parse_openai_yaml(skill_dir / "agents" / "openai.yaml")
    errors.extend(openai_errors)
    if openai_values.get("default_prompt") and f"${skill_dir.name}" not in openai_values["default_prompt"]:
        warnings.append("agents/openai.yaml default_prompt does not mention the skill explicitly")

    errors.extend(check_placeholders(skill_dir))
    errors.extend(check_links(skill_dir))
    errors.extend(check_python_scripts(skill_dir))

    return {
        "skill": skill_dir.name,
        "path": str(skill_dir),
        "errors": errors,
        "warnings": warnings,
        "ok": not errors,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Validate RestaurantPOS project-local Codex skills.")
    parser.add_argument("targets", nargs="*", help="Skill directories, skill names, or paths")
    parser.add_argument("--json", action="store_true", dest="json_output", help="Emit JSON output")
    args = parser.parse_args()

    try:
        targets = discover_targets(args.targets)
    except FileNotFoundError as exc:
        print(str(exc), file=sys.stderr)
        return 1

    results = [validate_skill(target) for target in targets]
    failed = [result for result in results if not result["ok"]]

    if args.json_output:
        print(json.dumps({"results": results, "failed_count": len(failed)}, indent=2))
        return 1 if failed else 0

    for result in results:
        status = "PASS" if result["ok"] else "FAIL"
        print(f"{status} {result['skill']}")
        for error in result["errors"]:
            print(f"  - {error}")
        for warning in result["warnings"]:
            print(f"  - warning: {warning}")

    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
