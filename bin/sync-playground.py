#!/usr/bin/env python3
"""Inline shared playground PHP scripts into every theme's playground/blueprint.json.

Why this exists:
    Playground blueprints can fetch a script via writeFile { resource: url },
    but raw.githubusercontent.com sets cache-control: max-age=300 on every
    response, and Playground's own resource layer caches URL fetches across
    boot attempts. The result is that updates to playground/*.php can take
    5+ minutes to propagate, and Playground will happily run the previous
    version of the script against the new blueprint.

    Inlining the scripts directly into each blueprint.json makes every script
    body part of the same payload as the blueprint itself: there is only one
    URL to invalidate, and it is fetched fresh on every boot anyway.

What this script does:
    For each theme it walks the blueprint's `steps` array, finds every
    `writeFile` step, and replaces the `data` field with the current content
    of the matching source file from playground/.

    Special case — wo-configure.php:
        The source file (playground/wo-configure.php) is theme-agnostic. Each
        theme needs its own WO_THEME_NAME constant. This script prepends:

            <?php define('WO_THEME_NAME', '<Theme>');

        …to the inlined data field so the shared source stays clean.
        The source file must NOT start with <?php (it starts with a doc-comment
        protected by `if (!defined('ABSPATH')) exit;`).

Usage:
    python3 bin/sync-playground.py
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT   = Path(__file__).resolve().parent.parent
THEMES = ("obel", "chonk")

# Map each blueprint writeFile target path -> source file path (relative to ROOT).
MAPPINGS: dict[str, Path] = {
    "/wordpress/wo-import.php":
        ROOT / "playground" / "wo-import.php",
    "/wordpress/wo-configure.php":
        ROOT / "playground" / "wo-configure.php",
    "/wordpress/wp-content/mu-plugins/wo-cart-mu.php":
        ROOT / "playground" / "wo-cart-mu.php",
}

# Per-theme name used in the WO_THEME_NAME prepend for wo-configure.php.
THEME_NAMES: dict[str, str] = {
    "obel":  "Obel",
    "chonk": "Chonk",
}


def build_body(target_path: str, source_path: Path, theme: str) -> str:
    """Read source_path and optionally prepend the theme-name define."""
    body = source_path.read_text()
    if target_path == "/wordpress/wo-configure.php":
        define_line = f"<?php define('WO_THEME_NAME', '{THEME_NAMES[theme]}');\n"
        # Strip the opening <?php from the source so we don't get two opening tags.
        stripped = body.lstrip()
        if stripped.startswith("<?php"):
            stripped = stripped[5:].lstrip("\n")
        body = define_line + stripped
    return body


def sync(theme: str) -> list[str]:
    """Sync all writeFile targets for a single theme. Returns list of updated paths."""
    bp_path = ROOT / theme / "playground" / "blueprint.json"
    if not bp_path.exists():
        print(f"skip: {bp_path} (missing)", file=sys.stderr)
        return []

    bp = json.loads(bp_path.read_text())
    updated: list[str] = []

    for step in bp.get("steps", []):
        if step.get("step") != "writeFile":
            continue
        target = step.get("path", "")
        if target not in MAPPINGS:
            continue
        source = MAPPINGS[target]
        if not source.exists():
            print(f"warn: source {source} not found for step path {target}", file=sys.stderr)
            continue

        body = build_body(target, source, theme)
        if step.get("data") == body:
            continue  # already in sync; skip to keep git diffs clean

        step["data"] = body
        updated.append(target)

    if updated:
        bp_path.write_text(json.dumps(bp, indent=2) + "\n")

    return updated


def main() -> int:
    # Verify all source files exist before touching any blueprint.
    missing = [p for p in MAPPINGS.values() if not p.exists()]
    if missing:
        for p in missing:
            print(f"error: source file not found: {p}", file=sys.stderr)
        return 1

    any_changed = False
    for theme in THEMES:
        updated = sync(theme)
        if updated:
            any_changed = True
            for path in updated:
                print(f"updated {theme}: {path}")

    if not any_changed:
        print("already in sync")
    return 0


if __name__ == "__main__":
    sys.exit(main())
