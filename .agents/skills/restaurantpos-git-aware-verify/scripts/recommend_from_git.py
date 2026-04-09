#!/usr/bin/env python3
"""Collect changed files from Git when possible, then reuse the repo-native selector."""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path


def repo_root() -> Path:
    return Path(__file__).resolve().parents[4]


def run_git(args: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(["git", *args], cwd=repo_root(), capture_output=True, text=True)


def in_git_repo() -> bool:
    result = run_git(["rev-parse", "--is-inside-work-tree"])
    return result.returncode == 0 and result.stdout.strip() == "true"


def unique_paths(paths: list[str]) -> list[str]:
    seen: set[str] = set()
    ordered: list[str] = []
    for raw in paths:
        normalized = raw.strip().replace("\\", "/")
        if not normalized or normalized in seen:
            continue
        seen.add(normalized)
        ordered.append(normalized)
    return ordered


def collect_git_paths(base: str | None) -> tuple[list[str], list[str]]:
    notes: list[str] = []
    paths: list[str] = []

    if base:
        result = run_git(["diff", "--name-only", "--diff-filter=ACMR", f"{base}...HEAD"])
        if result.returncode != 0:
            raise RuntimeError(result.stderr.strip() or f"Unable to diff against base `{base}`")
        paths.extend(result.stdout.splitlines())
        notes.append(f"collected branch diff against {base}")
        notes.append("considered local staged, unstaged, and untracked files alongside the base diff")

    for args, label in (
        (["diff", "--name-only", "--diff-filter=ACMR"], "unstaged changes"),
        (["diff", "--cached", "--name-only", "--diff-filter=ACMR"], "staged changes"),
        (["ls-files", "--others", "--exclude-standard"], "untracked files"),
    ):
        result = run_git(args)
        if result.returncode != 0:
            raise RuntimeError(result.stderr.strip() or f"Git command failed: {' '.join(args)}")
        if result.stdout.strip():
            paths.extend(result.stdout.splitlines())
            notes.append(f"included {label}")

    return unique_paths(paths), notes


def collect_explicit_paths(args_paths: list[str]) -> list[str]:
    if args_paths:
        return unique_paths(args_paths)

    stdin_paths = [line.strip() for line in sys.stdin if line.strip()]
    return unique_paths(stdin_paths)


def collect_verification(paths: list[str]) -> dict[str, object]:
    result = subprocess.run(
        ["php", "artisan", "booking:verify-select", "--json", *[f"--path={path}" for path in paths]],
        cwd=repo_root(),
        capture_output=True,
        text=True,
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or result.stdout.strip())

    return json.loads(result.stdout)


def main() -> int:
    parser = argparse.ArgumentParser(description="Recommend verification commands from Git diff data or explicit paths.")
    parser.add_argument("paths", nargs="*", help="Explicit paths to analyze when Git is unavailable or when overriding diff collection")
    parser.add_argument("--base", help="Optional Git base ref for branch diff collection")
    parser.add_argument("--json", action="store_true", dest="json_output", help="Emit machine-readable JSON")
    args = parser.parse_args()

    notes: list[str] = []
    explicit_paths = collect_explicit_paths(args.paths)

    if explicit_paths:
        paths = explicit_paths
        notes.append("used explicit path input")
    elif in_git_repo():
        try:
            paths, git_notes = collect_git_paths(args.base)
        except RuntimeError as exc:
            print(str(exc), file=sys.stderr)
            return 1
        notes.extend(git_notes)
    else:
        print("Git metadata is unavailable and no explicit paths were provided.", file=sys.stderr)
        return 1

    if not paths:
        print("No changed files were found from the selected source.", file=sys.stderr)
        return 1

    try:
        verification = collect_verification(paths)
    except RuntimeError as exc:
        print(str(exc), file=sys.stderr)
        return 1

    verification["notes"] = list(dict.fromkeys(notes + list(verification.get("notes", []))))

    if args.json_output:
        print(json.dumps(verification, indent=2))
        return 0

    print("Changed files:")
    for path in paths:
        print(f"- {path}")

    print("Recommended skills:")
    for skill in verification.get("skills", []):
        print(f"- ${skill}")

    print("Recommended commands:")
    for command in verification.get("commands", []):
        print(f"- [{command.get('tier', 'verify')}] {command.get('command', '')}")

    if verification.get("notes"):
        print("Notes:")
        for note in verification.get("notes", []):
            print(f"- {note}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
