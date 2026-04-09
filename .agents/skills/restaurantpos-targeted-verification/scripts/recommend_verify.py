#!/usr/bin/env python3
"""Recommend the smallest safe verification set for RestaurantPOS changes."""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path


def repo_root() -> Path:
    return Path(__file__).resolve().parents[4]


def run_selector(paths: list[str], stdin_data: str | None) -> dict[str, object]:
    command = ["php", "artisan", "booking:verify-select", "--json"]
    for path in paths:
        command.append(f"--path={path}")

    if stdin_data is not None:
        command.append("--stdin")

    result = subprocess.run(
        command,
        cwd=repo_root(),
        capture_output=True,
        text=True,
        input=stdin_data,
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or result.stdout.strip() or "booking:verify-select failed")

    return json.loads(result.stdout)


def main() -> int:
    parser = argparse.ArgumentParser(description="Recommend RestaurantPOS verification commands from changed files.")
    parser.add_argument("paths", nargs="*", help="Changed files or relevant paths")
    parser.add_argument("--json", action="store_true", dest="json_output", help="Emit machine-readable JSON")
    args = parser.parse_args()

    stdin_data = None
    paths = list(args.paths)
    if not paths:
        stdin_data = sys.stdin.read()
        if not stdin_data.strip():
            print("Provide one or more paths as arguments or via stdin.", file=sys.stderr)
            return 1

    try:
        result = run_selector(paths, stdin_data)
    except RuntimeError as exc:
        print(str(exc), file=sys.stderr)
        return 1

    if args.json_output:
        print(json.dumps(result, indent=2))
        return 0

    print("Recommended skills:")
    for skill in result.get("skills", []):
        print(f"- ${skill}")

    print("\nRecommended commands:")
    for command in result.get("commands", []):
        print(f"- [{command.get('tier', 'verify')}] {command.get('command', '')}")

    if result.get("notes"):
        print("\nNotes:")
        for note in result.get("notes", []):
            print(f"- {note}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
