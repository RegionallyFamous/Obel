#!/usr/bin/env python3
"""Compute the (mode, themes, do_full_shoot) tuple for .github/workflows/visual.yml.

Invoked by the `setup` job. Emits GITHUB_OUTPUT-formatted lines so the
workflow can fan out into a per-theme matrix downstream:

    mode=<check-changed|regenerate-gallery|rebaseline|check-manual>
    themes=<JSON array of theme slugs, e.g. ["aero","obel"]>
    do_full_shoot=<true|false>

`themes` is always a JSON array (never empty for matrix-strategy
consumption — when no themes need shooting we instead emit themes=[]
and the downstream `shoot` job is gated `if: needs.setup.outputs.themes
!= '[]'` to skip cleanly).

Inputs are read from environment variables (set by the workflow):
    EVENT_NAME    : github.event_name (push / pull_request / workflow_dispatch)
    INPUT_MODE    : workflow_dispatch input (regenerate-gallery, rebaseline, check-changed)
    INPUT_THEMES  : workflow_dispatch input — space-separated slugs, blank = all
    BASE_REF      : git base for `--changed` (default `origin/main`)

Why this lives in bin/ rather than inline in visual.yml:
    The matrix-scope logic is non-trivial (it imports `snap._changed_themes`
    + `snap.discover_themes`) and inline `python3 -c` blocks in YAML are a
    debugging nightmare (no syntax highlighting, escape hell, no traceback).
    A real script also lets us add `--dry-run` for local verification.
"""
from __future__ import annotations

import json
import os
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO_ROOT / "bin"))

# Imported AFTER sys.path tweak. snap.py is the single source of truth
# for "which slugs are themes" and "which themes did this commit touch".
from snap import _changed_themes, discover_themes  # type: ignore  # noqa: E402


def compute() -> dict[str, str]:
    event = os.environ.get("EVENT_NAME", "")
    input_mode = os.environ.get("INPUT_MODE", "").strip()
    input_themes = os.environ.get("INPUT_THEMES", "").strip()
    base_ref = os.environ.get("BASE_REF", "origin/main").strip() or "origin/main"

    explicit_themes = input_themes.split() if input_themes else []

    if event == "workflow_dispatch" and input_mode == "regenerate-gallery":
        themes = explicit_themes or discover_themes()
        return {"mode": "regenerate-gallery", "themes": themes, "do_full_shoot": "true"}

    if event == "workflow_dispatch" and input_mode == "rebaseline":
        themes = explicit_themes or discover_themes()
        return {"mode": "rebaseline", "themes": themes, "do_full_shoot": "true"}

    if event == "workflow_dispatch" and explicit_themes:
        # Manual check against an explicit theme list — useful for
        # re-running a previously failed gate without waiting for a
        # rebaseline. Behaves like check-changed (shoot + diff +
        # report) but on the user-supplied subset.
        return {"mode": "check-manual", "themes": explicit_themes, "do_full_shoot": "false"}

    # check-changed (default for push / PR, and for workflow_dispatch
    # with mode=check-changed and no themes). Use git to figure out
    # which themes actually need re-shooting.
    affected = _changed_themes(base_ref)
    if affected is None:
        # framework-wide change (bin/* touched) → shoot everything
        themes = discover_themes()
    elif not affected:
        # docs-only / tooling-only change → matrix is empty, downstream
        # jobs skip via `if: needs.setup.outputs.themes != '[]'`
        themes = []
    else:
        themes = affected

    return {"mode": "check-changed", "themes": themes, "do_full_shoot": "false"}


def main() -> int:
    result = compute()
    themes = result["themes"]
    assert isinstance(themes, list)

    # GITHUB_OUTPUT is the conventional way to pass values between
    # workflow steps; falls back to stdout for local --dry-run usage.
    out_path = os.environ.get("GITHUB_OUTPUT")
    lines = [
        f"mode={result['mode']}",
        f"themes={json.dumps(themes)}",
        f"do_full_shoot={result['do_full_shoot']}",
        # `has_themes` is a convenience boolean for `if:` expressions.
        # The matrix-strategy `if: themes != '[]'` works but reads worse.
        f"has_themes={'true' if themes else 'false'}",
    ]

    if out_path:
        with open(out_path, "a", encoding="utf-8") as fh:
            for line in lines:
                fh.write(line + "\n")

    # Always echo to stdout so the run log shows the resolved scope.
    for line in lines:
        print(line)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
