#!/usr/bin/env python3
"""Inline playground/wo-import.php into every theme's playground/blueprint.json.

Why this exists:
    Playground blueprints can fetch a script via writeFile { resource: url },
    but raw.githubusercontent.com sets cache-control: max-age=300 on every
    response, and Playground's own resource layer caches URL fetches across
    boot attempts. The result is that updates to playground/wo-import.php
    can take 5+ minutes to propagate, and Playground will happily run the
    previous version of the script against the new blueprint.

    Inlining the script directly into each blueprint.json makes the script
    body part of the same payload as the blueprint itself: there is only
    one URL to invalidate, and it is fetched fresh on every boot anyway.

What this script does:
    Reads playground/wo-import.php and writes its raw contents into the
    `data` field of the writeFile step that targets /wordpress/wo-import.php
    in each theme's blueprint. Run it whenever wo-import.php changes, then
    commit the resulting blueprint.json updates alongside the script change.

Usage:
    python3 bin/sync-playground.py
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SCRIPT = ROOT / "playground" / "wo-import.php"
THEMES = ("obel", "chonk")


def sync(theme: str, body: str) -> bool:
    bp_path = ROOT / theme / "playground" / "blueprint.json"
    if not bp_path.exists():
        print(f"skip: {bp_path} (missing)", file=sys.stderr)
        return False

    bp = json.loads(bp_path.read_text())
    target = "/wordpress/wo-import.php"
    found = False
    for step in bp.get("steps", []):
        if step.get("step") == "writeFile" and step.get("path") == target:
            if step.get("data") == body:
                # Already in sync; do not rewrite (keeps git diffs clean).
                return False
            step["data"] = body
            found = True
            break

    if not found:
        print(f"warn: no writeFile step for {target} in {bp_path}", file=sys.stderr)
        return False

    bp_path.write_text(json.dumps(bp, indent=2) + "\n")
    return True


def main() -> int:
    if not SCRIPT.exists():
        print(f"error: {SCRIPT} not found", file=sys.stderr)
        return 1

    body = SCRIPT.read_text()
    changed = []
    for theme in THEMES:
        if sync(theme, body):
            changed.append(theme)

    if changed:
        print(f"updated: {', '.join(changed)}")
    else:
        print("already in sync")
    return 0


if __name__ == "__main__":
    sys.exit(main())
