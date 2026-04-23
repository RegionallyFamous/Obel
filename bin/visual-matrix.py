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

import argparse
import json
import os
import sys
from pathlib import Path
from typing import NamedTuple

REPO_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO_ROOT / "bin"))

# Imported AFTER sys.path tweak. snap.py is the single source of truth
# for "which slugs are themes" and "which themes did this commit touch".
from snap import _changed_themes, discover_themes  # noqa: E402


class Scope(NamedTuple):
    """Resolved (mode, themes, do_full_shoot) tuple emitted to GITHUB_OUTPUT.

    NamedTuple (rather than @dataclass) is deliberate: the smoke test
    in tests/tools/test_bin_scripts_smoke.py loads this module via
    importlib.util.spec_from_file_location (the filename has a dash,
    which isn't a valid Python identifier), and @dataclass's
    __module__ resolution chokes when sys.modules has no entry for
    the loaded module name. NamedTuple sidesteps that entirely.
    """

    mode: str
    themes: list[str]
    do_full_shoot: bool


def compute(
    event: str,
    input_mode: str,
    input_themes: str,
    base_ref: str,
) -> Scope:
    """Pure function: turn the (event, mode, themes, base) inputs into a Scope.

    Split out from main() so the unit tests in
    tests/tools/test_visual_matrix.py can drive every code path without
    monkey-patching environment variables.
    """
    explicit_themes = input_themes.split() if input_themes else []

    if event == "workflow_dispatch" and input_mode == "regenerate-gallery":
        themes = explicit_themes or discover_themes()
        return Scope(mode="regenerate-gallery", themes=themes, do_full_shoot=True)

    if event == "workflow_dispatch" and input_mode == "rebaseline":
        themes = explicit_themes or discover_themes()
        return Scope(mode="rebaseline", themes=themes, do_full_shoot=True)

    if event == "workflow_dispatch" and explicit_themes:
        # Manual check against an explicit theme list — useful for
        # re-running a previously failed gate without waiting for a
        # rebaseline. Behaves like check-changed (shoot + diff +
        # report) but on the user-supplied subset.
        return Scope(mode="check-manual", themes=explicit_themes, do_full_shoot=False)

    # check-changed (default for push / PR, and for workflow_dispatch
    # with mode=check-changed and no themes). Use git to figure out
    # which themes actually need re-shooting.
    affected = _changed_themes(base_ref)
    if affected is None:
        # framework-wide change (bin/* touched) → shoot everything
        themes = discover_themes()
    elif not affected:
        # docs-only / tooling-only change → matrix is empty, downstream
        # jobs skip via `if: needs.setup.outputs.has_themes == 'true'`.
        themes = []
    else:
        themes = affected

    return Scope(mode="check-changed", themes=themes, do_full_shoot=False)


def emit(scope: Scope, out_stream, out_path: str | None) -> None:
    """Write GITHUB_OUTPUT lines (when running in CI) and echo to stdout."""
    lines = [
        f"mode={scope.mode}",
        f"themes={json.dumps(scope.themes)}",
        f"do_full_shoot={'true' if scope.do_full_shoot else 'false'}",
        # `has_themes` is a convenience boolean for `if:` expressions.
        # The matrix-strategy `if: themes != '[]'` works but reads worse.
        f"has_themes={'true' if scope.themes else 'false'}",
    ]

    if out_path:
        with open(out_path, "a", encoding="utf-8") as fh:
            for line in lines:
                fh.write(line + "\n")

    for line in lines:
        print(line, file=out_stream)


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(
        prog="visual-matrix.py",
        description=(
            "Compute the (mode, themes, do_full_shoot) tuple for "
            ".github/workflows/visual.yml's setup job. Reads inputs "
            "from EVENT_NAME / INPUT_MODE / INPUT_THEMES / BASE_REF "
            "environment variables (set by the workflow) and emits "
            "GITHUB_OUTPUT-formatted lines."
        ),
        epilog=(
            "Example local dry-run:\n"
            "  EVENT_NAME=workflow_dispatch INPUT_MODE=regenerate-gallery \\\n"
            "      python3 bin/visual-matrix.py"
        ),
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    # Flag exists purely so the smoke-test harness in
    # tests/tools/test_bin_scripts_smoke.py recognises this as an
    # argparse-driven script and can invoke `--help` on it.
    p.add_argument(
        "--dry-run",
        action="store_true",
        help=(
            "Echo resolved scope to stdout but don't touch GITHUB_OUTPUT "
            "even if it is set in the environment. Useful for local "
            "verification."
        ),
    )
    return p


def main(argv: list[str] | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)

    scope = compute(
        event=os.environ.get("EVENT_NAME", ""),
        input_mode=os.environ.get("INPUT_MODE", "").strip(),
        input_themes=os.environ.get("INPUT_THEMES", "").strip(),
        base_ref=(os.environ.get("BASE_REF", "origin/main").strip()
                  or "origin/main"),
    )
    out_path = None if args.dry_run else os.environ.get("GITHUB_OUTPUT")
    emit(scope, sys.stdout, out_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
